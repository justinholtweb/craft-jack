<?php

namespace justinholtweb\jack\controllers;

use Craft;
use craft\base\Element;
use craft\web\Controller;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * `/jack/documents.json` and `/jack/documents/<handle>.json` — published legal texts as data, for
 * front ends that do not render through Twig.
 *
 * Open to anyone and any origin, because it shows exactly what the documents' own pages show:
 * published text of enabled documents on the site the request resolved to, and nothing a compile
 * has produced since. There is deliberately no parameter for another site, a draft or a version.
 */
class JsonController extends Controller
{
    /** Seconds a client or CDN may keep the answer. Publishing is rare, but it should show. */
    public const MAX_AGE = 300;

    protected array|bool|int $allowAnonymous = true;

    public $enableCsrfValidation = false;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!Plugin::getInstance()->getSettings()->serveDocumentsJson) {
            throw new NotFoundHttpException();
        }

        return true;
    }

    /** Every published document on the current site, without bodies. */
    public function actionIndex(): Response
    {
        $renderer = Plugin::getInstance()->renderer;
        $documents = [];

        foreach ($this->query()->orderBy(['jack_documents.id' => SORT_ASC])->all() as $document) {
            $payload = $renderer->payload($document, false);

            if ($payload !== null) {
                $documents[] = $payload;
            }
        }

        return $this->send(['documents' => $documents]);
    }

    /**
     * One document by handle, or failing that the first of that type — the same lookup as
     * `craft.jack.render('privacy')`.
     *
     * @throws NotFoundHttpException
     */
    public function actionView(string $handle): Response
    {
        $document = $this->query()->handle($handle)->one()
            ?? $this->query()->documentType($handle)->orderBy(['jack_documents.id' => SORT_ASC])->one();

        $payload = $document ? Plugin::getInstance()->renderer->payload($document) : null;

        if ($payload === null) {
            throw new NotFoundHttpException('Document not found');
        }

        return $this->send(['document' => $payload]);
    }

    private function query(): \justinholtweb\jack\elements\db\DocumentQuery
    {
        return Document::find()
            ->siteId(Craft::$app->getSites()->getCurrentSite()->id)
            ->status(Element::STATUS_ENABLED)
            ->published();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(array $payload): Response
    {
        $this->response->getHeaders()
            ->set('Cache-Control', 'public, max-age=' . self::MAX_AGE)
            ->set('Access-Control-Allow-Origin', '*')
            ->set('X-Content-Type-Options', 'nosniff');

        return $this->asJson($payload);
    }
}
