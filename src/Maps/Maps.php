<?php

namespace Klxm\Fluid\Maps;

use Klxm\Fluid\Editing;
use Klxm\Fluid\FluidAddon;
use Klxm\Fluid\Settings;
use Klxm\Routes\Router;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Translation\I18n;

use function array_slice;
use function function_exists;
use function is_array;
use function is_numeric;
use function is_string;
use function Redaxo\Core\View\escape;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const M_PI;

/**
 * Maps with MapLibre GL and OpenFreeMap – entirely via the own proxy (from KLXM Studio – Core\Maps):
 *
 *  - Style, tiles, fonts and symbols come from /proxy/ofm/… – visitors have no contact with third parties,
 *    no consent, no cookies. MapLibre (~800 KB) only loads when the map comes into view.
 *  - Map area: the proxy only delivers detail tiles (from zoom 9) around places that appear as a map on the website.
 *  - Geocoding (address → coordinates) only for logged-in editors, server-side via Nominatim.
 */
final class Maps
{
    public const array STYLES = ['liberty' => 'Liberty (farbig, detailreich)', 'bright' => 'Bright (hell, klassisch)', 'positron' => 'Positron (dezent, grau)', 'dark' => 'Dark (dunkel)', 'fiord' => 'Fiord (dunkelblau)'];
    public const array HEIGHTS = ['s' => 'Niedrig', 'm' => 'Mittel', 'l' => 'Hoch'];
    private const int FREE_ZOOM = 8;
    private const float RADIUS_KM = 25.0;

    private static bool $assets = false;

    /**
     * Choices of the block in the editor's language (the constants above stay German for code that needs constant expressions).
     *
     * @param 'style'|'height' $kind
     * @return array<string, string>
     */
    public static function choices(string $kind): array
    {
        $keys = array_keys('style' === $kind ? self::STYLES : self::HEIGHTS);

        return array_combine($keys, array_map(static fn (string $k): string => I18n::rawMsg('fluid_maps_' . $kind . '_' . $k), $keys));
    }

    /** Geo coordinates of the website in the organization's schema.org node (start page) */
    public static function boot(): void
    {
        Extension::register('FLUID_STRUCTURED_DATA', static function (ExtensionPoint $ep): array {
            $data = (array) $ep->subject;
            $geo = self::siteLocation();
            foreach ((array) ($data['@graph'] ?? []) as $i => $node) {
                if ($geo && ($node['@id'] ?? null) === $ep->getParam('org') && isset($node['address'])) {
                    $data['@graph'][$i]['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $geo[0], 'longitude' => $geo[1]];
                }
            }

            return $data;
        });
    }

    /** Text in the page's language (the kit's lt(), otherwise German) */
    private static function t(string $text, array $vars = []): string
    {
        return function_exists('lt') ? lt($text, $vars) : strtr($text, array_combine(array_map(static fn ($k) => '{' . $k . '}', array_keys($vars)), array_values($vars)) ?: []);
    }

    public static function registerProxy(): void
    {
        Proxy::register('ofm', [
            'label' => 'Karten (OpenFreeMap)', 'upstream' => 'https://tiles.openfreemap.org',
            'allow' => '^(styles/[a-z]+|planet(/[\w.-]+/\d+/\d+/\d+\.pbf)?|sprites/[\w./@-]+\.(json|png)|fonts/[^/]+/\d+-\d+\.pbf|natural_earth/ne2sr/\d+/\d+/\d+\.png)$',
            'rewrite' => true,
            'ttl' => static fn (string $p) => preg_match('~^(styles/|planet$)~', $p) ? 86400 : 30 * 86400,
            'guard' => [self::class, 'allowTile'],
            'max' => 4 * 1024 * 1024,
        ]);
        Proxy::register('nominatim', [
            'label' => 'Adresssuche (OpenStreetMap Nominatim)', 'upstream' => 'https://nominatim.openstreetmap.org',
            'public' => false, 'types' => ['application/json'], 'max' => 512 * 1024,
        ]);
    }

    /** "51.16, 10.45" | ['lat'=>…, 'lng'=>…] → [lat, lng] or null */
    public static function parse(mixed $v): ?array
    {
        if (is_array($v)) {
            $lat = $v['lat'] ?? $v[0] ?? null;
            $lng = $v['lng'] ?? $v['lon'] ?? $v[1] ?? null;
        } elseif (is_string($v) && preg_match('~^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,;]\s*(-?\d{1,3}(?:\.\d+)?)\s*$~', $v, $m)) {
            [$lat, $lng] = [$m[1], $m[2]];
        } else {
            return null;
        }
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }
        $lat = (float) $lat;
        $lng = (float) $lng;
        if (abs($lat) > 85 || abs($lng) > 180 || (0.0 === $lat && 0.0 === $lng)) {
            return null;
        }

        return [round($lat, 6), round($lng, 6)];
    }

    public static function format(?array $p): string
    {
        return $p ? rtrim(rtrim(number_format($p[0], 6, '.', ''), '0'), '.') . ', ' . rtrim(rtrim(number_format($p[1], 6, '.', ''), '0'), '.') : '';
    }

