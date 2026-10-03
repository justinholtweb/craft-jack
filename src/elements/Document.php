<?php

namespace justinholtweb\jack\elements;

use Craft;
use craft\base\Element;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\CpScreenResponseBehavior;
use DateTime;
use justinholtweb\jack\elements\db\DocumentQuery;
use justinholtweb\jack\models\CompiledDocument;
use justinholtweb\jack\Plugin;
use justinholtweb\jack\records\DocumentRecord;
use justinholtweb\jack\records\DocumentSiteRecord;
use yii\web\Response;

/**
 * A legal document — an imprint, a privacy policy, terms.
 *
 * Documents are elements for three reasons and no others: they need a URL per site, they need to
 * exist in more than one language, and people expect to find them in a CP index. Everything that
 * makes a document *say* something lives elsewhere — the profile, the inventory and the library —
 * and the element is where the result comes to rest.
 *
 * The split between the two tables matters. `jack_documents` holds what a document *is* (its type,
 * its handle, the jurisdiction it is written for), which is the same on every site.
 * `jack_documentsites` holds what it currently *says*, which is not. Conflating them is how you
 * end up with a German privacy policy on an English page.
 *
 * @property-read string|null $compiledHtml
 * @property-read string|null $publishedHtml
 */
class Document extends Element
{
    public const TYPE_IMPRINT = 'imprint';
    public const TYPE_PRIVACY = 'privacy';
    public const TYPE_COOKIES = 'cookies';
    public const TYPE_TERMS = 'terms';
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_DISCLAIMER = 'disclaimer';
    public const TYPE_ACCESSIBILITY = 'accessibility';
    public const TYPE_SOCIAL = 'social';

    public const TYPES = [
        self::TYPE_IMPRINT,
        self::TYPE_PRIVACY,
        self::TYPE_COOKIES,
        self::TYPE_TERMS,
        self::TYPE_WITHDRAWAL,
        self::TYPE_DISCLAIMER,
        self::TYPE_ACCESSIBILITY,
        self::TYPE_SOCIAL,
    ];

    public string $documentType = self::TYPE_IMPRINT;
    public string $handle = '';
    public string $jurisdiction = 'de';

    // Per-site, loaded from jack_documentsites for whichever site this element was fetched for.
    public ?string $compiledHtml = null;
    public ?string $publishedHtml = null;
    public ?string $compiledText = null;
    public ?string $stamp = null;
    public ?string $overrides = null;
    public ?string $warnings = null;
    public ?string $missingFacts = null;
    /**
     * Real dates, not the naive UTC strings the row holds: read back with `new DateTime()` (or
     * Twig's `|date`) a stored string is taken as system time, and every save moved the stamp
     * by the site's offset.
     */
    public DateTime|string|null $compiledAt = null;
    public DateTime|string|null $publishedAt = null;

    /** @var array{changed: string[], added: string[], removed: string[]}|null */
    private ?array $drift = null;

    private ?CompiledDocument $compiled = null;

    public function init(): void
    {
        parent::init();

        // Rows from jack_documentsites arrive as naive UTC strings.
        $this->compiledAt = DateTimeHelper::toDateTime($this->compiledAt) ?: null;
        $this->publishedAt = DateTimeHelper::toDateTime($this->publishedAt) ?: null;
    }

    // -----------------------------------------------------------------------------------------
    // Element plumbing
    // -----------------------------------------------------------------------------------------

    public static function displayName(): string
    {
        return Craft::t('jack', 'Legal document');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('jack', 'Legal documents');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('jack', 'legal document');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('jack', 'legal documents');
    }

