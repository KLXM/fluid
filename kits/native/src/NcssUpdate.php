<?php

namespace Klxm\Fluid\Kit\Native;

use RuntimeException;
use ZipArchive;

use function array_diff;
use function array_keys;
use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function sha1_file;

/**
 * Aktualisiert NativeCSS im Kit „NativeCSS“ (`assets/ncss/`) aus einer Arbeitskopie oder einem Release von
 * https://github.com/skerbis/nativecss – ohne REDAXO lauffähig (Konsole `fluid:kit:ncss-update`, `tools/ncss-update.php`).
 *
 * - kopiert `dist/` unverändert (CSS im Wurzelordner, `components/`, `helpers/`, `js/`; nicht `integrations/` – Web Awesome
 *   nutzt fluid nicht) und die LICENSE; Dateien, die es upstream nicht mehr gibt, werden entfernt
 * - schreibt `assets/ncss/VERSION.txt` (Stand, Commit, Datum, Quelle)
 * - erzeugt `assets/css/ncss-base.css`: das Manifest `ncss.css` von NativeCSS ohne die Komponenten, die fluid nur auf
 *   Seiten lädt, die sie brauchen (ON_DEMAND, Wrapper `css/ncss-*.css` in kit.php), und ohne die, die fluid nicht nutzt
 *   (UNUSED) – Cherry-Picking im Sinne von NativeCSS
 *
 * Eigenes von fluid (`assets/css/`, `views/`, `template.view.php`) bleibt unberührt.
 */
final class NcssUpdate
{
    public const string REPO = 'skerbis/nativecss';

    /** Lädt fluid nur bei Bedarf (Wrapper mit layer(…) in assets/css/, Abbildung in kit.php → assets) */
    public const array ON_DEMAND = [
        'helpers/reveal.css',            // Abschnitt → Einblend-Effekt (@reveal)
        'components/count-up.css',       // Kennzahlen
        'components/marquee.css',        // Logos „Laufband“
        'components/scrolly.css',        // Scrollytelling
        'components/compare.css',        // Vorher/Nachher
        'components/video-player.css',   // Video & Audio (Video)
        'components/audio-player.css',   // Video & Audio (Audio)
        'components/video-bg.css',       // Einstieg „Vollflächig“ mit Hintergrundvideo (Schalter Pausieren/Abspielen)
    ];

    /** Komponenten von NativeCSS, die fluid (noch) nicht verwendet – nicht im Manifest, die Dateien liegen trotzdem bereit */
    public const array UNUSED = [
        'components/livesearch.css', 'components/scroll-video.css', 'components/scroll-frames.css', 
        'components/video-text.css', 'components/video-float.css', 'components/video-card.css', 'components/theme-toggle.css',
        'components/lightbox.css', 'components/subnav.css', 'components/spotlight.css', 'components/expand-panels.css',
        'components/command-palette.css', 'components/hovercard.css', 'components/notification.css', 'components/aurora.css',
        'components/backdrop.css', 'components/curtain.css', 'components/scroll-horizontal.css', 'components/text-reveal.css',
        'components/reveal-pin.css',
    ];

    private const string DIRS = '~^(components|helpers|js)/[a-z0-9-]+\.(css|js)$|^[a-z0-9-]+\.css$~';

    /** @var array<string, string> Pfad relativ zu dist → absolute Quelle */
    private array $files = [];

    private string $dist = '';

    private string $license = '';

    /** @var array{version: string, commit: string, date: string, source: string, dirty: bool} */
    private array $info = ['version' => '', 'commit' => '', 'date' => '', 'source' => '', 'dirty' => false];

    /** @var list<string> */
    private array $cleanup = [];

    /** @param string $assets Ordner `assets` des Kits (enthält `ncss/` und `css/`) */
    public function __construct(private readonly string $assets) {}

    public function __destruct()
    {
        foreach ($this->cleanup as $dir) {
            self::removeDir($dir);
        }
    }