    public static function siteLocation(): ?array
    {
        return self::parse(Settings::get('geo'));
    }

    public static function siteAddress(): string
    {
        return implode(', ', array_filter([trim((string) Settings::get('street', '')), trim(Settings::get('zip', '') . ' ' . Settings::get('city', ''))]));
    }

    // ------------------------------------------------------------------ Kartengebiet (Schutz des Proxys)

    public static function remember(array $p): void
    {
        $areas = (array) FluidAddon::instance()->getConfig('map_areas', []);
        foreach ($areas as $a) {
            if (self::distanceKm($p, $a) < 2) {
                return;
            }
        }
        $areas[] = [$p[0], $p[1]];
        FluidAddon::instance()->setConfig('map_areas', array_slice($areas, -200));
    }

    /** Guard for /proxy/ofm: is the tile within the allowed area? */
    public static function allowTile(string $path): bool
    {
        if (!preg_match('~^planet/[\w.-]+/(\d+)/(\d+)/(\d+)\.pbf$~', $path, $m)) {
            return true;
        }
        [$z, $x, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($z <= self::FREE_ZOOM) {
            return true;
        }
        if ($z > 16) {
            return false;
        }
        $n = 2 ** $z;
        $west = $x / $n * 360 - 180;
        $east = ($x + 1) / $n * 360 - 180;
        $north = rad2deg(atan(sinh(M_PI * (1 - 2 * $y / $n))));
        $south = rad2deg(atan(sinh(M_PI * (1 - 2 * ($y + 1) / $n))));
        foreach ((array) FluidAddon::instance()->getConfig('map_areas', []) as $a) {
            $lat = max($south, min($north, (float) $a[0]));
            $lng = max($west, min($east, (float) $a[1]));
            if (self::distanceKm([$lat, $lng], $a) <= self::RADIUS_KM + 5) {
                return true;
            }
        }

        return false;
    }

    private static function distanceKm(array $a, array $b): float
    {
        $dLat = deg2rad((float) $b[0] - (float) $a[0]);
        $dLng = deg2rad((float) $b[1] - (float) $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad((float) $a[0])) * cos(deg2rad((float) $b[0])) * sin($dLng / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($h)));
    }

    private static function bounds(array $p): array
    {
        $dLat = self::RADIUS_KM / 111.32;
        $dLng = self::RADIUS_KM / (111.32 * max(0.1, cos(deg2rad($p[0]))));

        return [round($p[1] - $dLng, 5), round($p[0] - $dLat, 5), round($p[1] + $dLng, 5), round($p[0] + $dLat, 5)];
    }

    // ------------------------------------------------------------------ Ausgabe

    public static function styleUrl(?string $style = null): string
    {
        $style = $style && isset(self::STYLES[$style]) ? $style : 'liberty';

        return Proxy::url('ofm', 'styles/' . $style);
    }