    public static function refHandle(): ?string
    {
        // `{jack:privacy:render}` splices a document into a rich-text field, so a footer built in
        // CKEditor can carry the live privacy policy without a template change.
        return 'jack';
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasUris(): bool
    {
        return true;
    }

    public static function isLocalized(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function find(): DocumentQuery
    {
        return new DocumentQuery(static::class);
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_IMPRINT => Craft::t('jack', 'Imprint / legal notice'),
            self::TYPE_PRIVACY => Craft::t('jack', 'Privacy policy'),
            self::TYPE_COOKIES => Craft::t('jack', 'Cookie policy'),
            self::TYPE_TERMS => Craft::t('jack', 'Terms and conditions'),
            self::TYPE_WITHDRAWAL => Craft::t('jack', 'Withdrawal instructions'),
            self::TYPE_DISCLAIMER => Craft::t('jack', 'Disclaimer'),
            self::TYPE_ACCESSIBILITY => Craft::t('jack', 'Accessibility statement'),
            self::TYPE_SOCIAL => Craft::t('jack', 'Social media privacy'),
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->documentType] ?? $this->documentType;
    }

    protected static function defineSources(?string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('jack', 'All documents'),
                'criteria' => [],
                'defaultSort' => ['title', 'asc'],
            ],
        ];

        foreach (self::typeLabels() as $type => $label) {
            $sources[] = [
                'key' => 'type:' . $type,
                'label' => $label,
                'criteria' => ['documentType' => $type],
            ];
        }

