<?php

namespace Klxm\Fluid\Chart;

use Klxm\Fluid\Chart\Attribute\AsChartEngine;
use Klxm\Fluid\Chart\Attribute\AsChartSource;
use Klxm\Fluid\FluidAddon;
use Klxm\Routes\Router;
use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\Language\Language;
use Throwable;

use function array_map;
use function array_slice;
use function array_values;
use function count;
use function function_exists;
use function in_array;
use function is_array;
use function is_numeric;
use function json_encode;
use function max;
use function mb_substr;
use function min;
use function preg_match;
use function preg_split;
use function Redaxo\Core\View\escape;
use function round;
use function sprintf;
use function str_contains;
use function str_replace;
use function strrpos;
use function trim;

use const JSON_HEX_AMP;
use const JSON_HEX_TAG;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Charts with an exchangeable engine (from KLXM Studio – Core\Charts).
 *
 * Callers describe a chart in a simple format of their own – never in a library's format:
 *
 *     echo Chart::render([
 *         'type' => 'bar',                                   // line | area | bar | hbar | pie | donut
 *         'labels' => ['Jan', 'Feb', 'Mär'],
 *         'series' => [['name' => 'Anfragen', 'data' => [12, 19, 15]]],
 *         'description' => 'Anfragen je Monat',              // screen reader (role="img")
 *         'format' => 'number', 'unit' => '', 'stacked' => false, 'height' => 320,
 *     ]);
 *
 * Output: <figure data-chart> with the data as JSON (<script type="application/json"> – not executable), a table
 * („Als Tabelle anzeigen“, also without JavaScript) and the loader assets/js/charts.mjs (once per page). The loader
 * fetches the engine only when in view and passes data and colours (CSS variables --chart-1 … --chart-8 etc.,
 * assets/css/charts.css).
 *
 * Swapping the engine: a class with #[AsChartEngine('name')] implementing ChartEngine, and in Project::configure()
 * `Chart::$config = new ChartConfig(engine: 'name')`. Callers and blocks do not change.
 * Data sources for the block „Diagramm“: #[AsChartSource('key', 'Bezeichnung')] on a static method.
 */
final class Chart
{
    public const array TYPES = ['line', 'area', 'bar', 'hbar', 'pie', 'donut'];
    public const array TYPE_LABELS = ['bar' => 'Balken', 'hbar' => 'Balken waagerecht', 'line' => 'Linie', 'area' => 'Fläche', 'donut' => 'Ring', 'pie' => 'Torte'];
    public const array FORMATS = ['number' => 'Zahl', 'percent' => 'Prozent', 'bytes' => 'Dateigröße', 'duration' => 'Dauer (Sekunden)'];
    public const array HEIGHTS = ['240' => 'Niedrig', '320' => 'Mittel', '440' => 'Hoch'];
    public const array TABLE_VIEWS = ['' => 'Anbieten (aufklappbar)', 'open' => 'Aufgeklappt zeigen', 'none' => 'Nicht anbieten'];

    private const int MAX_LABELS = 1000;
    private const int MAX_SERIES = 12;

    /** Project settings (Project::configure()); null = defaults */
    public static ?ChartConfig $config = null;

    /** @var array<string, ChartEngine>|null */
    private static ?array $engines = null;

    /** @var array<string, array{label: string, handler: callable}>|null */
    private static ?array $sources = null;

    private static bool $assets = false;

    public static function config(): ChartConfig
    {
        return self::$config ??= new ChartConfig();
    }

    /** @return array<string, ChartEngine> Name => Engine (per #[AsChartEngine]) */
    public static function engines(): array
    {
        if (null === self::$engines) {
            self::$engines = ['echarts' => new EchartsEngine()];
            try {
                foreach (ClassDiscovery::getInstance()->discoverByAttribute(AsChartEngine::class, ChartEngine::class) as $class => $attribute) {
                    /** @var AsChartEngine $attribute */
                    self::$engines[$attribute->name] = new $class();
                }
            } catch (Throwable) {
                // Class discovery not available (setup)
            }
        }

        return self::$engines;
    }

    /** Chosen engine (configuration, else echarts) */
    public static function engine(): ChartEngine
    {
        $all = self::engines();

        return $all[self::config()->engine] ?? $all['echarts'];
    }

    /** @return array<string, array{label: string, handler: callable}> */
    private static function allSources(): array
    {
        if (null === self::$sources) {
            self::$sources = [];
            try {
                foreach (ClassDiscovery::getInstance()->discoverByMethodAttribute(AsChartSource::class) as $e) {
                    if (!$e['isStatic']) {
                        continue;
                    }
                    /** @var AsChartSource $a */
                    $a = $e['attribute'];
                    self::$sources[$a->key] = ['label' => $a->label, 'handler' => [$e['class'], $e['method']]];
                }
            } catch (Throwable) {
                // Class discovery not available (setup)
            }
        }

        return self::$sources;
    }