    /** @param array{point?: mixed, zoom?: int, label?: string, address?: string, height?: string, style?: string, route?: bool, click?: bool, class?: string} $o */
    public static function render(array $o = []): string
    {
        $p = self::parse($o['point'] ?? null) ?? self::siteLocation();
        if (!$p) {
            return Editing::active()
                ? '<div class="cms-map cms-map--empty"><p>' . I18n::msg('fluid_maps_no_location') . '</p></div>'
                : '';
        }
        self::remember($p);
        $label = trim((string) ($o['label'] ?? Settings::name()));
        $address = trim((string) ($o['address'] ?? ''));
        $zoom = max(3, min(18, (int) ($o['zoom'] ?? 15)));
        $height = isset(self::HEIGHTS[$o['height'] ?? '']) ? $o['height'] : 'm';
        $routes = self::routeUrls($p, $zoom);
        $click = (bool) ($o['click'] ?? false);
        $addon = FluidAddon::instance();
        $cfg = [
            'style' => self::styleUrl($o['style'] ?? null), 'styleDark' => self::styleUrl('dark'),
            'center' => [$p[1], $p[0]], 'zoom' => $zoom, 'bounds' => self::bounds($p), 'label' => $label,
            // absolute: map.mjs loads MapLibre via import() – relative URLs would resolve from the module
            'vendor' => self::abs($addon->getAssetsUrl('vendor/maplibre/')), 'css' => self::abs($addon->getAssetsUrl('vendor/maplibre/maplibre-gl.css')),
            'i18n' => ['NavigationControl.ZoomIn' => 'Vergrößern', 'NavigationControl.ZoomOut' => 'Verkleinern', 'NavigationControl.ResetBearing' => 'Nach Norden ausrichten',
                'FullscreenControl.Enter' => 'Vollbild', 'FullscreenControl.Exit' => 'Vollbild beenden', 'AttributionControl.ToggleAttribution' => 'Quellenangaben',
                'CooperativeGesturesHandler.WindowsHelpText' => 'Strg + Mausrad zum Zoomen', 'CooperativeGesturesHandler.MacHelpText' => '⌘ + Mausrad zum Zoomen',
                'CooperativeGesturesHandler.MobileHelpText' => 'Mit zwei Fingern verschieben', 'Map.Title' => 'Karte', 'Marker.Title' => 'Standort'],
            'view3d' => false,
        ];
        $sr = '<span class="sr-only"> ' . escape(self::t('(öffnet in neuem Tab)')) . '</span>';
        $h = '<figure class="cms-map cms-map--' . $height . ($click ? ' cms-map--click' : '') . (!empty($o['class']) ? ' ' . escape($o['class']) : '') . '" data-cms-map="'
            . escape(json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '"' . ($click ? ' data-click' : '') . '>'
            . '<div class="cms-map__canvas" role="region" aria-label="' . escape('Karte: ' . ($label ?: self::format($p))) . '"><span class="cms-map__pin" aria-hidden="true"></span>'
            . ($click ? '<div class="cms-map__consent"><p class="cms-map__note">' . escape(self::t('Die Karte lädt erst nach Klick. Die Kartendaten (OpenStreetMap) holt unser Server – Ihr Browser verbindet sich dabei nicht mit Dritten.')) . '</p>'
                . '<button type="button" class="cms-map__load" data-cms-map-load>' . escape(self::t('Karte anzeigen')) . '</button></div></div>'
                : '<button type="button" class="cms-map__load" data-cms-map-load hidden>' . escape(self::t('Karte anzeigen')) . '</button></div>')
            . '<figcaption class="cms-map__cap"><span>' . ('' !== $label ? '<strong>' . escape($label) . '</strong>' : '') . ('' !== $address ? ('' !== $label ? ' · ' : '') . escape($address) : '') . '</span>';
        if (!empty($o['route'] ?? true)) {
            $h .= '<span class="cms-map__routes"><a class="cms-map__route" href="' . escape($routes['google']) . '" target="_blank" rel="noopener" data-route data-apple="' . escape($routes['apple']) . '">' . escape(self::t('Route planen'))
                . ' <span class="cms-map__via" data-route-via data-apple-name="Apple Karten">' . escape(self::t('mit {name}', ['name' => 'Google Maps'])) . '</span>' . $sr . '</a>'
                . '<details class="cms-map__apps"><summary>' . escape(self::t('Andere Karten-App')) . '</summary><ul>'
                . '<li><a href="' . escape($routes['apple']) . '" target="_blank" rel="noopener">Apple Karten' . $sr . '</a></li>'
                . '<li><a href="' . escape($routes['google']) . '" target="_blank" rel="noopener">Google Maps' . $sr . '</a></li>'
                . '<li><a href="' . escape($routes['osm']) . '" target="_blank" rel="noopener">OpenStreetMap' . $sr . '</a></li></ul></details></span>';
        }
        $h .= '</figcaption></figure>';
        if (!self::$assets) {
            self::$assets = true;
            $h .= '<link rel="stylesheet" href="' . escape($addon->getAssetsUrl('css/map.css')) . '"><script type="module" src="' . escape($addon->getAssetsUrl('js/map.mjs')) . '"></script>';
        }

        return $h;
    }

    private static function abs(string $url): string
    {
        return Router::base() . '/' . ltrim((string) preg_replace('~^\./~', '', $url), '/');
    }

    /** @return array{google: string, apple: string, osm: string} */
    public static function routeUrls(array $p, int $zoom = 15): array
    {
        $ll = $p[0] . ',' . $p[1];

        return [
            'google' => 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($ll),
            'apple' => 'https://maps.apple.com/?daddr=' . rawurlencode($ll) . '&dirflg=d',
            'osm' => 'https://www.openstreetmap.org/directions?to=' . rawurlencode($ll) . '#map=' . $zoom . '/' . $p[0] . '/' . $p[1],
        ];
    }

    public static function renderBlock(array $d, array $extra = []): string
    {
        $site = 'custom' !== ($d['location'] ?? 'site');

        return self::render([
            'point' => $site ? self::siteLocation() : ($d['point'] ?? null),
            'label' => trim((string) ($d['label'] ?? '')) ?: ($site ? Settings::name() : ''),
            'address' => trim((string) ($d['address'] ?? '')) ?: ($site ? self::siteAddress() : ''),
            'zoom' => (int) ($d['zoom'] ?? 15), 'height' => (string) ($d['height'] ?? 'm'), 'style' => (string) ($d['style'] ?? ''),
            'route' => !empty($d['route']), 'click' => !empty($d['click']),
        ] + $extra);
    }

    /** @return list<array{label: string, lat: float, lng: float}> */
    public static function geocode(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 3) {
            return [];
        }
        $res = Proxy::http('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&addressdetails=0&accept-language=de&q=' . rawurlencode($q));
        $rows = $res && 200 === $res['status'] ? json_decode($res['body'], true) : null;
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            if (isset($r['lat'], $r['lon'])) {
                $out[] = ['label' => (string) ($r['display_name'] ?? ''), 'lat' => round((float) $r['lat'], 6), 'lng' => round((float) $r['lon'], 6)];
            }
        }

        return $out;
    }
}
