<?php

namespace Klxm\Fluid;

use Klxm\Fluid\Kit\Fluid\FluidKit;
use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\Content\Article;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use ReflectionClass;

use function array_key_exists;
use function dirname;
use function in_array;
use function is_dir;
use function is_file;
use function Redaxo\Core\View\escape;
use function str_contains;

/**
 * A kit: design (template, CSS, JS) and the block outputs (their fields are defined in the block classes).
 *
 * Structure of a kit (folder, {@see Path()}):
 *   kit.php           definition: label, backgrounds, assets (CSS per block type), blocks (additions per block type, e.g. variants),
 *                     css (more stylesheets for every page), design (style editor)
 *   functions.php     helper functions for the outputs (loaded once)
 *   views/{typ}.php   output per block type (`$b` = block, `$d` = data)
 *   template.view.php page frame (optional, otherwise the parent kit's)
 *   assets/           CSS, JS, images – fluid publishes them to `assets/fluid-kits/{kit}/`
 *
 * Kits are registered with {@see AsFluidKit} and inherit from a parent kit (default: `fluid`). Whatever a kit does not
 * provide itself – kit.php entries, views, template, assets, helper functions – comes from the parent kit. In kit.php
 * its definition is available as `$parent`. Which kit the website uses is set in the website settings
 * ({@see active()}); all block modules appear in the chosen kit, content is kept when switching.
 */
abstract class Kit
{
    /** The base kit all others inherit from */
    public const string DEFAULT = 'fluid';

    /** @var array<class-string<self>, self> */
    private static array $instances = [];

    /** @var array<string, class-string<self>>|null key => class */
    private static ?array $registry = null;

    private static ?string $activeKey = null;

    /** @var array<string, mixed>|null */
    private ?array $def = null;

    private bool $functionsLoaded = false;

    private ?AsFluidKit $attribute = null;

    /** @var array<string, true> Block types used in this request (for the CSS per block) */
    private array $used = [];

    final private function __construct() {}

    final public static function instance(): static
    {
        /** @var static */
        return self::$instances[static::class] ??= new static();
    }

    // ------------------------------------------------------------------ Registration and selection

    /**
     * All registered kits (`#[AsFluidKit]` in the project, in fluid and in Composer packages).
     *
     * @return array<string, self> key => kit, `fluid` first
     */
    public static function all(): array
    {
        if (null === self::$registry) {
            $map = [self::DEFAULT => FluidKit::class];
            foreach (ClassDiscovery::getInstance()->discoverByAttribute(AsFluidKit::class, self::class) as $class => $attribute) {
                $map[$attribute->key] ??= $class;
            }
            self::$registry = $map;
        }
        $out = [];
        foreach (self::$registry as $key => $class) {
            $out[$key] = $class::instance();
        }

        return $out;
    }

    public static function get(string $key): ?self
    {
        return self::all()[$key] ?? null;
    }

    /**
     * The website's kit: website setting „Kit“, adjustable via the extension point FLUID_KIT (e.g. per domain).
     * Unknown keys fall back to `fluid`.
     */
    public static function active(): self
    {
        if (null === self::$activeKey) {
            $key = (string) (Settings::all()['kit'] ?? '');
            $key = (string) Extension::dispatch(new ExtensionPoint('FLUID_KIT', '' !== $key ? $key : self::DEFAULT));
            self::$activeKey = array_key_exists($key, self::all()) ? $key : self::DEFAULT;
        }

        return self::get(self::$activeKey) ?? FluidKit::instance();
    }

    /** Determine the selection again (after saving the setting, in tests) */
    public static function reset(): void
    {
        self::$activeKey = null;
        self::$registry = null;
    }

    /** @return array<string, string> key => name, for the kit selection */
    public static function options(): array
    {
        $out = [];
        foreach (self::all() as $key => $kit) {
            $out[$key] = $kit->label();
        }

        return $out;
    }

    // ------------------------------------------------------------------ Angaben des Kits

    /** Key of the kit, e.g. `fluid` (from `#[AsFluidKit]`) */
    public function key(): string
    {
        return $this->attribute()->key ?? static::class;
    }

    /** Name in the selection: `#[AsFluidKit]`, otherwise `label` from kit.php */
    public function label(): string
    {
        return $this->attribute()->label ?? (string) ($this->definition()['label'] ?? $this->key());
    }

