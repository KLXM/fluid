<?php

namespace Klxm\Fluid;

use Klxm\Fluid\Form\FieldForm;
use Klxm\Fluid\Form\Stored;
use Klxm\Fluid\Media\ImagePlaces;
use Override;
use Redaxo\Core\Content\ArticleContentEditor;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ExtensionPoint\SliceOutput;
use Redaxo\Core\Content\ExtensionPoint\SlicePreview;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Form\FormSchema;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;
use ReflectionClass;

use function array_diff_key;
use function array_filter;
use function in_array;
use function is_a;
use function is_array;
use function json_decode;
use function Redaxo\Core\View\escape;

/**
 * Base of fluid's blocks. A block is a core module – fields as attributes on the properties, output in output() –
 * plus what fluid adds: editing directly on the website, section (background, spacing, anchor), variants, layout
 * columns and styling by a kit.
 *
 *     #[AsModule('fluid.hinweis', 'Hinweis')]
 *     #[AsBlock(icon: 'info', nestable: true)]
 *     final class Hinweis extends FluidBlock
 *     {
 *         #[Text('Überschrift', required: true, maxLength: 120)]
 *         public string $headline = '';
 *
 *         #[Choice('Art', choices: ['info' => 'Information', 'warn' => 'Warnung'])]
 *         public string $kind = 'info';
 *
 *         public function output(): string
 *         {
 *             return '<div class="wrap"><p class="note note--' . escape($this->kind) . '"' . $this->edit('headline') . '>'
 *                 . escape($this->headline) . '</p></div>';
 *         }
 *     }
 *
 * The fields are the core's (Redaxo\Core\Form\Field\…) or fluid's fields of the same name with optional additions
 * (Klxm\Fluid\Field\…: width, variants, placeholder …), plus fields that only exist in fluid (RichText, Link,
 * Icon …). Storage works like in the core: each field under its key in the slice's JSON column `data`, plus `variant`
 * (variant) and `tunes` (section).
 *
 * Without its own output() the block renders its kit's view (views/{type}.php with `$b` = block, `$d` = values as
 * array, `$m` = the module). fluid itself wraps the output in the section (extension points SliceOutput/SlicePreview).
 */
abstract class BlockModule extends Module
{
    /** Darstellung (Variante) des Blocks – Auswahl „Darstellung“ im Formular */
    #[Stored]
    public string $variant = '';

    /** @var array<string, mixed> Section options: background, anchor, spacing, column in the layout */
    #[Stored]
    public array $tunes = [];

    /** Block currently being rendered (context for output(): edit(), variant, section) */
    public ?Block $block = null;

    /** @var array<class-string<self>, array<string, mixed>> */
    private static array $definitions = [];

    abstract public static function kit(): Kit;

    /**
     * Block module for the module key, `null` for other modules.
     *
     * @return class-string<self>|null
     */
    public static function classFor(string $key): ?string
    {
        $class = Module::getClass($key);

        return null !== $class && is_a($class, self::class, true) ? $class : null;
    }

    /**
     * All block modules, by module key.
     *
     * @return array<string, class-string<self>>
     */
    public static function all(): array
    {
        return array_filter(Module::getAll(), static fn (string $class) => is_a($class, self::class, true));
    }

    /** Block type in the kit: part of the module key after the last dot (`fluid.media_text` → `media_text`) */
    public static function type(): string
    {
        $key = static::key();
        $pos = strrpos($key, '.');

        return false === $pos ? $key : substr($key, $pos + 1);
    }

    /**
     * Definition of the block: #[AsBlock] on the class and the fields on the properties (Field\BlockSchema), on top of
     * that the kit's settings for the block type (kit.php 'blocks' – e.g. additional variants) and the extension point
     * FLUID_BLOCK_DEFINITION (changing labels …).
     *
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        return self::$definitions[static::class] ??= (array) Extension::dispatch(new ExtensionPoint('FLUID_BLOCK_DEFINITION',
            Kit::overlayBlock(Field\BlockSchema::of(static::class, static::name()), static::kit()->block(static::type())),
            ['type' => static::type(), 'module' => static::class, 'kit' => static::kit()]));
    }

    /** Beschriftung des Blocks (#[AsBlock] label, sonst Name des Moduls) */
    public static function label(): string
    {
        return (string) (static::definition()['label'] ?? static::name());
    }