        return $sources;
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'documentType' => Craft::t('jack', 'Type'),
            'uri' => Craft::t('app', 'URI'),
            'jurisdiction' => Craft::t('jack', 'Jurisdiction'),
            'health' => Craft::t('jack', 'State'),
            'compiledAt' => Craft::t('jack', 'Compiled'),
            'dateUpdated' => Craft::t('app', 'Date Updated'),
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['documentType', 'uri', 'health', 'compiledAt'];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'documentType' => Craft::t('jack', 'Type'),
            'dateUpdated' => Craft::t('app', 'Date Updated'),
        ];
    }

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'documentType' => Html::encode($this->typeLabel()),
            'jurisdiction' => Html::encode(strtoupper($this->jurisdiction)),
            'health' => $this->healthHtml(),
            'compiledAt' => $this->compiledAt
                ? Craft::$app->getFormatter()->asDatetime($this->compiledAt, 'short')
                : '<span class="light">' . Craft::t('jack', 'never') . '</span>',
            default => parent::attributeHtml($attribute),
        };
    }

    private function healthHtml(): string
    {
        $config = match (true) {
            !$this->compiledAt => ['color' => 'red', 'label' => Craft::t('jack', 'Not compiled')],
            $this->getMissingFacts() !== [] => [
                'color' => 'red',
                'label' => Craft::t('jack', '{n, plural, =1{1 fact missing} other{# facts missing}}', ['n' => count($this->getMissingFacts())]),
            ],
            $this->isStale() => ['color' => 'orange', 'label' => Craft::t('jack', 'Out of date')],
            default => ['color' => 'green', 'label' => Craft::t('jack', 'Current')],
        };

        return Cp::statusLabelHtml($config) ?? '';
    }

    // -----------------------------------------------------------------------------------------
    // Routing
    // -----------------------------------------------------------------------------------------

    public function getUriFormat(): ?string
    {
        if (!Plugin::getInstance()->getSettings()->enableDocumentUrls) {
            return null;
        }

        return '{slug}';
    }

    protected function route(): array|string|null
    {
        if (!Plugin::getInstance()->getSettings()->enableDocumentUrls) {
            return null;
        }

        $template = Plugin::getInstance()->getSettings()->documentTemplate;

        if ($template !== '') {
            return [
                'templates/render',
                [
                    'template' => $template,
                    'variables' => ['document' => $this, 'jackDocument' => $this],
                ],
            ];
        }

        // No template configured is still a working page. A legal text is required to be
        // *reachable*; making that wait on somebody writing a Twig file is how sites ship without
        // one.
        return ['jack/documents/render', ['documentId' => $this->id, 'siteId' => $this->siteId]];
    }

    /**
     * Core's getCpEditUrl() wraps this and adds the `site` param, which is what opens the
     * German document on the German row rather than on whichever site the CP is set to.
     */
    protected function cpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('jack/documents/' . $this->id);
    }

    public function prepareEditScreen(Response $response, string $containerId): void
    {
        /** @var CpScreenResponseBehavior $behavior */
        $behavior = $response->getBehavior(CpScreenResponseBehavior::NAME);
        $behavior->crumbs([
            ['label' => Craft::t('jack', 'Documents'), 'url' => UrlHelper::cpUrl('jack/documents')],
        ]);
    }

    // -----------------------------------------------------------------------------------------
    // The compiled body
    // -----------------------------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    public function getStamp(): array
    {
        $decoded = $this->stamp ? Json::decodeIfJson($this->stamp) : [];

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverrides(): array
    {
        $decoded = $this->overrides ? Json::decodeIfJson($this->overrides) : [];

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return string[]
     */
    public function getMissingFacts(): array
    {
        $decoded = $this->missingFacts ? Json::decodeIfJson($this->missingFacts) : [];

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        $decoded = $this->warnings ? Json::decodeIfJson($this->warnings) : [];

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * What differs between what this document says and what it would say if compiled now.
     *
     * Split three ways because the three mean different things to a reader: a *changed* clause is
     * wording they are publishing an old version of, an *added* clause is something their document
     * does not say at all, and a *removed* clause is a paragraph that no longer belongs.
     *
     * Compared against a fresh compilation rather than against the library, which is the only
     * comparison that catches all three causes of drift at once — a clause whose wording was
     * revised, a service added to or dropped from the inventory, and a profile answer that turned
     * a conditional clause on or off.
     *
     * @return array{changed: string[], added: string[], removed: string[]}
     */
    public function getDrift(): array
    {
        if ($this->drift !== null) {
            return $this->drift;
        }

        $stamp = $this->getStamp();

        if ($stamp === [] || !$this->compiledAt) {
            // Never compiled is a different problem, reported differently. Calling it drift would
            // put "out of date" on a document that has never been anything.
            return $this->drift = ['changed' => [], 'added' => [], 'removed' => []];
        }

        $would = $this->wouldCompile()->stamp;
        $changed = [];

        foreach ($stamp as $key => $version) {
            if (isset($would[$key]) && $would[$key] !== $version) {
                $changed[] = $key;
            }
        }

        return $this->drift = [
            'changed' => $changed,
            'added' => array_values(array_diff(array_keys($would), array_keys($stamp))),
            'removed' => array_values(array_diff(array_keys($stamp), array_keys($would))),
        ];
    }

    /**
     * Whether the published text and a fresh compilation have parted company.
     *
     * Compares the bytes, not the stamp. Editing the company address changes no clause version
     * and no clause list, and is exactly the kind of change that must not slip through — the
     * imprint is the document with the address in it.
     */
    public function isStale(): bool
    {
        if (!$this->compiledAt) {
            return false;
        }

        return trim((string)$this->compiledHtml) !== trim($this->wouldCompile()->toHtml());
    }

    /**
     * A fresh compilation of this document, computed at most once per request.
     */
    public function wouldCompile(): CompiledDocument
    {
        return $this->compiled ??= $this->compile();
    }

    public function compile(): CompiledDocument
    {
        return Plugin::getInstance()->compiler->compile(
            $this->documentType,
            $this->jurisdiction,
            $this->siteId,
            $this->languageForSite(),
            $this->getOverrides(),
        );
    }

    /**
     * The rendered document, which is also what `{jack:privacy:render}` resolves to.
     *
     * Craft splices the return value of a reference tag's property into rich text raw, so a
     * footer built in CKEditor can carry the live privacy policy without anybody touching a Twig
     * file — the same trick Legs uses for tables.
     *
     * Published text only, like every other way out of Jack.
     */
    public function getRender(): \Twig\Markup
    {
        return Plugin::getInstance()->renderer->render($this);
    }

    /**
     * The handle, which is what a reference tag names.
     */
    public function getRef(): ?string
    {
        return $this->handle;
    }

    public function languageForSite(): string
    {
        $site = Craft::$app->getSites()->getSiteById($this->siteId ?? Craft::$app->getSites()->getCurrentSite()->id);

        return $site ? strtolower($site->language) : 'en';
    }

    // -----------------------------------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------------------------------

    public function canView(\craft\elements\User $user): bool
    {
        // Parent first, so EVENT_AUTHORIZE_* handlers elsewhere still get a say.
        if (parent::canView($user)) {
            return true;
        }

        return $user->can(Plugin::PERMISSION_VIEW);
    }

    public function canSave(\craft\elements\User $user): bool
    {
        // Parent first, so EVENT_AUTHORIZE_* handlers elsewhere still get a say.
        if (parent::canSave($user)) {
            return true;
        }

        return $user->can(Plugin::PERMISSION_MANAGE);
    }

    public function canDelete(\craft\elements\User $user): bool
    {
        // Parent first, so EVENT_AUTHORIZE_* handlers elsewhere still get a say.
        if (parent::canDelete($user)) {
            return true;
        }

        return $user->can(Plugin::PERMISSION_MANAGE);
    }

    public function canCreateDrafts(\craft\elements\User $user): bool
    {
        return false;
    }

    // -----------------------------------------------------------------------------------------
    // Saving
    // -----------------------------------------------------------------------------------------

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['documentType', 'handle'], 'required'];
        $rules[] = [['documentType'], 'in', 'range' => self::TYPES];
        $rules[] = [['handle'], 'match', 'pattern' => '/^[a-z0-9\-_]+$/', 'message' => Craft::t('jack', 'Handles may contain lowercase letters, numbers, hyphens and underscores.')];
        $rules[] = [['handle'], 'string', 'max' => 64];
        $rules[] = [['jurisdiction'], 'string', 'max' => 8];
        $rules[] = [['handle'], 'unique', 'targetClass' => DocumentRecord::class, 'targetAttribute' => 'handle', 'filter' => fn($query) => $this->id ? $query->andWhere(['not', ['id' => $this->id]]) : $query];

        return $rules;
    }

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            $record = $isNew ? new DocumentRecord(['id' => $this->id]) : DocumentRecord::findOne($this->id);
            $record ??= new DocumentRecord(['id' => $this->id]);
            $record->documentType = $this->documentType;
            $record->handle = $this->handle;
            $record->jurisdiction = $this->jurisdiction;
            $record->save(false);
        }

        // Runs once per site, including while propagating, which is exactly what is wanted: the
        // per-site row is the per-site text.
        $siteRecord = DocumentSiteRecord::findOne(['documentId' => $this->id, 'siteId' => $this->siteId])
            ?? new DocumentSiteRecord(['documentId' => $this->id, 'siteId' => $this->siteId]);

        $siteRecord->compiledHtml = $this->compiledHtml;
        $siteRecord->publishedHtml = $this->publishedHtml;
        $siteRecord->compiledText = $this->compiledText;
        $siteRecord->stamp = $this->stamp;
        $siteRecord->overrides = $this->overrides;
        $siteRecord->warnings = $this->warnings;
        $siteRecord->missingFacts = $this->missingFacts;
        $siteRecord->compiledAt = Db::prepareDateForDb($this->compiledAt);
        $siteRecord->publishedAt = Db::prepareDateForDb($this->publishedAt);
        $siteRecord->save(false);
        $this->drift = null;
        $this->compiled = null;
        Plugin::getInstance()->audit->invalidate();

        parent::afterSave($isNew);
    }

    public function beforeDelete(): bool
    {
        // A deleted document keeps its row in the trash, and its handle with it. Parking both
        // frees the handle and the URI for a replacement, and restoring reclaims them.
        if (!parent::beforeDelete()) {
            return false;
        }

        Craft::$app->getDb()->createCommand()
            ->update('{{%jack_documents}}', ['handle' => $this->handle . '--trashed-' . $this->id], ['id' => $this->id])
            ->execute();

        return true;
    }

    public function afterRestore(): void
    {
        $handle = preg_replace('/--trashed-\d+$/', '', $this->handle);

        if ($handle !== $this->handle && !DocumentRecord::find()->where(['handle' => $handle])->exists()) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%jack_documents}}', ['handle' => $handle], ['id' => $this->id])
                ->execute();
        }

        Plugin::getInstance()->audit->invalidate();
        parent::afterRestore();
    }

    public function afterDelete(): void
    {
        Plugin::getInstance()->audit->invalidate();
        parent::afterDelete();
    }
}