    /**
     * Arbeitskopie von NativeCSS (Ordner mit `dist/` oder der `dist`-Ordner selbst). Mit $ref (z. B. `HEAD`, `v1.2.0`)
     * und einer Git-Arbeitskopie: genau dieser Stand per `git archive` – nicht committete Änderungen bleiben draußen.
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
            self::run(['git', '-C', $root, 'archive', '--format=tar', '-o', $tar, $ref, 'dist', 'LICENSE']);
            self::run(['tar', '-xf', $tar, '-C', $tmp]);
            $this->info = ['version' => self::gitOut($root, ['describe', '--tags', '--always', $ref]), 'commit' => self::gitOut($root, ['rev-parse', '--short', $ref]),
                'date' => self::gitOut($root, ['log', '-1', '--format=%cs', $ref]), 'source' => $root . ' @ ' . $ref, 'dirty' => false];

            return $this->scan($tmp . '/dist', $tmp . '/LICENSE');
        }
        if ($git) {
            $this->info = ['version' => self::gitOut($root, ['describe', '--tags', '--always']), 'commit' => self::gitOut($root, ['rev-parse', '--short', 'HEAD']),
                'date' => self::gitOut($root, ['log', '-1', '--format=%cs']), 'source' => $root,
                'dirty' => '' !== self::gitOut($root, ['status', '--porcelain', '--', 'dist'])];
        } else {
            $this->info = ['version' => 'lokal', 'commit' => '', 'date' => date('Y-m-d'), 'source' => $root, 'dirty' => false];
        }

        return $this->scan($root . '/dist', $root . '/LICENSE');
    }

    /** Release, Tag oder Branch von GitHub (Zip von codeload.github.com), z. B. `v1.2.0` oder `main` */
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
        $this->info = ['version' => $ref, 'commit' => '', 'date' => date('Y-m-d'), 'source' => 'https://github.com/' . $repo . ' (' . $ref . ')', 'dirty' => false];

        return $this->scan($top[0] . '/dist', $top[0] . '/LICENSE');
    }

    /** @return array{version: string, commit: string, date: string, source: string, dirty: bool} */
    public function info(): array
    {
        return $this->info;
    }

    /**
     * Was sich ändern würde – Pfade relativ zu `assets/ncss/`, dazu das erzeugte Manifest.
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

    /** Dateien schreiben, entfernte löschen, VERSION.txt und css/ncss-base.css erzeugen */
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

    /** @return list<string> @import-Zeilen des erzeugten Manifests */
    private function manifestImports(): array
    {
        $skip = [...self::ON_DEMAND, ...self::UNUSED];
        $out = [];
        preg_match_all('~@import\s+url\("([^"]+)"\)\s+layer\(([\w-]+)\);~', $this->upstreamManifest(), $m, PREG_SET_ORDER);
        foreach ($m as [$all, $file, $layer]) {
            if (!in_array($file, $skip, true)) {
                $out[] = '@import url("../ncss/' . $file . '") layer(' . $layer . ');';
            }
        }

        return $out;
    }

    private function manifest(): string
    {
        preg_match('~@layer\s[^;{]+;~', $this->upstreamManifest(), $layers);

        return "/*\n * NativeCSS für das Kit „NativeCSS“ – ERZEUGT von `fluid:kit:ncss-update`, nicht von Hand ändern.\n"
            . " * Das Manifest ncss.css von NativeCSS ohne die Komponenten, die fluid nur bei Bedarf lädt (css/ncss-*.css, kit.php →\n"
            . " * assets) oder nicht verwendet (Kit\\Native\\NcssUpdate::ON_DEMAND / ::UNUSED).\n */\n"
            . ($layers[0] ?? '') . "\n\n" . implode("\n", $this->manifestImports()) . "\n";
    }

    /** ncss.css von NativeCSS ohne Kommentare */
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
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
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

    /** @param list<string> $cmd */
    private static function run(array $cmd): string
    {
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!\is_resource($p)) {
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
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
