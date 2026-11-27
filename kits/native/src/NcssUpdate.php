<?php

namespace Klxm\Fluid\Kit\Native;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

use function array_diff;
use function array_keys;
use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_resource;
use function sha1_file;
use function strlen;

use const ARRAY_FILTER_USE_KEY;
use const GLOB_ONLYDIR;
use const PREG_SET_ORDER;

/**
 * Updates NativeCSS in the kit „NativeCSS“ (`assets/ncss/`) from a working copy or a release of
 * https://github.com/skerbis/nativecss – runs without REDAXO (console `fluid:kit:ncss-update`, `tools/ncss-update.php`).
 *
 * - copies `dist/` unchanged (CSS in the root folder, `components/`, `helpers/`, `js/`, `fonts/` with OFL licenses; not `integrations/` – fluid
 *   doesn't use Web Awesome) and the LICENSE; files no longer present upstream are removed
 * - writes `assets/ncss/VERSION.txt` (state, commit, date, source)
 * - generates `assets/css/ncss-base.css`: NativeCSS's manifest `ncss.css` without the components fluid loads only on
 *   pages that need them (ON_DEMAND, wrappers `css/ncss-*.css` in kit.php), and without those fluid doesn't use
 *   (UNUSED) – cherry-picking as NativeCSS intends
 *
 * fluid's own files (`assets/css/`, `views/`, `template.view.php`) stay untouched.
 */
final class NcssUpdate
{
    public const string REPO = 'skerbis/nativecss';

    /** Loaded by fluid only when needed (wrappers with layer(…) in assets/css/, mapping in kit.php → assets) */
    public const array ON_DEMAND = [
        'helpers/reveal.css',            // Section → reveal effect (@reveal)
        'components/count-up.css',       // Key figures
        'components/marquee.css',        // Logos „Laufband“
        'components/scrolly.css',        // Scrollytelling
        'components/compare.css',        // Before/after
        'components/video-player.css',   // Video & Audio (Video)
        'components/audio-player.css',   // Video & Audio (Audio)
        'components/video-bg.css',       // Intro „Vollflächig“ with background video (pause/play toggle)
        'components/preferences.css',    // Website → „Darstellungs-Knopf für Besucher anzeigen“ (<ncss-preferences>)
        'components/morph.css',          // Text + image, display „Morphing beim Scrollen“ (kit NativeCSS only)
        'helpers/scroll-shadow.css',     // Tables in body text, data list, packages (@table)
        'components/table-sort.css',     // „Spalten sortierbar“ (@tablesort)
        'components/filter.css',         // Cards/data list „Filter nach Kategorie“ (@filter)
        'components/timeline.css',       // Process „Zeitleiste“
        'components/testimonial.css',    // Quote „Groß“ and „Raster“
        'components/pricing.css',        // Packages „Karten“
        'components/leaders.css',        // Packages „Preisliste (Speisekarte)“
        'components/dropdown.css',       // Language switch in the header from three languages on (@dropdown)
    ];

    /** NativeCSS components fluid doesn't use (yet) – not in the manifest, the files are still available */
    public const array UNUSED = [
        'components/livesearch.css', 'components/scroll-video.css', 'components/scroll-frames.css',
        'components/video-text.css', 'components/video-float.css', 'components/video-card.css', 'components/theme-toggle.css',
        'components/lightbox.css', 'components/subnav.css', 'components/spotlight.css', 'components/expand-panels.css',
        'components/command-palette.css', 'components/hovercard.css', 'components/notification.css', 'components/aurora.css',
        'components/backdrop.css', 'components/curtain.css', 'components/scroll-horizontal.css', 'components/text-reveal.css',
        'components/reveal-pin.css',
        // in the manifest since 1.1.0, no block in fluid (yet)
        'components/tabs.css', 'components/tooltip.css', 'components/pagination.css', 'components/alert.css', 'components/avatar.css',
        'components/tag.css', 'components/stat.css', 'components/empty-state.css', 'components/spinner.css', 'components/kbd.css',
        'components/floating-label.css', 'components/segmented.css', 'components/rating.css', 'components/dropzone.css',
        'components/countdown.css', 'components/back-to-top.css', 'components/app-shell.css',
        // <ncss-map>: fluid has its own map block (MapLibre, proxy, no consent) – vendor/maplibre is not copied
        'components/map.css',
        // 1.1.0 (round 2): no counterpart in fluid
        'components/pill-group.css', 'components/toolbar.css', 'components/stepper.css', 'components/comments.css', 'components/sortable.css',
    ];