    /** Parent kit (default `fluid`); the kit `fluid` itself has none */
    public function parent(): ?self
    {
        if (self::DEFAULT === $this->key()) {
            return null;
        }
        $parent = self::get($this->attribute()->parent ?? self::DEFAULT) ?? FluidKit::instance();

        // no loops: a kit that (indirectly) inherits from itself is attached directly to fluid
        $seen = [];
        for ($p = $parent; null !== $p && self::DEFAULT !== $p->key(); $p = $p->parent()) {
            if ($p === $this || in_array($p, $seen, true)) {
                return FluidKit::instance();
            }
            $seen[] = $p;
        }

        return $parent;
    }

    /**
     * The kit and its ancestors, the kit itself first.
     *
     * @return list<self>
     */
    final public function lineage(): array
    {
        $out = [];
        for ($k = $this; null !== $k && !in_array($k, $out, true); $k = $k->parent()) {
            $out[] = $k;
        }

        return $out;
    }

    /** Folder of the kit – default: folder of the kit class */
    public function path(string $file = ''): string
    {
        return dirname((string) new ReflectionClass($this)->getFileName()) . ('' !== $file ? '/' . $file : '');
    }

    /** File of the kit or its ancestors (e.g. `views/hero.php`, `template.view.php`); null = none */
    final public function file(string $file): ?string
    {
        foreach ($this->lineage() as $kit) {
            if (is_file($kit->path($file))) {
                return $kit->path($file);
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ Definition (kit.php)

    /** @return array<string, mixed> */
    final public function definition(): array
    {
        if (null === $this->def) {
            $this->loadFunctions();
            $parent = $this->parent()?->definition() ?? [];
            $file = $this->path('kit.php');
            /** @var array<string, mixed> $own */
            $own = is_file($file) ? (static fn (array $parent, string $kitFile): array => (array) require $kitFile)($parent, $file) : [];
            $this->def = [] === $parent ? $own : self::merge($parent, $own);
        }

        return $this->def;
    }

    /**
     * Lay a kit's definition over its parent's: entries replace; blocks, backgrounds, the CSS mapping
     * per block type and the text formats (per group, {@see TextFormats}) are merged per key; `css` (stylesheets for
     * every page) is appended.
     *
     * @param array<string, mixed> $parent
     * @param array<string, mixed> $own
     * @return array<string, mixed>
     */
    private static function merge(array $parent, array $own): array
    {
        $out = array_replace($parent, $own);
        foreach (['backgrounds', 'assets'] as $key) {
            if (isset($parent[$key], $own[$key])) {
                $out[$key] = array_replace((array) $parent[$key], (array) $own[$key]);
            }
        }
        if (isset($parent['blocks'], $own['blocks'])) {
            $out['blocks'] = (array) $parent['blocks'];
            foreach ((array) $own['blocks'] as $type => $block) {
                $out['blocks'][$type] = self::overlayBlock((array) ($parent['blocks'][$type] ?? []), (array) $block);
            }
        }
        if (isset($parent['text'], $own['text'])) {
            $out['text'] = (array) $parent['text'];
            foreach ((array) $own['text'] as $group => $formats) {
                $out['text'][$group] = array_replace((array) ($parent['text'][$group] ?? []), (array) $formats);
            }
        }
        $out['css'] = [...(array) ($parent['css'] ?? []), ...(array) ($own['css'] ?? [])];

        return $out;
    }

    /**
     * The kit's settings for a block type (kit.php 'blocks'), laid over the block class's definition –
     * e.g. additional variants with their own view. Empty = block as defined in its class.
     *
     * @return array<string, mixed>
     */
    final public function block(string $type): array
    {
        /** @var array<string, mixed> */
        return (array) ($this->definition()['blocks'][$type] ?? []);
    }

    /**
     * Lay a kit's settings over a block definition: `variants` and `variant_help` are merged, `fields` appended,
     * everything else replaced.
     *
     * @param array<string, mixed> $def
     * @param array<string, mixed> $overlay
     * @return array<string, mixed>
     */
    public static function overlayBlock(array $def, array $overlay): array
    {
        $out = array_replace($def, $overlay);
        foreach (['variants', 'variant_help'] as $key) {
            if (isset($def[$key], $overlay[$key])) {
                $out[$key] = array_replace((array) $def[$key], (array) $overlay[$key]);
            }
        }
        if (isset($def['fields'], $overlay['fields'])) {
            $out['fields'] = [...(array) $def['fields'], ...(array) $overlay['fields']];
        }

        return $out;
    }

    /** @return list<string> */
    final public function darkBackgrounds(): array
    {
        /** @var list<string> */
        return $this->definition()['dark_backgrounds'] ?? [];
    }

    /** @return array<string, string> */
    final public function backgrounds(): array
    {
        /** @var array<string, string> */
        return $this->definition()['backgrounds'] ?? ['white' => 'Standard'];
    }

    /** View of a block type – from the kit or an ancestor */
    final public function view(string $type): string
    {
        return $this->file('views/' . $type . '.php') ?? $this->path('views/' . $type . '.php');
    }

    // ------------------------------------------------------------------ Assets

    /** URL of an asset (`css/site.css` …) – from the kit or an ancestor, with version tag */
    final public function assetUrl(string $file): string
    {
        foreach ($this->lineage() as $kit) {
            $source = $kit->sourceAsset($file);
            if (is_file($source)) {
                return $kit->publicAssetUrl($file) . '?v=' . substr(md5((string) filemtime($source)), 0, 8);
            }
        }

        return $this->publicAssetUrl($file);
    }

    /** Local source file of an asset (from the kit or an ancestor) */
    final public function assetPath(string $file): string
    {
        foreach ($this->lineage() as $kit) {
            if (is_file($kit->sourceAsset($file))) {
                return $kit->sourceAsset($file);
            }
        }

        return $this->sourceAsset($file);
    }

    /** Source of this kit's assets (without ancestors) */
    protected function sourceAsset(string $file): string
    {
        return $this->path('assets/' . $file);
    }

    /**
     * Public URL of an asset of this kit. Kits outside fluid are published to `assets/fluid-kits/{kit}/` –
     * completely on `console migrate`, otherwise per file as soon as it is missing or has changed.
     */
    protected function publicAssetUrl(string $file): string
    {
        $source = $this->sourceAsset($file);
        $target = Path::assets('fluid-kits/' . $this->key() . '/' . $file);
        if (is_file($source) && (!is_file($target) || filemtime($source) > filemtime($target))) {
            File::copy($source, $target);
        }

        return Url::assets('fluid-kits/' . $this->key() . '/' . $file);
    }

    /** Publish the assets of all kits (on `console migrate`) */
    public static function publishAll(): void
    {
        foreach (self::all() as $kit) {
            $kit->publish();
        }
    }

    protected function publish(): void
    {
        if (is_dir($this->path('assets'))) {
            Dir::copy($this->path('assets'), Path::assets('fluid-kits/' . $this->key()));
        }
    }

    /** Was a block type or key (`@tablesort` …) output in this request? */
    final public function used(string $key): bool
    {
        return isset($this->used[$key]);
    }

    final public function markUsed(string $type, string $variant = ''): void
    {
        $this->used[$type] = true;
        if ('' !== $variant) {
            $this->used[$type . ':' . $variant] = true;
        }
    }

    /**
     * Stylesheets for every page: `css` from kit.php (parent kit first), e.g. the own CSS of a derived kit.
     *
     * @return list<string> URLs
     */
    final public function kitCss(): array
    {
        return array_map($this->assetUrl(...), array_values(array_unique((array) ($this->definition()['css'] ?? []))));
    }

    /**
     * CSS files of the blocks output in this request (mapping `assets` in kit.php, as in Studio).
     * In edit mode all of them, so newly inserted blocks are styled immediately.
     *
     * @return list<string> URLs
     */
    final public function blockCss(bool $all = false): array
    {
        $urls = [];
        /** @var array<string, list<string>> $map */
        $map = $this->definition()['assets'] ?? [];
        foreach ($map as $file => $types) {
            if (!str_ends_with($file, '.css')) {
                continue;
            }
            foreach ($types as $t) {
                if ($all || isset($this->used[$t])) {
                    $urls[] = $this->assetUrl($file);
                    continue 2;
                }
            }
        }

        return $urls;
    }

    /** @return list<string> URLs of the scripts of used blocks */
    final public function blockJs(): array
    {
        $urls = [];
        /** @var array<string, list<string>> $map */
        $map = $this->definition()['assets'] ?? [];
        foreach ($map as $file => $types) {
            if (str_ends_with($file, '.js') && array_filter($types, fn (string $t) => isset($this->used[$t]))) {
                $urls[] = $this->assetUrl($file);
            }
        }

        return $urls;
    }

    /**
     * Reveal effects for sections (kit.php → 'reveal': key => ['label' => …, 'class' => …, 'attr' => …]).
     * Empty = the kit offers none; then „Einblend-Effekt“ is missing under section.
     *
     * @return array<string, array{label: string, class: string, attr?: string}>
     */
    final public function revealOptions(): array
    {
        /** @var array<string, array{label: string, class: string, attr?: string}> */
        return (array) ($this->definition()['reveal'] ?? []);
    }

    /** Style-Editor des Kits (eigene Werte je Kit) */
    /**
     * Head of a page in this kit: title, SEO tags, styles (base, design, used elements), design and scripts – written by
     * {@see KitHead} once the page is rendered. Kits with their own page frame override it (NativeKit: cascade layers).
     */
    public function head(Article $article): string
    {
        $design = $this->design();
        $ncss = self::get('native');
        $prefs = KitHead::visitorPreferences() && null !== $ncss;
        $prefsFonts = $prefs && (bool) Settings::get('visitor_prefs_fonts', true);
        // styles of a derived kit (kit.php → 'css') last, so they can override base and element styles of the parent kit
        $css = [$this->assetUrl('css/site.css'), ...$this->designCss(), ...$this->blockCss(Editing::active()), ...$this->kitCss()];
        if ($prefs) {
            $css = [...$css, $ncss->assetUrl('ncss/components/preferences.css'), ...($prefsFonts ? [$ncss->assetUrl('ncss/components/preferences-fonts.css')] : []), $this->assetUrl('css/prefs.css')];
        }
        $h = self::titleTags($article);
        if ($prefs) {
            $h .= self::script($ncss->assetUrl('ncss/js/preferences-early.js')) . self::script($this->assetUrl('js/prefs-theme.js'));
        }
        $h .= self::stylesheets($css) . $design->head($prefs);
        if ($prefs) {
            $h .= self::script($ncss->assetUrl('ncss/js/preferences.js'), true);
        }
        if (!Editing::active()) {
            if ('morph' === ($design->values()['transition'] ?? '')) {
                $h .= self::script($this->assetUrl('js/vt.js'));
            }
            $h .= self::script($this->assetUrl('js/site.js'), true);
            foreach ($this->blockJs() as $js) {
                $h .= self::script($js, true);
            }
            // scripts of NativeCSS (without dependencies) that this kit uses too – only on pages with these features
            foreach (['@tablesort' => 'ncss/js/table-sort.js', '@filter' => 'ncss/js/filter.js'] as $key => $file) {
                if ($this->used($key) && null !== $ncss) {
                    $h .= self::script($ncss->assetUrl($file), true);
                }
            }
        }

        return $h;
    }

    /** Class of this kit on <html> (e.g. kit-native), empty by default */
    public function htmlClass(): string
    {
        return '';
    }

    /** <title>, color scheme and SEO tags (canonical, hreflang, Open Graph, JSON-LD) */
    final protected static function titleTags(Article $article): string
    {
        return '<title>' . escape(PageSettings::title($article)) . '</title>' . "\n"
            . '<meta name="color-scheme" content="light dark">' . "\n"
            . PageSettings::head($article);
    }

    /** @param list<string> $hrefs */
    final protected static function stylesheets(array $hrefs): string
    {
        $h = '';
        foreach ($hrefs as $href) {
            $h .= '<link rel="stylesheet" href="' . escape($href) . '"' . (str_contains($href, '/opt-') ? ' data-fluid-opt' : '') . '>' . "\n";
        }

        return $h;
    }

    final protected static function script(string $src, bool $defer = false): string
    {
        return '<script src="' . escape($src) . '"' . ($defer ? ' defer' : '') . '></script>' . "\n";
    }

    final public function design(): Design
    {
        return Design::for($this);
    }

    /**
     * Stylesheets of the chosen design options (kit.php → 'design_css': css/opt-{option}-{wert}.css, if present).
     *
     * @return list<string> URLs
     */
    final public function designCss(?array $values = null): array
    {
        $design = $this->design();
        $values ??= $design->values();
        $out = [];
        foreach ((array) ($this->definition()['design_css'] ?? []) as $opt) {
            $file = 'css/opt-' . $opt . '-' . preg_replace('~[^a-z]~', '', (string) ($values[$opt] ?? '')) . '.css';
            if (is_file($this->assetPath($file))) {
                $out[] = $this->assetUrl($file);
            }
        }

        return $out;
    }

    final public function isDark(string $background): bool
    {
        return in_array($background, $this->darkBackgrounds(), true);
    }

    private function attribute(): ?AsFluidKit
    {
        if (null === $this->attribute) {
            $attributes = new ReflectionClass($this)->getAttributes(AsFluidKit::class);
            $this->attribute = isset($attributes[0]) ? $attributes[0]->newInstance() : null;
        }

        return $this->attribute;
    }

    /** Load helper functions – ancestors' first, so a kit can use them */
    private function loadFunctions(): void
    {
        if ($this->functionsLoaded) {
            return;
        }
        $this->functionsLoaded = true;
        $this->parent()?->loadFunctions();
        if (is_file($this->path('functions.php'))) {
            require_once $this->path('functions.php');
        }
    }
}