    /** Required addons available? (#[AsBlock(requires: …)], e.g. 'yform') – otherwise the block is missing from the picker */
    public static function available(): bool
    {
        return match (static::definition()['requires'] ?? null) {
            null => true,
            'yform' => Data\YFormBridge::available(),
            default => false,
        };
    }

    /** @return list<array<string, mixed>> */
    public static function fields(): array
    {
        /** @var list<array<string, mixed>> */
        return static::definition()['fields'] ?? [];
    }

    /** @return array<string, string> */
    public static function variants(): array
    {
        /** @var array<string, string> */
        return static::definition()['variants'] ?? [];
    }

    // ------------------------------------------------------------------ Werte

    /**
     * Stored values of the slice (without section), as in the column `data` – with `variant` and `widths` if set.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return self::valuesOf($this->slice);
    }

    /**
     * Values of the slice with defaults – the views' `$d`.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        // List template (Data\ListTemplates): values of the selected template over the block data – unchanged without template
        return Data\ListTemplates::resolve(static::class, FieldForm::withDefaults(static::fields(), $this->values(), static::variants()));
    }

    /**
     * Values for a slice's column `data` (e.g. ContentHandler::addSlice()): fields by key like in the core, plus
     * `tunes`.
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $tunes
     * @return array<string, mixed>
     */
    public static function store(array $values, array $tunes = []): array
    {
        unset($values['tunes']);

        return $values + ['tunes' => $tunes];
    }

    /**
     * Values and section from the column `data` (direct queries without ArticleSlice).
     *
     * @return array{values: array<string, mixed>, tunes: array<string, mixed>}
     */
    public static function stored(?string $json): array
    {
        return self::split(self::decode($json));
    }

    /**
     * Section options of a slice without creating the module (e.g. for slices of other modules).
     *
     * @return array<string, mixed>
     */
    public static function tunesOf(ArticleSlice $slice): array
    {
        return self::split($slice->data)['tunes'];
    }

    /**
     * Stored values of a slice (without defaults and section) without creating the module.
     *
     * @return array<string, mixed>
     */
    public static function valuesOf(ArticleSlice $slice): array
    {
        return self::split($slice->data)['values'];
    }

    /** Values as JSON for the column `data` (direct updates without handler) */
    public static function encodeStore(array $values, array $tunes = []): ?string
    {
        return ArticleSlice::encodeData(self::store($values, $tunes));
    }

    /**
     * Column `data` → values and section. Also reads the former format `{values: {…}, tunes: {…}}` (before switching
     * to the core's storage – `console fluid:upgrade:slices` converts it).
     *
     * @param array<array-key, mixed> $data
     * @return array{values: array<string, mixed>, tunes: array<string, mixed>}
     */
    private static function split(array $data): array
    {
        $tunes = is_array($data['tunes'] ?? null) ? $data['tunes'] : [];
        if (self::isLegacy($data)) {
            return ['values' => $data['values'], 'tunes' => $tunes];
        }
        unset($data['tunes']);

        return ['values' => $data, 'tunes' => $tunes];
    }

    /** Former storage `{values: {…}, tunes: {…}}`? */
    public static function isLegacy(array $data): bool
    {
        return is_array($data['values'] ?? null) && [] === array_diff_key($data, ['values' => 1, 'tunes' => 1]);
    }

    /** @return array<string, mixed> */
    public static function decode(?string $json): array
    {
        if (null === $json || '' === $json) {
            return [];
        }
        $v = json_decode($json, true);

        return is_array($v) ? $v : [];
    }

    // ------------------------------------------------------------------ Form and output

    #[Override]
    public function input(Form $form): string
    {
        // like in the core: inputs by property (slice[moreLink]), the core's form reads them into the properties
        return FieldForm::render(static::class, $this->values(), $this->tunes, 'slice', dataKey: '', tunesKey: 'tunes', byProp: true);
    }