    /** Files from dist/: CSS in the root folder, components/, helpers/, js/ and fonts/ (fonts of the display settings with license) */
    private const string DIRS = '~^(components|helpers|js)/[a-z0-9-]+\.(css|js)$|^[a-z0-9-]+\.css$|^fonts/([a-z0-9-]+/)?[A-Za-z0-9._-]+\.(woff2|txt|md)$~';

    /** @var array<string, string> path relative to dist → absolute source */
    private array $files = [];

    private string $dist = '';

    private string $license = '';

    /** @var array{version: string, commit: string, date: string, source: string, dirty: bool} */
    private array $info = ['version' => '', 'commit' => '', 'date' => '', 'source' => '', 'dirty' => false];

    /** @var list<string> */
    private array $cleanup = [];

    /** @param string $assets the kit's `assets` folder (contains `ncss/` and `css/`) */
    public function __construct(
        private readonly string $assets,
    ) {}

    public function __destruct()
    {
        foreach ($this->cleanup as $dir) {
            self::removeDir($dir);
        }
    }

    /**
     * Working copy of NativeCSS (folder with `dist/` or the `dist` folder itself). With $ref (e.g. `HEAD`, `v1.2.0`)
     * and a Git working copy: exactly this state via `git archive` – uncommitted changes stay out.
     */
    public function fromDirectory(string $dir, string $ref = ''): self
    {
        $dir = rtrim($dir, '/');
        $root = is_file($dir . '/ncss.css') ? dirname($dir) : $dir;
        $git = is_dir($root . '/.git') || is_file($root . '/.git');
        if ('' !== $ref) {
            if (!$git) {
                throw new RuntimeException('--ref braucht eine Git-Arbeitskopie: ' . $root);
            }
            $tmp = $this->tmpDir();
            $tar = $tmp . '/dist.tar';
            self::run(['git', '-C', $root, 'archive', '--format=tar', '-o', $tar, $ref, 'dist', 'LICENSE', 'package.json']);
            self::run(['tar', '-xf', $tar, '-C', $tmp]);
            $this->info = ['version' => self::packageVersion($tmp) ?? self::gitOut($root, ['describe', '--tags', '--always', $ref]), 'commit' => self::gitOut($root, ['rev-parse', '--short', $ref]),
                'date' => self::gitOut($root, ['log', '-1', '--format=%cs', $ref]), 'source' => $root . ' @ ' . $ref, 'dirty' => false];

            return $this->scan($tmp . '/dist', $tmp . '/LICENSE');
        }
        if ($git) {
            $this->info = ['version' => self::packageVersion($root) ?? self::gitOut($root, ['describe', '--tags', '--always']), 'commit' => self::gitOut($root, ['rev-parse', '--short', 'HEAD']),
                'date' => self::gitOut($root, ['log', '-1', '--format=%cs']), 'source' => $root,
                'dirty' => '' !== self::gitOut($root, ['status', '--porcelain', '--', 'dist'])];
        } else {
            $this->info = ['version' => 'lokal', 'commit' => '', 'date' => date('Y-m-d'), 'source' => $root, 'dirty' => false];
        }

        return $this->scan($root . '/dist', $root . '/LICENSE');
    }

