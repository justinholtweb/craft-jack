<?php

namespace justinholtweb\jack\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use justinholtweb\jack\models\Edition;
use justinholtweb\jack\Plugin;
use justinholtweb\jack\services\ConsentExport;
use yii\console\Exception;
use yii\console\ExitCode;

/**
 * The detector, from the command line.
 */
class InventoryController extends Controller
{
    /** Add everything found that is not already declared. */
    public bool $adopt = false;

    /** `toss`, `klaro`, `cookieconsent` or `consent-mode`. */
    public string $format = ConsentExport::FORMAT_TOSS;

    /** The site handle to write the config for. Defaults to the primary site. */
    public ?string $site = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), match ($actionID) {
            'detect' => ['adopt'],
            'consent-config' => ['format', 'site'],
            default => [],
        });
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // A refusal has to fail the build. Returning false here exits 0, which would make
        // `check` and `detect` pass on any site whose licence has lapsed.
        if (!Edition::allowsConsole(Plugin::getInstance()->isPro())) {
            throw new Exception('The console commands are a Jack Pro feature.');
        }

        return true;
    }

    /**
     * Finds third-party services on the site and says which are missing from the privacy policy.
     */
    public function actionDetect(): int
    {
        $plugin = Plugin::getInstance();
        $detections = $plugin->detector->detect();

        if ($detections === []) {
            $this->stdout("Nothing found.\n", Console::FG_YELLOW);

            return ExitCode::OK;
        }

        $undeclared = 0;

        foreach ($detections as $detection) {
            $this->stdout(sprintf('%-28s %-14s ', $detection->label, $detection->category));

            if ($detection->declared) {
                $this->stdout("declared\n", Console::FG_GREEN);
                continue;
            }

            $undeclared++;
            $this->stdout("NOT DECLARED\n", Console::FG_RED);

            foreach (array_slice($detection->evidence, 0, 3) as $evidence) {
                $this->stdout(sprintf(
                    "    %s%s\n",
                    $evidence['location'],
                    $evidence['line'] ? ':' . $evidence['line'] : '',
                ), Console::FG_GREY);
            }
        }

        if ($undeclared === 0) {
            return ExitCode::OK;
        }

        if ($this->adopt) {
            foreach ($detections as $detection) {
                if (!$detection->declared) {
                    $plugin->inventory->add($detection->serviceKey);
                }
            }

            $this->stdout("\nAdded $undeclared service(s) to the inventory.\n", Console::FG_GREEN);

            return ExitCode::OK;
        }

        $this->stderr("\n$undeclared service(s) are on the site but not in the privacy policy.\n", Console::FG_RED);

        return ExitCode::DATAERR;
    }

    /**
     * Lists the inventory.
     */
    public function actionList(): int
    {
        foreach (Plugin::getInstance()->inventory->all() as $entry) {
            $this->stdout(sprintf(
                "%-22s %-14s %-12s %-10s %s\n",
                $entry->serviceKey,
                $entry->category(),
                $entry->legalBasis(),
                $entry->transfer(),
                $entry->enabled ? 'on' : 'off',
            ));
        }

        return ExitCode::OK;
    }

    /**
     * Prints the inventory as a consent manager's config, as JSON — redirect it into a file to
     * commit next to the banner.
     *
     * php craft jack/inventory/consent-config --format=klaro --site=default > web/klaro-config.json
     */
    public function actionConsentConfig(): int
    {
        if (!in_array($this->format, ConsentExport::FORMATS, true)) {
            $this->stderr(sprintf("Unknown format “%s”. Use one of: %s.\n", $this->format, implode(', ', ConsentExport::FORMATS)), Console::FG_RED);

            return ExitCode::USAGE;
        }

        $sites = Craft::$app->getSites();
        $site = $this->site !== null ? $sites->getSiteByHandle($this->site) : $sites->getPrimarySite();

        if (!$site) {
            $this->stderr("No site with the handle “{$this->site}”.\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        // No separate edition check: every console command is Pro already (see beforeAction()).
        $this->stdout(Plugin::getInstance()->consentExport->json($this->format, $site->id));

        return ExitCode::OK;
    }
}