    /**
     * Content of the block (without section) – like output() of any core module. Without its own method: the kit's
     * view (views/{type}.php). In custom blocks: read properties, `$this->edit('field')` for editing on the page,
     * `$this->context()` for variant and section.
     */
    #[Override]
    public function output(): string
    {
        $b = $this->context();
        // column blocks are rendered by their layout (Layout::render) – not again as a separate slice
        if (null === Block::$rendering && null !== $b->slice && null !== Layout::parentOf($b->slice) && !self::backendPreview()) {
            return '';
        }

        return static::view($b);
    }

    /** Block currently being rendered – for output by the core from the slice */
    final public function context(): Block
    {
        return $this->block ??= $this->block();
    }

    /**
     * Makes a field editable directly on the page in edit mode (attribute on the element).
     * Path with dots for lists: `items.0.title`. Mode: `plain`, `lines` (multi-line), `rich` (formatting).
     */
    final protected function edit(string $path, string $mode = 'plain'): string
    {
        return $this->context()->edit($path, $mode);
    }

    /** The kit's view (views/{type}.php), overridable via FLUID_BLOCK_VIEW */
    public static function viewFile(Block $b): string
    {
        return (string) Extension::dispatch(new ExtensionPoint('FLUID_BLOCK_VIEW', static::kit()->view($b->type), ['type' => $b->type, 'variant' => $b->variant(), 'block' => $b]));
    }

    /** View des Kits ausgeben: `$b` Block, `$d` Werte, `$m` Modul */
    protected function view(Block $b): string
    {
        $outer = Block::$rendering;   // Layout: column blocks are rendered inside its view
        Block::$rendering = $b;
        ImagePlaces::enter($b->data);
        try {
            return (static function (string $__file, Block $b, array $d, BlockModule $m): string {
                ob_start();
                try {
                    require $__file;
                } finally {
                    $html = (string) ob_get_clean();
                }

                // Video links: „ohne Lightbox“ per link field (only if the setting is enabled)
                return VideoLinks::optOut($html, $b->data);
            })(static::viewFile($b), $b, $b->data, $this);
        } finally {
            ImagePlaces::leave();
            Block::$rendering = $outer;
        }
    }

    /** Content of a block via the instance's output() – with the block as context */
    private static function inner(self $m, Block $b): string
    {
        Seo\StructuredData::collect($b);   // schema.org of the blocks (FAQ …) for the page head
        static::kit()->definition();   // also loads the kit's helper functions (functions.php) – for custom blocks too
        $m->block = $b;
        $outer = Block::$rendering;
        Block::$rendering = $b;
        ImagePlaces::enter($b->data);
        try {
            return $m->output();
        } finally {
            ImagePlaces::leave();
            Block::$rendering = $outer;
        }
    }

    /**
     * Instance with values other than the stored ones (preview while editing, blocks from data without slice): filled
     * like in the core, without slice.
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $tunes
     */
    public static function withValues(array $values, array $tunes = []): static
    {
        $m = new ReflectionClass(static::class)->newInstanceWithoutConstructor();
        FormSchema::of(static::class)->hydrate($m, self::store($values, $tunes));

        return $m;
    }

    /**
     * @internal Section around a block's output (frontend). Not in the backend's slice list: there the content is the
     * core's whole panel (header, buttons, preview) – column blocks would otherwise vanish along with the panel.
     */
    #[AsExtension(SliceOutput::class, ExtensionLevel::Early)]
    public static function wrapOutput(SliceOutput $ep): string
    {
        if (null === self::classFor($ep->slice->moduleKey)) {
            return (string) $ep->subject;
        }
        if ($ep->content instanceof ArticleContentEditor) {
            return Backend\SliceGrid::collect($ep) ?? (string) $ep->subject;   // column blocks into the layout's grid
        }

        return self::of($ep->slice)->wrap((string) $ep->subject);
    }