    /** @return array<string, string> choice „Daten“ in the block: manual + registered sources */
    public static function sourceChoices(): array
    {
        return ['' => 'Werte von Hand eingeben'] + array_map(static fn (array $s): string => $s['label'], self::allSources());
    }

    /**
     * Description from a registered source (empty if it does not exist or fails).
     *
     * @return array<string, mixed>
     */
    public static function fromSource(string $key): array
    {
        $source = self::allSources()[$key] ?? null;
        if (null === $source) {
            return [];
        }
        try {
            return (array) ($source['handler'])();
        } catch (Throwable $e) {
            error_log('[fluid charts] Quelle ' . $key . ': ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Labels (one per line) and values (separated by ; or line break) from input.
     *
     * @param list<array{name?: string, values?: string}> $series
     * @return array{labels: list<string>, series: list<array{name: string, data: list<float|null>}>}
     */
    public static function fromInput(string $labels, array $series): array
    {
        $out = [];
        foreach ($series as $s) {
            $values = trim((string) ($s['values'] ?? ''));
            $out[] = ['name' => (string) ($s['name'] ?? ''), 'data' => '' === $values ? [] : array_map(self::number(...), preg_split('~\s*[;\n]\s*~', $values) ?: [])];
        }

        return ['labels' => self::lines($labels), 'series' => $out];
    }

    /** @return list<string> */
    private static function lines(string $s): array
    {
        return array_values(array_filter(array_map('trim', preg_split('~\R~', $s) ?: []), static fn (string $l): bool => '' !== $l));
    }

    /** Number from an input: „1.250“, „12,5“, „1 250“, „3.5“ → float; empty/invalid → null */
    public static function number(string $v): ?float
    {
        $v = str_replace(["\u{00A0}", ' ', '%', '€'], '', trim($v));
        if ('' === $v || !preg_match('~^-?[\d.,]+$~', $v)) {
            return null;
        }
        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = strrpos($v, ',') > strrpos($v, '.') ? str_replace(['.', ','], ['', '.'], $v) : str_replace(',', '', $v);
        } elseif (str_contains($v, ',')) {
            $v = str_replace(',', '.', $v);
        } elseif (preg_match('~^-?\d{1,3}(\.\d{3})+$~', $v)) {
            $v = str_replace('.', '', $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * Validate and normalise the description (type, numbers, limits).
     *
     * @param array<string, mixed> $spec
     * @return array{type: string, labels: list<string>, series: list<array{name: string, data: list<int|float|null>}>, title: string, description: string, format: string, unit: string, stacked: bool, height: int, locale: string}
     */
    public static function normalize(array $spec): array
    {
        $type = in_array($spec['type'] ?? '', self::TYPES, true) ? (string) $spec['type'] : 'bar';
        $labels = array_slice(array_map(static fn ($l): string => mb_substr(trim((string) $l), 0, 80), array_values((array) ($spec['labels'] ?? []))), 0, self::MAX_LABELS);
        $series = [];
        foreach (array_slice(array_values((array) ($spec['series'] ?? [])), 0, self::MAX_SERIES) as $i => $s) {
            if (!is_array($s)) {
                continue;
            }
            $data = array_map(static fn ($v) => is_numeric($v) ? $v + 0 : null, array_slice(array_values((array) ($s['data'] ?? [])), 0, self::MAX_LABELS));
            $series[] = ['name' => mb_substr(trim((string) ($s['name'] ?? '')), 0, 80) ?: self::t('Reihe {n}', ['n' => $i + 1]), 'data' => $data];
        }
        if (in_array($type, ['pie', 'donut'], true)) {
            $series = array_slice($series, 0, 1);   // Pie and donut: one series
        }

        return [
            'type' => $type, 'labels' => $labels, 'series' => $series,
            'title' => mb_substr(trim((string) ($spec['title'] ?? '')), 0, 160),
            'description' => mb_substr(trim((string) ($spec['description'] ?? '')), 0, 400),
            'format' => isset(self::FORMATS[$spec['format'] ?? '']) ? (string) $spec['format'] : 'number',
            'unit' => mb_substr(trim((string) ($spec['unit'] ?? '')), 0, 12),
            'stacked' => !empty($spec['stacked']) && in_array($type, ['bar', 'hbar', 'area'], true),
            'height' => max(160, min(720, (int) ($spec['height'] ?? 320))),
            'locale' => self::locale(),
        ];
    }

    /**
     * Render the chart.
     *
     * @param array<string, mixed> $spec
     * @param array{class?: string, table?: bool|'none', caption?: bool} $opt table: true = table expanded, 'none' = without (the page already shows the values as a table)
     */
    public static function render(array $spec, array $opt = []): string
    {
        $s = self::normalize($spec);
        if (!$s['series'] || !$s['labels']) {
            return '<p class="cms-chart__empty">' . escape(self::t('Keine Daten für dieses Diagramm.')) . '</p>';
        }
        $label = '' !== $s['description'] ? $s['description'] : $s['title'];
        $json = json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $table = $opt['table'] ?? false;
        $h = '<figure class="cms-chart cms-chart--' . escape($s['type']) . (!empty($opt['class']) ? ' ' . escape((string) $opt['class']) : '') . '" data-chart'
            . ' data-chart-engine="' . escape(self::absolute(self::engine()->url())) . '">'
            . (($opt['caption'] ?? true) && '' !== $s['title'] ? '<figcaption class="cms-chart__title">' . escape($s['title']) . '</figcaption>' : '')
            . '<div class="cms-chart__canvas cms-chart__h' . (int) (round($s['height'] / 40) * 40) . '" role="img" aria-label="' . escape($label) . '"></div>'
            . '<script type="application/json" class="cms-chart__data">' . $json . '</script>'
            . ('none' === $table ? '' : '<details class="cms-chart__table"' . (true === $table ? ' open' : '') . '><summary>' . escape(self::t('Als Tabelle anzeigen')) . '</summary>' . self::table($s) . '</details>')
            . '</figure>';
        if (!self::$assets) {
            self::$assets = true;
            $addon = FluidAddon::instance();
            $h .= '<link rel="stylesheet" href="' . escape($addon->getAssetsUrl('css/charts.css')) . '"><script type="module" src="' . escape($addon->getAssetsUrl('js/charts.mjs')) . '"></script>';
        }

        return $h;
    }

    /**
     * Values as a table (without JavaScript, screen readers, print).
     *
     * @param array{title: string, labels: list<string>, series: list<array{name: string, data: list<int|float|null>}>, format: string, unit: string} $s
     */
    public static function table(array $s): string
    {
        $h = '<div class="cms-chart__scroll"><table><thead><tr><th scope="col">' . escape($s['title']) . '</th>';
        foreach ($s['series'] as $se) {
            $h .= '<th scope="col">' . escape($se['name']) . '</th>';
        }
        $h .= '</tr></thead><tbody>';
        foreach ($s['labels'] as $i => $l) {
            $h .= '<tr><th scope="row">' . escape($l) . '</th>';
            foreach ($s['series'] as $se) {
                $h .= '<td>' . escape(self::format($se['data'][$i] ?? null, $s['format'], $s['unit'])) . '</td>';
            }
            $h .= '</tr>';
        }

        return $h . '</tbody></table></div>';
    }

    /** A value in readable form (same rules as the engine) */
    public static function format(int|float|null $v, string $format = 'number', string $unit = ''): string
    {
        if (null === $v) {
            return '–';
        }
        $out = match ($format) {
            'percent' => self::num($v, abs($v) < 10 && floor($v) != $v ? 1 : 0) . ' %',
            'bytes' => self::bytes((float) $v),
            'duration' => $v >= 60 ? sprintf('%d:%02d min', intdiv((int) $v, 60), (int) $v % 60) : self::num($v, 0) . ' s',
            default => self::num($v, floor($v) != $v ? 1 : 0),
        };

        return '' !== $unit ? $out . ' ' . $unit : $out;
    }

    private static function num(int|float $v, int $decimals): string
    {
        return 'de' === self::locale() ? number_format($v, $decimals, ',', '.') : number_format($v, $decimals);
    }

    private static function bytes(float $v): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while (abs($v) >= 1024 && $i < count($units) - 1) {
            $v /= 1024;
            ++$i;
        }

        return self::num($v, $i > 0 ? 1 : 0) . ' ' . $units[$i];
    }

    /** Language of the page (language code, e.g. de, en) */
    private static function locale(): string
    {
        try {
            return Language::getCurrent()->code;
        } catch (Throwable) {
            return 'de';
        }
    }

    /** @param array<string, string|int> $vars */
    private static function t(string $text, array $vars = []): string
    {
        if (function_exists('lt')) {
            return lt($text, $vars);
        }
        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', (string) $v, $text);
        }

        return $text;
    }

    /** Engine addresses absolute: the loader fetches them via import() – relative addresses would resolve from the module */
    private static function absolute(string $url): string
    {
        if (preg_match('~^((https?:)?//|/)~', $url)) {
            return $url;
        }

        return Router::base() . '/' . (string) preg_replace('~^(\.\.?/)+~', '', $url);
    }
}
