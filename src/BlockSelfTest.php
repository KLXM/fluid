<?php

namespace Klxm\Fluid;

use Redaxo\Core\Database\Sql;

use function count;
use function dirname;
use function file;
use function function_exists;
use function glob;
use function implode;
use function preg_match;
use function str_contains;
use function strlen;

/**
 * Selbsttest der Blöcke und Kits (`console fluid:selftest`, Gruppe „Blöcke“) – Vorbild KLXM Studio (Core\Blocks\SelfTest):
 *
 *  - Views: kein Code hinter einem Zeilenkommentar (`<?php $a = 1; // … $b = 2;` – alles nach // fehlt still).
 *    Läuft auch ohne REDAXO: `php tools/check-views.php` (CI).
 *  - Inhaltsverzeichnis (fluid_toc): h2 als oberste Ebene nur, wenn der Text h2 enthält.
 *  - Video-Schalter (fluid_video_toggle): Name im Text, kein aria-pressed, beide Symbole, Ausgangszustand „abspielen“.
 *  - Geänderte Standardwerte (DefaultChanges): bisheriger Zustand für gespeicherte Blöcke, idempotent, auch in „Fluid-Blöcke“.
 *  - Interaktives Bild (Hotspots): Format v1 wie KLXM Studio, Formularzeilen, Ausgabe (Popover, Liste, SVG, keine style-Attribute).
 *  - Video-Links (VideoLinks): Erkennung, aus = unverändert, an = Kennzeichnung und Skript, Ausnahme je Link-Feld.
 *  - Termine (Data\Ics): Kalenderdatei ganztägig und mit Uhrzeit, Maskierung, Falten.
 *  - Scrollytelling: Klartext bleibt Klartext, Zwischenüberschriften unter dem Abschnittstitel.
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
        $t->defaultChanges();
        $t->hotspots();
        $t->videoLinks();
        $t->ics();
        $t->scrolly();

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

    // ------------------------------------------------------------------ Geänderte Standardwerte

    private function defaultChanges(): void
    {
        $rules = DefaultChanges::rules();
        $this->check('center' === ($rules['media_text']['valign'] ?? null) && 'grid' === ($rules['gallery']['single_size'] ?? null) && '4:5' === ($rules['scrolly']['ratio'] ?? null),
            'Standardwerte: Regeln für Text + Bild, Galerie, Scrollytelling');
        $f = ['valign' => 'center'];
        $j = DefaultChanges::patchJson('{"variant":"right","title":"T","tunes":{"background":"muted"}}', $f);
        $d = json_decode((string) $j, true);
        $this->check('center' === ($d['valign'] ?? null) && 'T' === $d['title'] && ['background' => 'muted'] === $d['tunes'], 'Standardwerte: fehlender Wert → bisheriger Zustand, Rest bleibt');
        $this->check(null === DefaultChanges::patchJson((string) $j, $f), 'Standardwerte: zweiter Lauf ändert nichts (idempotent)');
        $this->check(null === DefaultChanges::patchJson('{"valign":"top"}', $f), 'Standardwerte: vorhandener Wert bleibt');
        $this->check(null === DefaultChanges::patchJson('{"valign":null}', $f), 'Standardwerte: auch null bleibt');
        $legacy = json_decode((string) DefaultChanges::patchJson('{"values":{"title":"A"},"tunes":{}}', $f), true);
        $this->check('center' === ($legacy['values']['valign'] ?? null), 'Standardwerte: früheres Format {values, tunes}');
        $types = ['fluid.media_text' => 'media_text', 'fluid.cta' => 'cta'];
        $list = json_decode((string) DefaultChanges::patchBlockList('[{"module":"fluid.media_text","data":{"title":"x"},"tunes":{}},{"module":"fluid.cta","data":{}}]', ['media_text' => $f], $types), true);
        $this->check('center' === ($list[0]['data']['valign'] ?? null) && [] === $list[1]['data'], 'Standardwerte: Feld „Fluid-Blöcke“, andere Blöcke unberührt');
        $mt = BlockModule::classFor('fluid.media_text');
        $def = null !== $mt ? array_column($mt::fields(), 'default', 'name') : [];
        $this->check('follow' === ($def['valign'] ?? null), 'Standardwerte: neuer Text + Bild-Block bekommt „Mitlaufen“');
        $g = BlockModule::classFor('fluid.gallery');
        $this->check('large' === (null !== $g ? array_column($g::fields(), 'default', 'name')['single_size'] ?? null : null), 'Standardwerte: neue Galerie bekommt Einzelbild „groß“');
        $sc = BlockModule::classFor('fluid.scrolly');
        $this->check('auto' === (null !== $sc ? array_column($sc::fields(), 'default', 'name')['ratio'] ?? null : null), 'Standardwerte: neues Scrollytelling bekommt „Automatisch“');
    }

    // ------------------------------------------------------------------ Interaktives Bild

    private function hotspots(): void
    {
        $n = Hotspots::normalize('{"v":1,"items":[{"id":"a","shape":"point","x":12.345,"y":140,"title":"<b>T</b>","text":"<p>x<img src=\"y\"></p>","link":"javascript:alert(1)"},'
            . '{"shape":"poly","points":"10,10 30,12 20,30"},{"shape":"poly","points":[[1,1]],"x":5,"y":6},{"shape":"rect","x":90,"y":90,"w":50,"h":50},{"title":"ohne Lage"},'
            . '{"id":"a","shape":"circle","x":50,"y":50}]}');
        $it = $n['items'];
        $this->check(1 === $n['v'] && 5 === count($it), 'Markierungen: Format v1, ungültige Zeile fällt weg');
        $this->check(12.35 === $it[0]['x'] && 100.0 === $it[0]['y'] && 'T' === $it[0]['title'] && '' === $it[0]['link'] && !str_contains($it[0]['text'], '<img'), 'Markierungen: runden, begrenzen, Titel ohne HTML, kein Bild im Text, kein javascript:');
        $this->check('h2' === $it[1]['id'] && [[10.0, 10.0], [30.0, 12.0], [20.0, 30.0]] === $it[1]['points'], 'Markierungen: Vieleck aus Text, Kennung aus der Position');
        $this->check('point' === $it[2]['shape'] && 5.0 === $it[2]['x'], 'Markierungen: Vieleck mit zu wenigen Punkten wird Punkt');
        $this->check(10.0 === $it[3]['w'] && 10.0 === $it[3]['h'], 'Markierungen: Rechteck bleibt im Bild');
        $this->check('a-2' === $it[4]['id'], 'Markierungen: doppelte Kennung wird eindeutig');
        $this->check([20.0, 17.33] === Hotspots::anchor($it[1]), 'Markierungen: Mittelpunkt eines Vielecks');
        $rows = Hotspots::toForm($n);
        $this->check('10,10 30,12 20,30' === $rows[1]['points'] && Hotspots::fromForm($rows) === $n, 'Markierungen: Formularzeilen hin und zurück ohne Verlust');
        $mid = Hotspots::fromForm([['title' => 'Neu', 'x' => '', 'y' => '']]);
        $this->check(50.0 === ($mid['items'][0]['x'] ?? null), 'Markierungen: neue Zeile mit Inhalt ohne Lage → Bildmitte');
        $media = Sql::factory()->getArray("SELECT filename FROM rex_media WHERE filetype LIKE 'image/%' AND filetype <> 'image/svg+xml' ORDER BY id LIMIT 1");
        $file = (string) ($media[0]['filename'] ?? '');
        if ('' === $file) {
            return;
        }
        Hotspots::reset();
        $h = Hotspots::render($file, $n, ['id' => 'st', 'label' => 'Probe']);
        $this->check(str_contains($h, 'aria-label="Interaktives Bild: Probe" data-cms-hs') || str_contains($h, 'aria-label="Interactive image: Probe" data-cms-hs'), 'Markierungen: Bild benannt');
        $this->check(5 === substr_count($h, 'popovertarget="st-') - 5 && str_contains($h, 'popover role="dialog"'), 'Markierungen: je Markierung Knopf (popovertarget) und Hinweisfenster');
        $this->check(str_contains($h, 'class="cms-hs__pin x12 y100"') && str_contains($h, 'data-x="12.35"'), 'Markierungen: Lage über Klassen und data-x (CSSOM)');
        $this->check(!preg_match('~\sstyle=~', $h), 'Markierungen: keine style-Attribute (CSP)');
        $this->check(1 === substr_count($h, '<ol class="cms-hs__list"') && 3 === preg_match_all('~<(polygon|rect|circle) class="cms-hs__area"~', $h), 'Markierungen: Liste und Flächen als SVG');
    }

    // ------------------------------------------------------------------ Video-Links

    private function videoLinks(): void
    {
        $p = VideoLinks::parse(...);
        $this->check(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'] === $p('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=3')
            && ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'] === $p('https://youtu.be/dQw4w9WgXcQ') && ['provider' => 'vimeo', 'id' => '76979871'] === $p('https://vimeo.com/76979871')
            && null === $p('https://www.youtube.com/channel/x') && null === $p('https://example.org/watch?v=dQw4w9WgXcQ'), 'Video-Links: Erkennung nur von Video-Seiten');
        $html = '<p><a href="https://youtu.be/dQw4w9WgXcQ">V</a> <a href="https://example.org/">E</a></p><script>"<a href=\"https://youtu.be/dQw4w9WgXcQ\">"</script></body>';
        $prev = VideoLinks::$force;
        try {
            VideoLinks::$force = false;
            $this->check($html === VideoLinks::inject($html), 'Video-Links: ausgeschaltet → Ausgabe unverändert');
            VideoLinks::$force = true;
            $out = VideoLinks::enhance($html);
            $this->check(str_contains($out, '<a href="https://youtu.be/dQw4w9WgXcQ" data-video-lb="youtube" data-video-id="dQw4w9WgXcQ">') && 1 === substr_count($out, 'data-video-lb'), 'Video-Links: Kennzeichnung, nicht im Skript, nicht bei anderen Links');
            $this->check(str_contains(VideoLinks::optOut('<a class="btn" href="https://vimeo.com/76979871">x</a>', ['button_link' => 'https://vimeo.com/76979871', 'button_link_nolb' => true]), 'data-video-lb="off"'), 'Video-Links: Ausnahme je Link-Feld');
            $fields = VideoLinks::blockFields([['name' => 'button_link', 'type' => 'link'], ['name' => 'items', 'type' => 'repeater', 'fields' => [['name' => 'link', 'type' => 'link']]]]);
            $this->check('button_link_nolb' === ($fields[1]['name'] ?? null) && 'link_nolb' === ($fields[2]['fields'][1]['name'] ?? null), 'Video-Links: Zusatzfeld hinter jedem Link-Feld');
        } finally {
            VideoLinks::$force = $prev;
        }
    }

    // ------------------------------------------------------------------ Termine

    private function ics(): void
    {
        $a = Data\Ics::event(['uid' => 'x@example.org', 'start' => '2026-12-03', 'title' => 'Termin; A, B', 'text' => "Zeile 1\nZeile 2", 'place' => 'Online']);
        $this->check(str_contains($a, "DTSTART;VALUE=DATE:20261203\r\nDTEND;VALUE=DATE:20261204\r\n") && str_contains($a, 'SUMMARY:Termin\; A\, B') && str_contains($a, 'DESCRIPTION:Zeile 1\nZeile 2'),
            'Termine: ganztägig, Ende exklusiv, Text maskiert');
        $b = Data\Ics::event(['uid' => 'y', 'start' => '2026-12-03T18:30:00+01:00', 'title' => str_repeat('Ä', 60)]);
        $this->check(str_contains($b, "DTSTART:20261203T173000Z\r\nDTEND:20261203T183000Z"), 'Termine: mit Uhrzeit in UTC, eine Stunde');
        $ok = true;
        foreach (explode("\r\n", $b) as $line) {
            $ok = $ok && strlen($line) <= 75 && false !== mb_check_encoding($line, 'UTF-8');
        }
        $this->check($ok && str_starts_with($b, "BEGIN:VCALENDAR\r\n") && str_ends_with($b, "END:VCALENDAR\r\n"), 'Termine: Zeilen höchstens 75 Bytes, UTF-8 nicht geteilt');
    }

    // ------------------------------------------------------------------ Scrollytelling

    private function scrolly(): void
    {
        $this->check(fluid_is_plain_text("Zeile 1\nZeile 2 <3") && !fluid_is_plain_text('<p>x</p>'), 'Scrollytelling: alter Klartext bleibt Klartext');
        $this->check('<h4>A</h4><p>x</p><h4>B</h4>' === fluid_rich_below('<h2>A</h2><p>x</p><h4>B</h4>', 3), 'Scrollytelling: Zwischenüberschriften unter dem Abschnittstitel (h3 → h4)');
    }
}
