<?php

namespace Klxm\Fluid;

use function count;
use function html_entity_decode;
use function in_array;
use function is_array;
use function is_string;
use function parse_str;
use function parse_url;
use function preg_match;
use function preg_replace_callback;
use function preg_split;
use function Redaxo\Core\View\escape;
use function str_contains;
use function strlen;
use function strripos;
use function strtolower;
use function substr;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;
use const FILTER_VALIDATE_BOOL;
use const PREG_SPLIT_DELIM_CAPTURE;

/**
 * Video-Links in der Lightbox (Vorbild KLXM Studio, Core\VideoLinks): Links auf YouTube- und Vimeo-Videos – Buttons der Blöcke,
 * Rich-Text, Handlungsaufrufe im Kopf und Fuß – öffnen nach Einwilligung den Player in einer Lightbox statt der Seite des Anbieters.
 *
 *  - Website-Einstellung „Video-Links in Lightbox öffnen“ (Settings, `video_lightbox`, Standard aus).
 *  - Aus: Ausgabe unverändert – kein Attribut, kein Skript, keine Zusatzfelder im Editor.
 *  - An: inject() kennzeichnet jeden erkannten Video-Link mit data-video-lb="{anbieter}" + data-video-id="{id}" und bindet
 *    js/video-lightbox.js des Kits ein – nur, wenn die Seite solche Links hat. Das Skript prüft die Einwilligung beim Klick:
 *    AddOn consent_kit (window.ConsentKit.has(dienst), Dienste „youtube“/„vimeo“), sonst die Freigabe „künftig direkt laden“ des
 *    Video-Blocks (js/embed.js). Ohne Einwilligung bleibt der Link ein normaler Link – keine Anfrage an den Anbieter.
 *  - Ausnahme je Button: Zusatzfeld „{feld}_nolb“ hinter jedem Link-Feld (nur bei eingeschalteter Einstellung, Editor auf der
 *    Website), ausgewertet beim Ausgeben des Blocks (optOut); je Link im HTML: data-video-lb="off" am <a>.
 *  Erkannt werden nur Seiten-Links: youtube.com/watch?v=ID, youtube.com/shorts/ID, youtu.be/ID, vimeo.com/ID.
 */
final class VideoLinks
{
    public const string SETTING = 'video_lightbox';
    /** Endung des Zusatzfelds an Link-Feldern der Blöcke: „Video ohne Lightbox öffnen“ */
    public const string SUFFIX = '_nolb';
    public const array PROVIDERS = ['youtube' => 'YouTube', 'vimeo' => 'Vimeo'];

    /** Für Tests: Einstellung erzwingen (null = gespeicherter Wert) */
    public static ?bool $force = null;

    public static function on(): bool
    {
        return self::$force ?? !empty(Settings::all()[self::SETTING] ?? false);
    }

