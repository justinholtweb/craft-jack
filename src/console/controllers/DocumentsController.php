<?php

namespace justinholtweb\jack\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\models\Edition;
use justinholtweb\jack\models\Finding;
use justinholtweb\jack\Plugin;
use yii\console\ExitCode;

/**
 * Compiling and checking documents from the command line.
 *
 * `check` is the reason this exists. A legal text that quietly stopped being true is not something
 * anybody notices by looking at the site, so it belongs in the same place as the tests: run it in
 * CI, and a deploy that would have published a privacy policy missing a processor fails instead.
 */
class DocumentsController extends Controller
{
    /** Fail the check on warnings as well as critical findings. */
    public bool $strict = false;

    /** Only this site. */
    public ?string $site = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), match ($actionID) {
            'check' => ['strict', 'site'],
            'compile' => ['site'],
            default => [],
        });
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!Edition::allowsConsole(Plugin::getInstance()->isPro())) {
            $this->stderr("The console commands are a Jack Pro feature.\n", Console::FG_YELLOW);

            return false;
        }

        return true;
    }

    /**
     * Recompiles every document.
     */
    public function actionCompile(): int
    {
        $siteId = $this->siteId();
        $count = Plugin::getInstance()->documents->compileAll($siteId);

        $this->stdout("Compiled $count document(s).\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Reports every problem with the site's legal texts, and fails if any are serious.
     */
    public function actionCheck(): int
    {
        $plugin = Plugin::getInstance();
        $siteId = $this->siteId() ?? Craft::$app->getSites()->getPrimarySite()->id;
        $findings = $plugin->audit->run($siteId);

        if ($findings === []) {
            $this->stdout("No problems found.\n", Console::FG_GREEN);

            return ExitCode::OK;
        }

        $failed = 0;

        foreach ($findings as $finding) {
            [$colour, $prefix] = match ($finding->severity) {
                Finding::SEVERITY_CRITICAL => [Console::FG_RED, 'CRITICAL'],
                Finding::SEVERITY_WARNING => [Console::FG_YELLOW, 'WARNING '],
                default => [Console::FG_GREY, 'NOTICE  '],
            };

            $this->stdout($prefix . '  ', $colour);
            $this->stdout($finding->title . "\n", Console::BOLD);
            $this->stdout('          ' . $finding->detail . "\n");
            $this->stdout('          → ' . $finding->remedy . "\n\n", Console::FG_CYAN);

            if ($finding->severity === Finding::SEVERITY_CRITICAL
                || ($this->strict && $finding->severity === Finding::SEVERITY_WARNING)) {
                $failed++;
            }
        }

        if ($failed > 0) {
            $this->stderr("$failed problem(s) need fixing.\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        return ExitCode::OK;
    }

    /**
     * Lists documents and the state they are in.
     */
    public function actionList(): int
    {
        $documents = Document::find()->siteId($this->siteId())->status(null)->all();

        if ($documents === []) {
            $this->stdout("No documents.\n", Console::FG_YELLOW);

            return ExitCode::OK;
        }

        foreach ($documents as $document) {
            $state = match (true) {
                !$document->compiledAt => 'never compiled',
                $document->getMissingFacts() !== [] => count($document->getMissingFacts()) . ' facts missing',
                $document->isStale() => 'out of date',
                default => 'current',
            };

            $this->stdout(sprintf(
                "%-24s %-22s %-18s %s\n",
                $document->handle,
                $document->documentType,
                $document->uri ?? '—',
                $state,
            ));
        }

        return ExitCode::OK;
    }

    private function siteId(): ?int
    {
        if ($this->site === null) {
            return null;
        }

        $site = Craft::$app->getSites()->getSiteByHandle($this->site);

        return $site?->id;
    }
}