    /** @internal Preview in the backend's slice list: note on the column in the layout */
    #[AsExtension(SlicePreview::class)]
    public static function wrapPreview(SlicePreview $ep): string
    {
        if (null === self::classFor($ep->slice->moduleKey) || null === Layout::parentOf($ep->slice)) {
            return (string) $ep->subject;
        }

        $parent = Layout::parentOf($ep->slice);
        $data = null !== $parent ? self::of($parent)->context()->data : [];

        // Layout offline: its column blocks are gone from the website too (only the layout renders them)
        $inherited = null !== $parent && 1 !== $parent->status && 1 === $ep->slice->status
            ? '<p class="blocks-be-inherited"><i class="rex-icon rex-icon-offline" aria-hidden="true"></i> ' . I18n::msg('fluid_block_offline_by_layout') . '</p>' : '';

        return '<div class="blocks-be-nest' . ('' !== $inherited ? ' is-inherited-offline' : '') . '">' . $inherited . '<p class="blocks-be-col"><span class="blocks-be-col__n">' . I18n::msg('fluid_block_column', Layout::col($ep->slice) . ' / ' . Layout::count($data)) . '</span>'
            . ' ' . I18n::msg('fluid_block_in_layout', Layout::PRESET_LABELS[Layout::preset($data)]) . ($parent ? ' <a href="#slice' . $parent->id . '">' . I18n::msg('fluid_block_to_layout') . '</a>' : '') . '</p>' . $ep->subject . '</div>';
    }

    /** Short label of a slice for overviews: name of the block and – if present – headline or title */
    public static function summary(ArticleSlice $slice): string
    {
        $class = self::classFor($slice->moduleKey);
        if (null === $class) {
            return $slice->moduleKey;
        }
        $data = self::of($slice)->context()->data;
        $title = trim(strip_tags((string) ($data['title'] ?? $data['name'] ?? '')));

        return $class::label() . ('' !== $title ? ' „' . mb_strimwidth((string) preg_replace('~\*([^*]+)\*~u', '$1', $title), 0, 60, '…') . '“' : '');
    }

    /** Output of a slice (output()) as section – column blocks are rendered by their layout */
    private function wrap(string $inner): string
    {
        if (null !== Layout::parentOf($this->slice)) {
            return '';
        }
        $b = $this->context();
        Seo\StructuredData::collect($b);
        static::kit()->markUsed($b->type, $b->variant());

        return $this->section($b, $inner);
    }

    /** Output of a slice as section, optionally with other data (preview while editing) */
    public function renderBlock(?array $data = null, ?array $tunes = null, int $depth = 0): string
    {
        $b = $this->block($data, $tunes, $depth);
        static::kit()->markUsed($b->type, $b->variant());
        $m = null === $data && null === $tunes ? $this : static::withValues($b->data, $b->tunes);

        return $this->section($b, self::inner($m, $b));
    }

    /** Section with background, spacing and the editor's markup; empty for visitors if the content is empty */
    private function section(Block $b, string $inner): string
    {
        if ('' === trim($inner) && !Editing::active()) {
            return '';
        }
        // aria-labelledby only if the headline is actually rendered
        $titled = str_contains($inner, 'id="' . $b->titleId() . '"');

        return '<section id="' . escape($b->domId()) . '" class="' . escape($b->sectionClass()) . '"'
            . ($titled ? ' aria-labelledby="' . escape($b->titleId()) . '"' : '') . $b->revealAttrs() . $this->editAttrs($b->type) . '>'
            . $b->sectionBg() . $inner . "</section>\n";
    }

    /** Preview in the backend's slice list (not for the cache – that is built in the frontend) */
    public static function backendPreview(): bool
    {
        return Core::isBackend() && str_starts_with(Request::get('page', 'string'), 'content');
    }

    /** May the block (in this variant) sit in a layout column? (#[AsBlock(nestable: true | ['variante', …])]) */
    public static function nestable(string $variant = ''): bool
    {
        $n = static::definition()['nestable'] ?? false;

        return true === $n || (is_array($n) && ('' === $variant || in_array($variant, $n, true)));
    }

    /** Attributes for the editor (edit mode only) */
    protected function editAttrs(string $type, ?int $col = null): string
    {
        if (!Editing::active()) {
            return '';
        }
        $slice = $this->slice;

        return ' data-block="' . $slice->id . '" data-type="' . escape($type) . '" data-label="' . escape(static::label()) . '"'
            . (null !== $col ? ' data-col="' . $col . '"' : '')
            . ($this instanceof LayoutBlock ? ' data-layout' : '')
            . (1 !== $slice->status ? ' data-offline' : '')
            // Lock and module permissions (Lock, Perm): toolbar and sidebar only show what is allowed
            . (Lock::locked($this->tunes) ? ' data-locked' : '')
            . ('' !== ($ro = Lock::reason(Editing::user(), $slice)) ? ' data-readonly="' . $ro . '"' : '');
    }