    /**
     * Video eines Links: nur Seiten-Adressen der Anbieter (keine Player- oder Kanal-Links, keine reinen IDs).
     *
     * @return array{provider: string, id: string}|null
     */
    public static function parse(string $href): ?array
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5));
        if (!preg_match('~^https?://~i', $href)) {
            return null;
        }
        $u = parse_url($href);
        if (!is_array($u) || empty($u['host'])) {
            return null;
        }
        $host = strtolower((string) $u['host']);
        $path = (string) ($u['path'] ?? '');
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if ('/watch' === $path) {
                parse_str((string) ($u['query'] ?? ''), $q);
                $id = is_string($q['v'] ?? null) ? $q['v'] : '';

                return preg_match('~^[\w-]{11}$~', $id) ? ['provider' => 'youtube', 'id' => $id] : null;
            }

            return preg_match('~^/shorts/([\w-]{11})/?$~', $path, $m) ? ['provider' => 'youtube', 'id' => $m[1]] : null;
        }
        if ('youtu.be' === $host) {
            return preg_match('~^/([\w-]{11})/?$~', $path, $m) ? ['provider' => 'youtube', 'id' => $m[1]] : null;
        }
        if ('vimeo.com' === $host || 'www.vimeo.com' === $host) {
            return preg_match('~^/(\d{6,12})/?$~', $path, $m) ? ['provider' => 'vimeo', 'id' => $m[1]] : null;
        }

        return null;
    }

    /**
     * Block-Felder (Extension Point FLUID_BLOCK_DEFINITION, nur bei eingeschalteter Einstellung): hinter jedem Link-Feld
     * (auch in Wiederholgruppen) das Zusatzfeld „Video ohne Lightbox öffnen“.
     *
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    public static function blockFields(array $fields): array
    {
        $out = [];
        $names = array_column($fields, 'name');
        foreach ($fields as $f) {
            if ('repeater' === ($f['type'] ?? '') && is_array($f['fields'] ?? null)) {
                $f['fields'] = self::blockFields($f['fields']);
            }
            $out[] = $f;
            $n = (string) ($f['name'] ?? '');
            if ('link' === ($f['type'] ?? '') && '' !== $n && !in_array($n . self::SUFFIX, $names, true)) {
                $out[] = array_filter(['name' => $n . self::SUFFIX, 'label' => 'Video ohne Lightbox öffnen (normaler Link)', 'type' => 'bool',
                    'help' => 'Nur bei Links auf YouTube- oder Vimeo-Videos: öffnet die Seite des Anbieters statt der Lightbox.',
                    'width' => $f['width'] ?? null, 'variants' => $f['variants'] ?? null], static fn ($v) => null !== $v);
            }
        }

        return $out;
    }

    /**
     * Block-Ausgabe: Links auf Videos, deren Link-Feld „Ohne Lightbox öffnen“ trägt, mit data-video-lb="off" kennzeichnen.
     *
     * @param array<array-key, mixed> $data
     */
    public static function optOut(string $html, array $data): string
    {
        if (!self::on() || !str_contains($html, '<a')) {
            return $html;
        }
        $off = self::optedOut($data);
        if (!$off) {
            return $html;
        }

        return preg_replace_callback('~<a\s(?![^>]*\bdata-video-lb=)[^>]*?\bhref="([^"]+)"[^>]*>~i', static function (array $x) use ($off): string {
            $v = self::parse($x[1]);

            return null !== $v && isset($off[$v['provider'] . ':' . $v['id']]) ? substr($x[0], 0, -1) . ' data-video-lb="off">' : $x[0];
        }, $html) ?? $html;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<string, true> „anbieter:id“ der ausgenommenen Video-Links eines Blocks (auch in Wiederholgruppen)
     */
    public static function optedOut(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $out += self::optedOut($v);
                continue;
            }
            if (!is_string($k) || !str_ends_with($k, self::SUFFIX) || !filter_var($v, FILTER_VALIDATE_BOOL)) {
                continue;
            }
            $link = $data[substr($k, 0, -strlen(self::SUFFIX))] ?? null;
            if (is_string($link) && null !== ($p = self::parse($link))) {
                $out[$p['provider'] . ':' . $p['id']] = true;
            }
        }

        return $out;
    }

    /**
     * Erkannte Video-Links kennzeichnen. Ausgenommen: Links mit data-video-lb (z. B. „off“), der Link „Auf … ansehen“ der
     * Zwei-Klick-Lösung (.vembed__ext) und alles in <script>, <template>, <noscript>, <textarea> und <consent-embed>.
     */
    public static function enhance(string $html): string
    {
        if (!str_contains($html, 'youtu') && !str_contains($html, 'vimeo.com')) {
            return $html;
        }
        $parts = preg_split('~(<(script|template|noscript|textarea|consent-embed)\b.*?</\2\s*>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (false === $parts) {
            return $html;
        }
        $out = '';
        for ($i = 0, $n = count($parts); $i < $n; ++$i) {
            if (2 === $i % 3) {
                continue;                          // Name des ausgenommenen Elements (zweite Klammer)
            }
            if (1 === $i % 3) {
                $out .= $parts[$i];                // ausgenommener Bereich unverändert
                continue;
            }
            $out .= preg_replace_callback('~<a\s(?![^>]*\bdata-video-lb=)[^>]*?\bhref="([^"]+)"[^>]*>~i', static function (array $x): string {
                if (preg_match('~\bclass="[^"]*\bvembed__ext\b~', $x[0])) {
                    return $x[0];
                }
                $v = self::parse($x[1]);

                return null === $v ? $x[0] : substr($x[0], 0, -1) . ' data-video-lb="' . $v['provider'] . '" data-video-id="' . escape($v['id']) . '">';
            }, $parts[$i]) ?? $parts[$i];
        }

        return $out;
    }

    /** Seite ausliefern (FluidTemplate::render): Links kennzeichnen und Skript einbinden – bei ausgeschalteter Einstellung unverändert */
    public static function inject(string $html): string
    {
        if (!self::on() || Editing::active()) {
            return $html;
        }
        $html = self::enhance($html);
        if (!preg_match('~data-video-lb="(?:youtube|vimeo)"~', $html) || str_contains($html, 'data-fluid-video-lb')) {
            return $html;
        }
        $kit = Kit::active();
        $tag = '<script src="' . escape($kit->assetUrl('js/video-lightbox.js')) . '" defer data-fluid-video-lb'
            . ' data-css="' . escape($kit->assetUrl('css/video-lightbox.css')) . '"'
            . ' data-close="' . escape(lt('Schließen')) . '" data-video="' . escape(lt('Video')) . '"></script>';
        $pos = strripos($html, '</body>');

        return false === $pos ? $html . $tag : substr($html, 0, $pos) . $tag . substr($html, $pos);
    }
}
