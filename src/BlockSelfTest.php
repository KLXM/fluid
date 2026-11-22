<?php

namespace Klxm\Fluid;

use function count;
use function dirname;
use function file;
use function glob;
use function implode;
use function preg_match;
use function str_contains;

/**
 * Selbsttest der Blöcke und Kits (`console fluid:selftest`, Gruppe „Blöcke“) – Vorbild KLXM Studio (Core\Blocks\SelfTest):
 *
 *  - Views: kein Code hinter einem Zeilenkommentar (`<?php $a = 1; // … $b = 2;` – alles nach // fehlt still).
 *    Läuft auch ohne REDAXO: `php tools/check-views.php` (CI).
 *  - Inhaltsverzeichnis (fluid_toc): h2 als oberste Ebene nur, wenn der Text h2 enthält.
 *  - Video-Schalter (fluid_video_toggle): Name im Text, kein aria-pressed, beide Symbole, Ausgangszustand „abspielen“.
 */
final class BlockSelfTest
{
    private int $ok = 0;
    /** @var list<string> */
    private array $fails = [];

    /** @return array{ok: int, fails: list<string>} */
    public static function selftest(): array
    {
        $t = new self();
        $t->views();
        $t->toc();
        $t->videoToggle();

        return ['ok' => $t->ok, 'fails' => $t->fails];
    }

    private function check(bool $cond, string $label): void
    {
        if ($cond) {
            ++$this->ok;
        } else {
            $this->fails[] = $label;
        }
    }

    // ------------------------------------------------------------------ Views

    /**
     * Zeilen in Views (Blöcke, Seitenrahmen, Fragmente) mit Code nach „; //“ in derselben PHP-Zeile, ohne schließendes
     * PHP-Tag dahinter. Ohne REDAXO aufrufbar (tools/check-views.php).
     *
     * @return list<string> Datei:Zeile
     */
    public static function viewComments(?string $root = null): array
    {
        $root ??= dirname(__DIR__);
        $files = array_merge(
            glob($root . '/kits/*/views/*.php') ?: [],
            glob($root . '/kits/*/*.view.php') ?: [],
            glob($root . '/kits/*/src/*.view.php') ?: [],
            glob($root . '/src/*.view.php') ?: [],
            glob($root . '/src/*/*.view.php') ?: [],
            glob($root . '/fragments/*.php') ?: [],
            glob($root . '/fragments/*/*.php') ?: [],
        );
        $bad = [];
        foreach ($files as $f) {
            foreach (file($f) ?: [] as $no => $line) {
                if (!str_contains($line, '<?php') || !str_contains($line, '//')) {
                    continue;
                }
                if (preg_match('~;\s*//[^\n]*\$[A-Za-z_]\w*\s*=[^=>]~', $line) && !preg_match('~//[^\n]*\?>~', $line)) {
                    $bad[] = substr($f, strlen($root) + 1) . ':' . ($no + 1);
                }
            }
        }

        return $bad;
    }

    private function views(): void
    {
        $bad = self::viewComments();
        $this->check([] === $bad, 'Views: kein Code hinter einem Zeilenkommentar (' . implode(', ', $bad) . ')');
        // Der Test erkennt den Fehler (Probe in einer Datei im Temp-Ordner)
        $dir = sys_get_temp_dir() . '/fluid-views-' . bin2hex(random_bytes(4));
        @mkdir($dir . '/kits/probe/views', 0o777, true);
        file_put_contents($dir . '/kits/probe/views/x.php', "<?php \$a = 1; // Kommentar \$b = 2;\n<?php \$c = 1; // ok ?>\n");
        $hits = self::viewComments($dir);
        @unlink($dir . '/kits/probe/views/x.php');
        @rmdir($dir . '/kits/probe/views');
        @rmdir($dir . '/kits/probe');
        @rmdir($dir . '/kits');
        @rmdir($dir);
        $this->check(['kits/probe/views/x.php:1'] === $hits, 'Views: Probe mit Code hinter // wird erkannt (' . implode(', ', $hits) . ')');
    }

    // ------------------------------------------------------------------ Inhaltsverzeichnis

    private function toc(): void
    {
        if (!function_exists('fluid_toc')) {
            Kit::get(Kit::DEFAULT)?->definition();   // lädt functions.php des Kits fluid
        }
        if (!function_exists('fluid_toc')) {
            $this->fails[] = 'Inhaltsverzeichnis: fluid_toc() fehlt';

            return;
        }
        [, $toc] = fluid_toc('<h3>Eins</h3><p>x</p><h4>Unter</h4><h3>Zwei</h3>', 't');
        $this->check([2, 3, 2] === array_column($toc, 'level'), 'Inhaltsverzeichnis: nur h3/h4 – h3 oberste Ebene (unverändert)');
        [$html, $toc] = fluid_toc('<h2>Eins</h2><h3>Unter</h3><h2 class="x">Zwei</h2><h4>Tief</h4>', 't');
        $this->check([2, 3, 2, 3] === array_column($toc, 'level'), 'Inhaltsverzeichnis: Text mit h2 – h2 oberste Ebene');
        $this->check(str_contains($html, '<h2 id="t-eins">') && str_contains($html, '<h2 class="x" id="t-zwei">'), 'Inhaltsverzeichnis: h2 bekommen Sprungmarken');
    }

    // ------------------------------------------------------------------ Video-Schalter

    private function videoToggle(): void
    {
        if (!function_exists('fluid_video_toggle')) {
            Kit::get(Kit::DEFAULT)?->definition();
        }
        $h = fluid_video_toggle('x__toggle');
        $this->check(str_contains($h, 'class="x__toggle hx-vt hx-vt--icon is-paused" data-hero-video-toggle hidden'), 'Video-Schalter: Klassen, verborgen ohne Skript, Ausgangszustand angehalten');
        $this->check(!str_contains($h, 'aria-pressed') && !str_contains($h, 'aria-label'), 'Video-Schalter: kein aria-pressed, Name aus dem Text');
        $this->check(1 === preg_match('~<span class="hx-vt__label" data-l-pause="[^"]+" data-l-play="([^"]+)">\1</span></button>$~', $h), 'Video-Schalter: Text „abspielen“ mit beiden Beschriftungen für hero-video.js');
        $this->check(str_contains($h, '#i-pause"') && str_contains($h, '#i-play"') && 2 === substr_count($h, 'aria-hidden="true"'), 'Video-Schalter: Symbole „pause“ und „play“, dekorativ');
        $this->check(str_contains(fluid_video_toggle('', 'text'), 'hx-vt hx-vt--text') && str_contains(fluid_video_toggle('', 'quatsch'), 'hx-vt--icon'), 'Video-Schalter: Symbol mit Text, Unbekanntes = Symbol');
        $this->check(Kit::active()->used('@videotoggle'), 'Video-Schalter: lädt CSS und Skript (@videotoggle)');
        $map = (array) (Kit::get(Kit::DEFAULT)?->definition()['assets'] ?? []);
        $this->check(['@videotoggle'] === ($map['css/video-toggle.css'] ?? null) && ['@videotoggle'] === ($map['js/hero-video.js'] ?? null), 'Video-Schalter: kit.php → assets');
        foreach (['pause', 'play'] as $n) {
            $this->check(str_contains((string) file_get_contents(dirname(__DIR__) . '/assets/kits/fluid/icons/core.svg'), 'id="i-' . $n . '"'), 'Video-Schalter: Symbol „' . $n . '“ im Symbolsatz');
        }
    }
}
