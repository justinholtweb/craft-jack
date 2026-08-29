<?php

namespace justinholtweb\jack\controllers;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\models\Edition;
use justinholtweb\jack\Plugin;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The document screens, plus the front-end route that renders one.
 */
class DocumentsController extends Controller
{
    protected array|bool|int $allowAnonymous = ['render'];

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if ($action->id !== 'render') {
            $this->requirePermission(Plugin::PERMISSION_VIEW);
        }

        return true;
    }

    public function actionIndex(): Response
    {
        $plugin = Plugin::getInstance();
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        return $this->renderTemplate('jack/documents/_index', [
            'canCreate' => Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_MANAGE),
            'missingTypes' => $plugin->documents->missingTypes($siteId),
            'typeLabels' => Document::typeLabels(),
            'availableTypes' => Edition::documentTypes($plugin->isPro()),
            'isPro' => $plugin->isPro(),
        ]);
    }

    /**
     * The "which document do you need" screen, and the thing that creates it.
     */
    public function actionNew(): Response
    {
        $this->requirePermission(Plugin::PERMISSION_MANAGE);
        $plugin = Plugin::getInstance();

        if (!Craft::$app->getRequest()->getIsPost()) {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;

            return $this->renderTemplate('jack/documents/_new', [
                'typeLabels' => Document::typeLabels(),
                'availableTypes' => Edition::documentTypes($plugin->isPro()),
                'missingTypes' => $plugin->documents->missingTypes($siteId),
                'isPro' => $plugin->isPro(),
            ]);
        }

        $this->requirePostRequest();
        $documentType = $this->request->getRequiredBodyParam('documentType');

        if (!Edition::allowsDocumentType($documentType, $plugin->isPro())) {
            throw new ForbiddenHttpException(Craft::t('jack', 'Jack Lite creates an imprint and a privacy policy. The other document types are a Pro feature.'));
        }

        $siteId = Edition::allowsMultiSite($plugin->isPro())
            ? (int)($this->request->getBodyParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id)
            : Craft::$app->getSites()->getPrimarySite()->id;

        $document = $plugin->documents->create($documentType, $siteId);

        if (!$document) {
            $this->setFailFlash(Craft::t('jack', 'Couldn’t create the document.'));

            return $this->redirect('jack/documents');
        }

        $this->setSuccessFlash(Craft::t('jack', 'Document created.'));

        return $this->redirect($document->getCpEditUrl());
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionEdit(int $documentId): Response
    {
        $plugin = Plugin::getInstance();
        $siteId = $this->request->getParam('siteId');
        $document = Document::find()->id($documentId)->siteId($siteId)->status(null)->one();

        if (!$document) {
            throw new NotFoundHttpException('Document not found');
        }

        $compiled = $document->wouldCompile();

        return $this->renderTemplate('jack/documents/_edit', [
            'document' => $document,
            'compiled' => $compiled,
            'drift' => $document->getDrift(),
            'isStale' => $document->isStale(),
            'overrides' => $document->getOverrides(),
            'library' => $plugin->library,
            'facts' => $plugin->library->facts(),
            'isPro' => $plugin->isPro(),
            'canOverride' => Edition::allowsOverrides($plugin->isPro()),
            'canPublish' => Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_PUBLISH),
            'canManage' => Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_MANAGE),
            'sites' => Edition::allowsMultiSite($plugin->isPro())
                ? Craft::$app->getSites()->getAllSites()
                : [Craft::$app->getSites()->getPrimarySite()],
            'versionCount' => count($plugin->documents->versions($document->id, $document->siteId, 500)),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE);

        $plugin = Plugin::getInstance();
        $documentId = (int)$this->request->getRequiredBodyParam('documentId');
        $siteId = (int)$this->request->getRequiredBodyParam('siteId');
        $document = Document::find()->id($documentId)->siteId($siteId)->status(null)->one();

        if (!$document) {
            throw new NotFoundHttpException('Document not found');
        }

        $document->title = $this->request->getBodyParam('title', $document->title);
        $document->slug = $this->request->getBodyParam('slug', $document->slug);
        $document->enabled = (bool)$this->request->getBodyParam('enabled', true);
        $document->jurisdiction = $this->request->getBodyParam('jurisdiction', $document->jurisdiction);

        if (Edition::allowsOverrides($plugin->isPro())) {
            $document->overrides = Json::encode($this->collectOverrides());
        }

        if (!Craft::$app->getElements()->saveElement($document)) {
            $this->setFailFlash(Craft::t('jack', 'Couldn’t save the document.'));
            Craft::$app->getUrlManager()->setRouteParams(['document' => $document]);

            return null;
        }

        // Saving always recompiles. An override that is stored but not applied is the worst of
        // both worlds: the screen says one thing and the page says another.
        $plugin->documents->compile($document);

        $this->setSuccessFlash(Craft::t('jack', 'Document saved.'));

        return $this->redirectToPostedUrl($document);
    }

    public function actionCompile(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE);

        $documentId = (int)$this->request->getRequiredBodyParam('documentId');
        $siteId = (int)$this->request->getBodyParam('siteId', Craft::$app->getSites()->getCurrentSite()->id);
        $document = Document::find()->id($documentId)->siteId($siteId)->status(null)->one();

        if (!$document) {
            throw new NotFoundHttpException('Document not found');
        }

        Plugin::getInstance()->documents->compile($document);

        if ($this->request->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        $this->setSuccessFlash(Craft::t('jack', 'Document recompiled.'));

        return $this->redirect($document->getCpEditUrl());
    }

    public function actionPublish(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_PUBLISH);

        $documentId = (int)$this->request->getRequiredBodyParam('documentId');
        $siteId = (int)$this->request->getBodyParam('siteId', Craft::$app->getSites()->getCurrentSite()->id);
        $document = Document::find()->id($documentId)->siteId($siteId)->status(null)->one();

        if (!$document) {
            throw new NotFoundHttpException('Document not found');
        }

        $plugin = Plugin::getInstance();

        // Publish what is current, not what was compiled a fortnight ago.
        $plugin->documents->compile($document);
        $published = $plugin->documents->publish($document, $this->request->getBodyParam('note'));

        if (!$published) {
            $this->setFailFlash(Craft::t('jack', 'Couldn’t publish — the document has no text.'));

            return $this->redirect($document->getCpEditUrl());
        }

        $this->setSuccessFlash(Craft::t('jack', 'Version published.'));

        return $this->redirect($document->getCpEditUrl());
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE);

        $documentId = (int)$this->request->getRequiredBodyParam('documentId');
        $document = Document::find()->id($documentId)->status(null)->one();

        if ($document) {
            Craft::$app->getElements()->deleteElement($document);
        }

        $this->setSuccessFlash(Craft::t('jack', 'Document deleted.'));

        return $this->redirect('jack/documents');
    }

    public function actionHistory(int $documentId): Response
    {
        $plugin = Plugin::getInstance();

        if (!Edition::allowsHistory($plugin->isPro())) {
            throw new ForbiddenHttpException(Craft::t('jack', 'Version history is a Pro feature.'));
        }

        $siteId = (int)($this->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id);
        $document = Document::find()->id($documentId)->siteId($siteId)->status(null)->one();

        if (!$document) {
            throw new NotFoundHttpException('Document not found');
        }

        return $this->renderTemplate('jack/documents/_history', [
            'document' => $document,
            'versions' => $plugin->documents->versions($document->id, $siteId),
        ]);
    }

    public function actionCompare(int $documentId): Response
    {
        $plugin = Plugin::getInstance();

        if (!Edition::allowsHistory($plugin->isPro())) {
            throw new ForbiddenHttpException(Craft::t('jack', 'Comparing versions is a Pro feature.'));
        }

        $siteId = (int)($this->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id);
        $document = Document::find()->id($documentId)->siteId($siteId)->status(null)->one();

        if (!$document) {
            throw new NotFoundHttpException('Document not found');
        }

        $versions = $plugin->documents->versions($document->id, $siteId);
        $fromId = (int)$this->request->getParam('from');
        $toId = (int)$this->request->getParam('to');

        $from = $fromId ? $plugin->documents->version($fromId) : ($versions[1] ?? null);
        $to = $toId ? $plugin->documents->version($toId) : ($versions[0] ?? null);

        // "Compare with what it says now" is the comparison people actually want, so the current
        // compiled text stands in whenever there is nothing newer to compare against.
        $after = $to?->html ?? (string)$document->compiledHtml;
        $before = $from?->html ?? '';

        return $this->renderTemplate('jack/documents/_compare', [
            'document' => $document,
            'versions' => $versions,
            'from' => $from,
            'to' => $to,
            'rows' => $plugin->documents->diff($before, $after),
        ]);
    }

    /**
     * The front end, when no template has been configured.
     */
    public function actionRender(int $documentId, ?int $siteId = null): Response
    {
        $document = Document::find()->id($documentId)->siteId($siteId)->one();

        if (!$document) {
            throw new NotFoundHttpException('Document not found');
        }

        return $this->renderTemplate('jack/_render/document', [
            'document' => $document,
            'body' => Plugin::getInstance()->renderer->render($document, ['toc' => true]),
        ], \craft\web\View::TEMPLATE_MODE_CP);
    }

    /**
     * Pulls the per-clause overrides out of the posted form.
     *
     * Empty overrides are dropped rather than stored blank, so that "this clause has been edited"
     * stays a question the screen can answer by looking at the data.
     *
     * @return array<string, mixed>
     */
    private function collectOverrides(): array
    {
        $posted = $this->request->getBodyParam('overrides', []);
        $overrides = [];

        if (!is_array($posted)) {
            return $overrides;
        }

        foreach ($posted as $key => $values) {
            if (!is_array($values)) {
                continue;
            }

            $entry = array_filter([
                'heading' => trim((string)($values['heading'] ?? '')),
                'body' => trim((string)($values['body'] ?? '')),
            ], static fn($value) => $value !== '');

            if (!empty($values['disabled'])) {
                $entry['disabled'] = true;
            }

            if (isset($values['order']) && $values['order'] !== '') {
                $entry['order'] = (int)$values['order'];
            }

            if ($entry !== []) {
                $overrides[(string)$key] = $entry;
            }
        }

        return $overrides;
    }
}
