<?php

namespace justinholtweb\jack\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\FileHelper;
use justinholtweb\jack\models\Detection;
use justinholtweb\jack\Plugin;
use Throwable;

/**
 * Finds the third parties a site actually talks to, and compares them with what it admits to.
 *
 * This is the feature that makes the difference between a privacy policy and an accurate one. The
 * usual failure is not dishonesty — it is that a developer embedded a YouTube video in March and
 * the policy was written in January. Jack reads the templates, the installed plugins and the
 * environment, and says: this is on your site, and your privacy policy has never heard of it.
 *
 * Evidence, not accusation. Every detection carries the file and line it came from, because a
 * finding somebody cannot check is a finding somebody will ignore.
 */
class Detector extends Component
{
    /**
     * @return Detection[] keyed by service key, most evidence first
     */
    public function detect(): array
    {
        $detections = [];

        foreach ([$this->scanFiles(), $this->scanPlugins(), $this->scanEnvironment()] as $found) {
            foreach ($found as $key => $detection) {
                if (isset($detections[$key])) {
                    $detections[$key]->evidence = array_merge($detections[$key]->evidence, $detection->evidence);
                    continue;
                }

                $detections[$key] = $detection;
            }
        }

        $inventory = Plugin::getInstance()->inventory;

        foreach ($detections as $detection) {
            $detection->declared = $inventory->isEnabled($detection->serviceKey);
        }

        uasort($detections, static fn(Detection $a, Detection $b) => [$a->declared, -$a->evidenceCount()] <=> [$b->declared, -$b->evidenceCount()]);

        return $detections;
    }

    /**
     * Detected services that are not in the inventory — the actionable half.
     *
     * @return Detection[]
     */
    public function undeclared(): array
    {
        return array_filter($this->detect(), static fn(Detection $detection) => !$detection->declared);
    }

    /**
     * @return Detection[]
     */
    private function scanFiles(): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $patterns = $this->patterns();
        $detections = [];
        $maxBytes = $settings->detectorMaxFileSize * 1024;

        foreach ($this->files() as $file) {
            try {
                if (filesize($file) > $maxBytes) {
                    continue;
                }

                $contents = file_get_contents($file);
            } catch (Throwable) {
                continue;
            }

            if ($contents === false || $contents === '') {
                continue;
            }

            $relative = $this->relativePath($file);

            foreach ($patterns as $serviceKey => $servicePatterns) {
                foreach ($servicePatterns as $pattern) {
                    if (!preg_match('~' . $pattern . '~i', $contents, $match, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }

                    $detections[$serviceKey] ??= $this->detection($serviceKey, Detection::VIA_TEMPLATE);
                    $detections[$serviceKey]->evidence[] = [
                        'location' => $relative,
                        'line' => substr_count(substr($contents, 0, $match[0][1]), "\n") + 1,
                        'excerpt' => $this->excerpt($contents, $match[0][1]),
                    ];

                    // One hit per file per service is enough to make the point, and stops a
                    // minified bundle producing four hundred rows about the same script tag.
                    break;
                }
            }
        }

        return $detections;
    }

    /**
     * @return Detection[]
     */
    private function scanPlugins(): array
    {
        $detections = [];
        $plugins = Craft::$app->getPlugins();

        foreach (Plugin::getInstance()->library->services() as $serviceKey => $definition) {
            foreach ($definition['detect']['plugins'] ?? [] as $handle) {
                if (!$plugins->isPluginEnabled($handle)) {
                    continue;
                }

                $detections[$serviceKey] ??= $this->detection($serviceKey, Detection::VIA_PLUGIN);
                $detections[$serviceKey]->evidence[] = [
                    'location' => $handle,
                    'line' => null,
                    'excerpt' => (string)($plugins->getPluginInfo($handle)['name'] ?? $handle),
                ];
            }
        }

        return $detections;
    }

    /**
     * @return Detection[]
     */
    private function scanEnvironment(): array
    {
        $detections = [];

        foreach (Plugin::getInstance()->library->services() as $serviceKey => $definition) {
            foreach ($definition['detect']['env'] ?? [] as $variable) {
                $value = App::env($variable);

                if ($value === null || $value === '' || $value === false) {
                    continue;
                }

                $detections[$serviceKey] ??= $this->detection($serviceKey, Detection::VIA_ENV);
                $detections[$serviceKey]->evidence[] = [
                    'location' => $variable,
                    'line' => null,
                    // Never the value. It is an API key, and this report gets pasted into tickets.
                    'excerpt' => 'set',
                ];
            }
        }

        return $detections;
    }

    private function detection(string $serviceKey, string $via): Detection
    {
        $definition = Plugin::getInstance()->library->service($serviceKey) ?? [];

        return new Detection([
            'serviceKey' => $serviceKey,
            // The detector reports into the control panel, which is English.
            'label' => Plugin::getInstance()->library->serviceLabel($serviceKey),
            'category' => $definition['category'] ?? 'functional',
            'via' => $via,
        ]);
    }

    /**
     * @return array<string, string[]>
     */
    private function patterns(): array
    {
        $patterns = [];

        foreach (Plugin::getInstance()->library->services() as $serviceKey => $definition) {
            $servicePatterns = $definition['detect']['patterns'] ?? [];

            if ($servicePatterns !== []) {
                $patterns[$serviceKey] = $servicePatterns;
            }
        }

        return $patterns;
    }

    /**
     * @return string[]
     */
    private function files(): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $base = Craft::getAlias('@root') ?: dirname(Craft::$app->getPath()->getConfigPath());
        $files = [];

        foreach ($settings->detectorPaths as $path) {
            $directory = str_starts_with($path, '/') ? $path : $base . DIRECTORY_SEPARATOR . trim($path, '/');

            if (!is_dir($directory)) {
                continue;
            }

            try {
                $files = array_merge($files, FileHelper::findFiles($directory, [
                    'only' => array_map(static fn($extension) => '*.' . ltrim($extension, '.'), $settings->detectorExtensions),
                    'except' => ['/node_modules/', '/vendor/', '/.git/'],
                ]));
            } catch (Throwable $e) {
                Craft::warning('Jack could not read ' . $directory . ': ' . $e->getMessage(), Plugin::LOG_CATEGORY);
            }
        }

        return $files;
    }

    private function relativePath(string $file): string
    {
        $base = Craft::getAlias('@root') ?: '';

        return $base && str_starts_with($file, $base) ? ltrim(substr($file, strlen($base)), '/') : $file;
    }

    private function excerpt(string $contents, int $offset): string
    {
        $start = max(0, $offset - 40);
        $excerpt = substr($contents, $start, 140);
        $excerpt = preg_replace('~\s+~', ' ', $excerpt) ?? $excerpt;

        return trim($excerpt);
    }
}
