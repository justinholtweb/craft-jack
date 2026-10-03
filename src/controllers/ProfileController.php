<?php

namespace justinholtweb\jack\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\jack\models\Edition;
use justinholtweb\jack\Plugin;
use justinholtweb\jack\services\Compiler;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The profile screen: the facts, once.
 */
class ProfileController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(Plugin::PERMISSION_VIEW);

        return true;
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionEdit(?string $siteHandle = null): Response
    {
        $plugin = Plugin::getInstance();
        $site = null;

        if ($siteHandle !== null) {
            $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);

            if (!$site) {
                throw new NotFoundHttpException('Site not found');
            }
        }

        $global = $plugin->profiles->getGlobal();
        $override = $site ? $plugin->profiles->getOverride($site->id) : null;

        return $this->renderTemplate('jack/profile/_edit', [
            'site' => $site,
            'sites' => Craft::$app->getSites()->getAllSites(),
            'groups' => $plugin->library->factsByGroup(),
            'groupLabels' => self::groupLabels(),
            // The override screen shows only what this site has answered differently; the global
            // value sits behind each field as its placeholder, so it is obvious what is being
            // departed from.
            'values' => $site ? ($override->facts ?? []) : $global->facts,
            'inherited' => $site ? $plugin->profiles->factsForSite(null) : [],
            'completeness' => $plugin->profiles->completeness($site?->id),
            'canEdit' => Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_PROFILE)
                || Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_MANAGE),
            'multiSite' => Edition::allowsMultiSite($plugin->isPro()) && count(Craft::$app->getSites()->getAllSites()) > 1,
        ]);
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();

        if (!Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_PROFILE)) {
            $this->requirePermission(Plugin::PERMISSION_MANAGE);
        }

        $plugin = Plugin::getInstance();
        $facts = $this->request->getBodyParam('facts', []);
        $facts = is_array($facts) ? $facts : [];

        // An unchecked checkbox posts nothing at all, so booleans have to be read from the hidden
        // companion field rather than from what arrived — otherwise turning something off would
        // silently do nothing.
        foreach ($plugin->library->facts() as $key => $definition) {
            if (($definition['type'] ?? '') === 'bool') {
                $facts[$key] = !empty($facts[$key]);
            }
        }

        $siteId = $this->request->getBodyParam('siteId');

        if ($siteId) {
            // Per-site answers are Pro, and changing one site's answers takes Craft's own
            // permission for that site.
            if (!Edition::allowsMultiSite($plugin->isPro())) {
                throw new ForbiddenHttpException(Craft::t('jack', 'Per-site profiles are a Pro feature.'));
            }

            $site = Craft::$app->getSites()->getSiteById((int)$siteId);

            if (!$site) {
                throw new NotFoundHttpException('Site not found');
            }

            $this->requirePermission('editSite:' . $site->uid);
        }

        // Links that are not http(s) are dropped on save; say which, rather than lose them quietly.
        $rejected = [];

        foreach ($plugin->library->facts() as $key => $definition) {
            $value = $facts[$key] ?? null;

            if (($definition['type'] ?? '') === 'url' && is_string($value) && trim($value) !== '' && !Compiler::isWebUrl($value)) {
                $rejected[] = $definition['label'] ?? $key;
            }
        }

        $saved = $siteId
            ? $plugin->profiles->saveOverride((int)$siteId, $facts)
            : $plugin->profiles->saveGlobal($facts);

        if (!$saved) {
            $this->setFailFlash(Craft::t('jack', 'Couldn’t save the profile.'));
        } elseif ($rejected !== []) {
            $this->setFailFlash(Craft::t('jack', 'Profile saved, except these links, which must start with http:// or https://: {fields}', [
                'fields' => implode(', ', $rejected),
            ]));
        } else {
            $this->setSuccessFlash(Craft::t('jack', 'Profile saved.'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * @return array<string, string>
     */
    public static function groupLabels(): array
    {
        return [
            'company' => Craft::t('jack', 'Company'),
            'contact' => Craft::t('jack', 'Contact'),
            'profession' => Craft::t('jack', 'Profession'),
            'privacy' => Craft::t('jack', 'Data protection'),
            'hosting' => Craft::t('jack', 'Hosting'),
            'site' => Craft::t('jack', 'This site'),
            'terms' => Craft::t('jack', 'Selling'),
            'accessibility' => Craft::t('jack', 'Accessibility'),
        ];
    }
}