    /**
     * Block in a layout column: without its own section, background like the layout – its own, different background
     * turns it into a card (classes sec + bg-*, as in Studio).
     */
    public function renderChild(string $parentBg, ?array $data = null, ?array $tunes = null): string
    {
        $t = $tunes ?? $this->tunes;
        $own = (string) ($t['background'] ?? '');
        $card = '' !== $own && $own !== $parentBg && isset(static::kit()->backgrounds()[$own]);
        $t['background'] = $card ? $own : $parentBg;
        $b = $this->block($data, $t, 6);
        static::kit()->markUsed($b->type, $b->variant());
        $m = null === $data && null === $tunes ? $this : static::withValues($b->data, $b->tunes);
        $inner = self::inner($m, $b);
        if ('' === trim($inner) && !Editing::active()) {
            return '';
        }
        $cls = ['lay-item', 'sec--' . str_replace('_', '-', $b->type)];
        if ('' !== $b->variant()) {
            $cls[] = 'v-' . $b->variant();
        }
        if ($card) {
            array_push($cls, 'sec', 'lay-item--card', 'bg-' . $own);
        }
        $anchor = '' !== (string) ($t['anchor'] ?? '') ? ' id="' . escape($b->domId()) . '"' : '';
        $invalid = !static::nestable($b->variant()) && Editing::active()
            ? '<p class="blocks-invalid">' . I18n::msg('fluid_block_not_nestable', static::label()) . '</p>'
            : '';

        return '<div' . $anchor . ' class="' . escape(implode(' ', $cls)) . '"' . $this->editAttrs($b->type, Layout::col($this->slice)) . '>' . $invalid . $inner . "</div>\n";
    }

    /** Block from data (without slice) – e.g. YForm field „Fluid-Blöcke“, see BlockData */
    public static function blockFromData(string $id, array $data, array $tunes = [], ?Block $prev = null, int $articleId = 0): Block
    {
        return new Block(null, static::class, Data\ListTemplates::resolve(static::class, FieldForm::withDefaults(static::fields(), $data, static::variants())), $tunes, $prev, $id, $articleId);
    }

    /**
     * Section of a block from data – like renderBlock(). With $editId (record block, Data\RecordBlocks) and in edit
     * mode with the editor's markup, otherwise without.
     */
    public static function renderData(Block $b, string $editId = ''): string
    {
        static::kit()->markUsed($b->type, $b->variant());
        $inner = self::inner(static::withValues($b->data, $b->tunes), $b);
        if ('' === trim($inner)) {
            return '';
        }
        $titled = str_contains($inner, 'id="' . $b->titleId() . '"');

        $edit = '' !== $editId && Editing::active()
            ? ' data-block="' . escape($editId) . '" data-type="' . escape($b->type) . '" data-label="' . escape(static::label()) . '" data-record'
            : '';

        return '<section id="' . escape($b->domId()) . '" class="' . escape($b->sectionClass()) . '"'
            . ($titled ? ' aria-labelledby="' . escape($b->titleId()) . '"' : '') . $b->revealAttrs() . $edit . '>' . $b->sectionBg() . $inner . "</section>\n";
    }

    /** @internal */
    public function block(?array $data = null, ?array $tunes = null, int $depth = 0): Block
    {
        $prev = null;
        // predecessors (e.g. „Text + Bild abwechselnd“) – only a few levels, no output needs more
        if ($depth < 6 && ($p = $this->slice->getPreviousSlice(!Editing::active())) && null !== self::classFor($p->moduleKey)) {
            // Module::of() of the core, not self::of(): self:: keeps the late static binding, so of() would expect
            // the previous slice to be of this element's class (e.g. Video) and reject any other element
            $previous = Module::of($p);
            $prev = $previous instanceof self ? $previous->block(null, null, $depth + 1) : null;
        }

        return new Block(
            $this->slice,
            static::class,
            null !== $data ? Data\ListTemplates::resolve(static::class, FieldForm::withDefaults(static::fields(), $data, static::variants())) : $this->data(),
            $tunes ?? $this->tunes,
            $prev,
        );
    }
}
