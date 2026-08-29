<?php

namespace justinholtweb\jack\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\jack\models\Finding;
use justinholtweb\jack\Plugin;
use yii\web\Response;

/**
 * The audit screen — everything wrong, worst first.
 */
class AuditController extends Controller
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
        $siteId = (int)($this->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id);
        $findings = $plugin->audit->run($siteId);

        return $this->renderTemplate('jack/audit/_index', [
            'findings' => $findings,
            'siteId' => $siteId,
            'sites' => Craft::$app->getSites()->getAllSites(),
            'counts' => [
                'critical' => count(array_filter($findings, static fn(Finding $f) => $f->severity === Finding::SEVERITY_CRITICAL)),
                'warning' => count(array_filter($findings, static fn(Finding $f) => $f->severity === Finding::SEVERITY_WARNING)),
                'notice' => count(array_filter($findings, static fn(Finding $f) => $f->severity === Finding::SEVERITY_NOTICE)),
            ],
            'libraryVersion' => \justinholtweb\jack\services\Library::VERSION,
            'isPro' => $plugin->isPro(),
        ]);
    }
}