    /** Release, tag or branch from GitHub (zip from codeload.github.com), e.g. `v1.2.0` or `main` */
    public function fromGithub(string $ref, string $repo = self::REPO): self
    {
        if (!preg_match('~^[\w.\-/]+$~', $ref) || !preg_match('~^[\w.-]+/[\w.-]+$~', $repo)) {
            throw new RuntimeException('Ungültige Angabe: ' . $repo . ' ' . $ref);
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP-Erweiterung zip fehlt.');
        }
        $tmp = $this->tmpDir();
        $zipFile = $tmp . '/ncss.zip';
        $url = 'https://codeload.github.com/' . $repo . '/zip/' . rawurlencode($ref);
        $ctx = stream_context_create(['http' => ['timeout' => 60, 'header' => "User-Agent: fluid-ncss-update\r\n"]]);
        $data = @file_get_contents($url, false, $ctx);
        if (false === $data || '' === $data) {
            throw new RuntimeException('Download fehlgeschlagen: ' . $url);
        }
        file_put_contents($zipFile, $data);
        $zip = new ZipArchive();
        if (true !== $zip->open($zipFile)) {
            throw new RuntimeException('Zip lässt sich nicht öffnen: ' . $url);
        }
        $zip->extractTo($tmp . '/x');
        $zip->close();
        $top = glob($tmp . '/x/*', GLOB_ONLYDIR) ?: [];
        if (1 !== count($top) || !is_dir($top[0] . '/dist')) {
            throw new RuntimeException('Im Archiv fehlt dist/.');
        }
        $this->info = ['version' => self::packageVersion($top[0]) ?? $ref, 'commit' => '', 'date' => date('Y-m-d'), 'source' => 'https://github.com/' . $repo . ' (' . $ref . ')', 'dirty' => false];

        return $this->scan($top[0] . '/dist', $top[0] . '/LICENSE');
    }

    /** @return array{version: string, commit: string, date: string, source: string, dirty: bool} */
    public function info(): array
    {
        return $this->info;
    }

    /**
     * What would change – paths relative to `assets/ncss/`, plus the generated manifest.
     *
     * @return array{added: list<string>, changed: list<string>, removed: list<string>, unchanged: int, manifest: list<string>}
     */
    public function plan(): array
    {
        $target = $this->assets . '/ncss';
        $current = is_dir($target) ? self::list($target) : [];
        $added = $changed = [];
        $unchanged = 0;
        foreach ($this->files as $rel => $src) {
            if (!isset($current[$rel])) {
                $added[] = $rel;
            } elseif (sha1_file($src) !== sha1_file($current[$rel])) {
                $changed[] = $rel;
            } else {
                ++$unchanged;
            }
        }
        $removed = array_values(array_diff(array_keys($current), array_keys($this->files), ['VERSION.txt', 'LICENSE.txt']));
        sort($added);
        sort($changed);
        sort($removed);

        return ['added' => $added, 'changed' => $changed, 'removed' => $removed, 'unchanged' => $unchanged, 'manifest' => $this->manifestImports()];
    }

    /** Write files, delete removed ones, generate VERSION.txt and css/ncss-base.css */
    public function apply(): void
    {
        $plan = $this->plan();
        $target = $this->assets . '/ncss';
        foreach ($this->files as $rel => $src) {
            if (!is_dir(dirname($target . '/' . $rel))) {
                mkdir(dirname($target . '/' . $rel), 0o775, true);
            }
            copy($src, $target . '/' . $rel);
        }
        foreach ($plan['removed'] as $rel) {
            unlink($target . '/' . $rel);
        }
        if ('' !== $this->license) {
            copy($this->license, $target . '/LICENSE.txt');
        }
        $i = $this->info;
        file_put_contents($target . '/VERSION.txt', 'NativeCSS ' . ('' !== $i['commit'] && $i['commit'] !== $i['version'] ? $i['version'] . ' ' . $i['commit'] : $i['version'])
            . ' (' . $i['date'] . ')' . ($i['dirty'] ? ' + nicht committete Änderungen' : '') . " – https://github.com/skerbis/nativecss\n"
            . 'Übernommen mit fluid:kit:ncss-update am ' . date('Y-m-d') . ' aus ' . $i['source'] . "\n");
        file_put_contents($this->assets . '/css/ncss-base.css', $this->manifest());
    }

