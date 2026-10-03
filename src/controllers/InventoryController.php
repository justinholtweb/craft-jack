<?php

namespace justinholtweb\jack\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\jack\models\Edition;
use justinholtweb\jack\models\ServiceEntry;
use justinholtweb\jack\Plugin;
use justinholtweb\jack\services\Compiler;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The inventory screens: what this site hands data to, and what it forgot to mention.
 */
class InventoryController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(Plugin::PERMISSION_VIEW);

        return true;
    }

    public function actionIndex(): Response
    {
        $plugin = Plugin::getInstance();

        return $this->renderTemplate('jack/inventory/_index', [
            'entries' => $plugin->inventory->enabled(),
            'disabled' => array_filter($plugin->inventory->all(), static fn(ServiceEntry $entry) => !$entry->enabled),
            'available' => $plugin->inventory->available(),
            'categories' => \justinholtweb\jack\services\Inventory::categories(),
            'canDetect' => Edition::allowsDetection($plugin->isPro()),
            'canManage' => Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_MANAGE),
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionEdit(string $serviceKey): Response
    {
        $plugin = Plugin::getInstance();
        $entry = $plugin->inventory->get($serviceKey);

        if (!$entry) {
            throw new NotFoundHttpException('Service not found');
        }

        return $this->renderTemplate('jack/inventory/_edit', [
            'entry' => $entry,
            'canManage' => Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_MANAGE),
            'bases' => [
                ServiceEntry::BASIS_CONSENT => Craft::t('jack', 'Consent — Art. 6 (1) (a) GDPR'),
                ServiceEntry::BASIS_CONTRACT => Craft::t('jack', 'Contract — Art. 6 (1) (b) GDPR'),
                ServiceEntry::BASIS_OBLIGATION => Craft::t('jack', 'Legal obligation — Art. 6 (1) (c) GDPR'),
                ServiceEntry::BASIS_INTEREST => Craft::t('jack', 'Legitimate interest — Art. 6 (1) (f) GDPR'),
            ],
            'transfers' => [
                ServiceEntry::TRANSFER_NONE => Craft::t('jack', 'Stays in the EEA'),
                ServiceEntry::TRANSFER_ADEQUACY => Craft::t('jack', 'Adequacy decision — Art. 45'),
                ServiceEntry::TRANSFER_DPF => Craft::t('jack', 'EU–US Data Privacy Framework'),
                ServiceEntry::TRANSFER_SCC => Craft::t('jack', 'Standard contractual clauses — Art. 46'),
            ],
        ]);
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE);

        $plugin = Plugin::getInstance();
        $serviceKey = $this->request->getRequiredBodyParam('serviceKey');
        $entry = $plugin->inventory->get($serviceKey);

        if (!$entry) {
            throw new NotFoundHttpException('Service not found');
        }

        foreach (['privacyUrl', 'optOutUrl'] as $param) {
            if ($this->stringParam($param) !== null && $this->webUrl($param) === null) {
                $this->setFailFlash(Craft::t('jack', 'Links must start with http:// or https://.'));

                return $this->redirect('jack/inventory/' . $serviceKey);
            }
        }

        $entry->enabled = (bool)$this->request->getBodyParam('enabled', true) || $entry->isAlwaysOn();
        $entry->overrides = array_merge($entry->overrides, [
            'label' => $this->stringParam('label'),
            'provider' => $this->stringParam('provider'),
            'providerAddress' => $this->stringParam('providerAddress'),
            'privacyUrl' => $this->webUrl('privacyUrl'),
            'optOutUrl' => $this->webUrl('optOutUrl'),
            'legalBasis' => $this->stringParam('legalBasis'),
            'transfer' => $this->stringParam('transfer'),
            'purpose' => $this->languageMap('purpose'),
            'data' => $this->languageMap('data'),
            'retention' => $this->languageMap('retention'),
            'notes' => $this->languageMap('notes'),
        ]);

        if (!$plugin->inventory->save($entry)) {
            $this->setFailFlash(Craft::t('jack', 'Couldn’t save the service.'));

            return $this->redirect('jack/inventory/' . $serviceKey);
        }

        $this->setSuccessFlash(Craft::t('jack', 'Service saved.'));

        return $this->redirect('jack/inventory');
    }

    public function actionAdd(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE);

        $plugin = Plugin::getInstance();
        $serviceKey = trim((string)$this->request->getRequiredBodyParam('serviceKey'));

        $entry = $plugin->inventory->add($serviceKey);

        if (!$entry) {
            $this->setFailFlash(Craft::t('jack', 'Couldn’t add that service.'));

            return $this->redirect('jack/inventory');
        }

        $this->setSuccessFlash(Craft::t('jack', '{service} added.', ['service' => $entry->label()]));

        // A custom service arrives with nothing written about it, so send the author straight to
        // where they have to write it rather than back to a list with a hole in it.
        return $this->redirect($entry->isKnown() ? 'jack/inventory' : 'jack/inventory/' . $entry->serviceKey);
    }

    public function actionRemove(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE);

        $serviceKey = $this->request->getRequiredBodyParam('serviceKey');
        $removed = Plugin::getInstance()->inventory->delete($serviceKey);

        if ($this->request->getAcceptsJson()) {
            return $removed
                ? $this->asSuccess(Craft::t('jack', 'Service removed.'))
                : $this->asFailure(Craft::t('jack', 'That entry can’t be removed — it describes something the site does whether or not it is written down.'));
        }

        if (!$removed) {
            $this->setFailFlash(Craft::t('jack', 'That entry can’t be removed — it describes something the site does whether or not it is written down.'));
        } else {
            $this->setSuccessFlash(Craft::t('jack', 'Service removed.'));
        }

        return $this->redirect('jack/inventory');
    }

    /**
     * @throws ForbiddenHttpException
     */
    public function actionDetect(): Response
    {
        $plugin = Plugin::getInstance();

        if (!Edition::allowsDetection($plugin->isPro())) {
            throw new ForbiddenHttpException(Craft::t('jack', 'Finding undeclared services is a Pro feature.'));
        }

        return $this->renderTemplate('jack/inventory/_detect', [
            'detections' => $plugin->detector->detect(),
            'canManage' => Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_MANAGE),
        ]);
    }

    /**
     * Adds everything the detector found and the inventory does not have.
     */
    public function actionAdopt(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE);

        $plugin = Plugin::getInstance();

        if (!Edition::allowsDetection($plugin->isPro())) {
            throw new ForbiddenHttpException(Craft::t('jack', 'Finding undeclared services is a Pro feature.'));
        }

        $keys = $this->request->getBodyParam('serviceKeys', []);
        $added = 0;

        foreach ((array)$keys as $key) {
            if ($plugin->inventory->add((string)$key)) {
                $added++;
            }
        }

        $this->setSuccessFlash(Craft::t('jack', '{n, plural, =1{1 service added} other{# services added}}.', ['n' => $added]));

        return $this->redirect('jack/inventory');
    }

    /**
     * Reads a `de` / `en` pair out of the form, dropping it entirely if both are empty.
     *
     * @return array<string, string>|null
     */
    /**
     * A posted text field, trimmed; null when it is empty or not a string.
     */
    private function stringParam(string $name): ?string
    {
        $value = $this->request->getBodyParam($name);

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * A posted link, kept only if it is an http(s) address: it is printed into an href.
     */
    private function webUrl(string $name): ?string
    {
        $value = $this->stringParam($name);

        return $value !== null && Compiler::isWebUrl($value) ? $value : null;
    }

    private function languageMap(string $field): ?array
    {
        $values = $this->request->getBodyParam($field, []);

        if (!is_array($values)) {
            return null;
        }

        $map = array_filter(
            array_map(static fn($value) => is_string($value) ? trim($value) : '', $values),
            static fn($value) => $value !== '',
        );

        return $map === [] ? null : $map;
    }
}
