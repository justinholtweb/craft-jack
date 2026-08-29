<?php

namespace justinholtweb\jack;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\fields\DocumentField;
use justinholtweb\jack\models\Settings;
use justinholtweb\jack\services\Audit;
use justinholtweb\jack\services\Compiler;
use justinholtweb\jack\services\Detector;
use justinholtweb\jack\services\Documents;
use justinholtweb\jack\services\Inventory;
use justinholtweb\jack\services\Library;
use justinholtweb\jack\services\Profiles;
use justinholtweb\jack\services\Renderer;
use justinholtweb\jack\twig\JackVariable;
use yii\base\Event;

/**
 * Jack — legal texts for Craft CMS.
 *
 * The idea the whole plugin is built on is that **a legal document is a projection, not a
 * document**. Nobody should write a privacy policy; they should answer two questions — who are
 * you, and what do you use — and the policy should follow. So Jack keeps:
 *
 * - a {@see Profiles profile}: the facts about the business, once, for every document;
 * - an {@see Inventory inventory}: the third parties this site hands data to;
 * - a {@see Library library} of clauses and processor definitions, shipped inside the plugin and
 *   stamped with a date;
 *
 * and a {@see Compiler} that is a pure function of the three. Because it is pure, the same inputs
 * always give the same bytes, which is what makes the interesting things possible: a document can
 * tell you it has drifted from what it would say now, a diff between two published versions means
 * something, and a CI check can fail a deploy over a privacy policy that stopped being true.
 *
 * What it deliberately is not: a consent banner. Jack's job ends at describing accurately what
 * the site does. Holding a script back until somebody agrees is a different job with a different
 * failure mode, and a plugin that did both badly would be worse than two that each do one.
 *
 * @property-read Library $library
 * @property-read Profiles $profiles
 * @property-read Inventory $inventory
 * @property-read Compiler $compiler
 * @property-read Documents $documents
 * @property-read Detector $detector
 * @property-read Audit $audit
 * @property-read Renderer $renderer
 * @property-read Settings $settings
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public const EDITION_LITE = 'lite';
    public const EDITION_PRO = 'pro';

    public const PERMISSION_VIEW = 'jack:viewDocuments';
    public const PERMISSION_MANAGE = 'jack:manageDocuments';
    public const PERMISSION_PROFILE = 'jack:manageProfile';
    public const PERMISSION_PUBLISH = 'jack:publishDocuments';

    /** Log category used by everything in the plugin. */
    public const LOG_CATEGORY = 'jack';

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;

    public static function editions(): array
    {
        return [
            self::EDITION_LITE,
            self::EDITION_PRO,
        ];
    }

    public static function config(): array
    {
        return [
            'components' => [
                'library' => Library::class,
                'profiles' => Profiles::class,
                'inventory' => Inventory::class,
                'compiler' => Compiler::class,
                'documents' => Documents::class,
                'detector' => Detector::class,
                'audit' => Audit::class,
                'renderer' => Renderer::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->registerElementTypes();
        $this->registerFieldTypes();
        $this->registerCpRoutes();
        $this->registerPermissions();
        $this->registerTwig();
    }

    /** Whether the Pro feature set is available. Every edition check goes through here. */
    public function isPro(): bool
    {
        return $this->is(self::EDITION_PRO, '>=');
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = Craft::t('jack', 'Jack');

        $item['subnav'] = [
            'documents' => [
                'label' => Craft::t('jack', 'Documents'),
                'url' => 'jack/documents',
            ],
            'profile' => [
                'label' => Craft::t('jack', 'Profile'),
                'url' => 'jack/profile',
            ],
            'inventory' => [
                'label' => Craft::t('jack', 'Inventory'),
                'url' => 'jack/inventory',
            ],
            'audit' => [
                'label' => Craft::t('jack', 'Audit'),
                'url' => 'jack/audit',
            ],
        ];

        if (Craft::$app->getUser()->getIsAdmin()) {
            $item['subnav']['settings'] = [
                'label' => Craft::t('jack', 'Settings'),
                'url' => 'settings/plugins/jack',
            ];
        }

        // A missing imprint is worth interrupting somebody about, and this badge is the only place
        // they will see it without going looking — which is the entire failure mode Jack exists
        // for.
        try {
            $problems = $this->audit->problemCount();

            if ($problems > 0) {
                $item['badgeCount'] = $problems;
            }
        } catch (\Throwable) {
            // Before install, or mid-migration. A nav item is not worth an exception.
        }

        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('jack/settings', [
            'settings' => $this->getSettings(),
            'plugin' => $this,
        ]);
    }

    /**
     * Recompiles when settings that change the output are saved.
     */
    public function afterSaveSettings(): void
    {
        parent::afterSaveSettings();

        $this->documents->onFactsChanged();
    }

    private function registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Document::class;
        });
    }

    private function registerFieldTypes(): void
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = DocumentField::class;
        });
    }

    private function registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules += [
                'jack' => 'jack/documents/index',
                'jack/documents' => 'jack/documents/index',
                'jack/documents/new' => 'jack/documents/new',
                'jack/documents/<documentId:\d+>' => 'jack/documents/edit',
                'jack/documents/<documentId:\d+>/history' => 'jack/documents/history',
                'jack/documents/<documentId:\d+>/compare' => 'jack/documents/compare',
                'jack/profile' => 'jack/profile/edit',
                'jack/profile/<siteHandle:{handle}>' => 'jack/profile/edit',
                'jack/inventory' => 'jack/inventory/index',
                'jack/inventory/detect' => 'jack/inventory/detect',
                'jack/inventory/<serviceKey:[a-z0-9_]+>' => 'jack/inventory/edit',
                'jack/audit' => 'jack/audit/index',
            ];
        });
    }

    private function registerPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $event->permissions[] = [
                'heading' => Craft::t('jack', 'Jack'),
                'permissions' => [
                    self::PERMISSION_VIEW => [
                        'label' => Craft::t('jack', 'View legal documents'),
                        'nested' => [
                            self::PERMISSION_MANAGE => [
                                'label' => Craft::t('jack', 'Create and edit documents, the profile and the inventory'),
                            ],
                            // Separate from editing on purpose: compiling a document is a draft,
                            // publishing it is the site's public legal position, and plenty of
                            // organisations want those to be different people.
                            self::PERMISSION_PUBLISH => [
                                'label' => Craft::t('jack', 'Publish documents'),
                            ],
                            self::PERMISSION_PROFILE => [
                                'label' => Craft::t('jack', 'Edit the company profile'),
                            ],
                        ],
                    ],
                ],
            ];
        });
    }

    private function registerTwig(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $event->sender->set('jack', JackVariable::class);
        });
    }
}