    /**
     * @return list<string> @import lines of the generated manifest. Since NativeCSS 1.3, files in components/ and
     * helpers/ set their layer themselves and ncss.css imports them without layer() – then take them over without layer().
     */
    private function manifestImports(): array
    {
        $skip = [...self::ON_DEMAND, ...self::UNUSED];
        $out = [];
        preg_match_all('~@import\s+url\("([^"]+)"\)(?:\s+layer\(([\w-]+)\))?\s*;~', $this->upstreamManifest(), $m, PREG_SET_ORDER);
        foreach ($m as $match) {
            [$file, $layer] = [$match[1], $match[2] ?? ''];
            if (!in_array($file, $skip, true)) {
                $out[] = '@import url("../ncss/' . $file . '")' . ('' !== $layer ? ' layer(' . $layer . ')' : '') . ';';
            }
        }

        return $out;
    }

    private function manifest(): string
    {
        preg_match('~@layer\s[^;{]+;~', $this->upstreamManifest(), $layers);

        return "/*\n * NativeCSS for the kit „NativeCSS“ – GENERATED by `fluid:kit:ncss-update`, do not edit by hand.\n"
            . " * The NativeCSS manifest ncss.css without the components fluid loads only when needed (css/ncss-*.css, kit.php →\n"
            . " * assets) or doesn't use (Kit\\Native\\NcssUpdate::ON_DEMAND / ::UNUSED).\n */\n"
            . ($layers[0] ?? '') . "\n\n" . implode("\n", $this->manifestImports()) . "\n";
    }

    /** NativeCSS's ncss.css without comments */
    private function upstreamManifest(): string
    {
        return (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($this->dist . '/ncss.css'));
    }

    private function scan(string $dist, string $license): self
    {
        if (!is_file($dist . '/ncss.css')) {
            throw new RuntimeException('Kein NativeCSS gefunden (dist/ncss.css fehlt): ' . $dist);
        }
        $this->dist = $dist;
        $this->license = is_file($license) ? $license : '';
        $this->files = array_filter(self::list($dist), static fn (string $rel) => 1 === preg_match(self::DIRS, $rel), ARRAY_FILTER_USE_KEY);
        foreach ([...self::ON_DEMAND] as $rel) {
            if (!isset($this->files[$rel])) {
                throw new RuntimeException('NativeCSS ohne ' . $rel . ' – fluid lädt die Datei bei Bedarf. Bitte NcssUpdate::ON_DEMAND und kit.php anpassen.');
            }
        }

        return $this;
    }

    /** @return array<string, string> */
    private static function list(string $dir): array
    {
        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && '.' !== $f->getFilename()[0]) {
                $out[str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1))] = $f->getPathname();
            }
        }
        ksort($out);

        return $out;
    }

    private function tmpDir(): string
    {
        $dir = sys_get_temp_dir() . '/fluid-ncss-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o700, true);
        $this->cleanup[] = $dir;

        return $dir;
    }

    /** Version from NativeCSS's package.json („1.1.0“), otherwise null */
    private static function packageVersion(string $root): ?string
    {
        $json = is_file($root . '/package.json') ? json_decode((string) file_get_contents($root . '/package.json'), true) : null;
        $v = is_array($json) ? (string) ($json['version'] ?? '') : '';

        return 1 === preg_match('~^\d+\.\d+\.\d+[\w.+-]*$~', $v) ? $v : null;
    }

    /** @param list<string> $cmd */
    private static function run(array $cmd): string
    {
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($p)) {
            throw new RuntimeException('Befehl lässt sich nicht starten: ' . $cmd[0]);
        }
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        if (0 !== proc_close($p)) {
            throw new RuntimeException(trim($err) ?: 'Fehler bei ' . implode(' ', $cmd));
        }

        return trim($out);
    }

    /** @param list<string> $args */
    private static function gitOut(string $root, array $args): string
    {
        try {
            return self::run(['git', '-C', $root, ...$args]);
        } catch (RuntimeException) {
            return '';
        }
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
