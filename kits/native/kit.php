<?php
/*
 * Kit „NativeCSS“ – erbt vom Kit fluid ($parent). Eigener Seitenrahmen (template.view.php) und eigene Views für
 * Einstieg, Karten, Text + Bild, Handlungsaufruf und FAQ mit den Komponenten von NativeCSS; alle übrigen Blöcke kommen
 * von fluid und übernehmen über die Token-Brücke (assets/css/native.css) Farben, Schrift, Radien und Schatten.
 *
 * NativeCSS: https://github.com/skerbis/nativecss (MIT) – unverändert unter assets/ncss/ (Stand: assets/ncss/VERSION.txt).
 */

// Standardwerte im Stil von NativeCSS: tiefes Blau, Navy, blaugetönte Flächen, Systemschrift (kein Download).
// Hell und dunkel nach WCAG 2.2 AA geprüft (Akzent/Grund 8,9:1, Schrift/Grund 15:1, Nebentext/Fläche 6:1).
$colorKeys = ['accent', 'accent_strong', 'on_accent', 'highlight', 'ink', 'text', 'muted', 'background', 'surface', 'line', 'dark_section'];
$light = ['#154A86', '#0F3766', '#FFFFFF', '#DCEBFF', '#0D1726', '#1F2633', '#535B68', '#FFFFFF', '#EAF3FF', '#D7E3F3', '#0B1C33'];
$dark = ['#8DB6EE', '#B5D0F6', '#0A1628', '#1E3A5F', '#EEF2F8', '#D3DAE5', '#A3AFC0', '#0B1626', '#13233A', '#2A3B57', '#132B4A'];
$defaults = array_combine($colorKeys, $light) + [
    'secondary' => '#314164', 'on_secondary' => '#FFFFFF',
    'font_body' => 'system', 'font_head' => 'system', 'heading_weight' => 700, 'heading_tracking' => -1,
    'radius' => 12, 'buttons' => 'solid', 'cards' => 'outlined', 'shadow' => 'soft', 'eyebrow' => 'plain',
];
$darkDefaults = array_combine($colorKeys, $dark) + ['secondary' => '#9DB0D8', 'on_secondary' => '#0A1628'];

$design = $parent['design'];
// „Kopf & Fuß“ gehört zum Seitenrahmen von fluid – der Rahmen dieses Kits kommt aus NativeCSS
// „Dichte des Fußbereichs“ bleibt (Gruppe „Form & Raum“; CSS in native.css)
$footerDensity = array_values(array_filter(array_merge(...array_map(static fn (array $g) => (array) ($g['tokens'] ?? []), $design['groups'])), static fn (array $t) => 'footer_density' === ($t['name'] ?? '')));
$design['groups'] = array_values(array_filter($design['groups'], static fn (array $g) => 'navigation' !== ($g['id'] ?? '')));
foreach ($design['groups'] as &$g) {
    if ('form' === ($g['id'] ?? '')) {
        $g['tokens'] = [...$g['tokens'], ...$footerDensity];
    }
}
unset($g);
foreach ($design['groups'] as &$group) {
    foreach ($group['tokens'] as &$token) {
        $name = (string) ($token['name'] ?? '');
        if (array_key_exists($name, $defaults)) {
            $token['default'] = $defaults[$name];
        }
        if (array_key_exists($name, $darkDefaults) && 'color' === ($token['type'] ?? '')) {
            $token['dark'] = $darkDefaults[$name];
        }
    }
    unset($token);
}
unset($group);

$values = static function (array $l, array $d, array $rest) use ($colorKeys): array {
    $v = array_combine($colorKeys, $l);
    foreach ($colorKeys as $i => $k) {
        $v[$k . '@dark'] = $d[$i];
    }

    return $v + $rest;
};
$design['presets'] = [
    'native' => ['label' => 'NativeCSS', 'description' => 'Tiefes Blau, Navy, blaugetönte Flächen und die Systemschrift des Geräts – wie die NativeCSS-Website.',
        'values' => $values($light, $dark, ['secondary' => '#314164', 'on_secondary' => '#FFFFFF', 'secondary@dark' => '#9DB0D8', 'on_secondary@dark' => '#0A1628',
            'font_body' => 'system', 'font_head' => 'system', 'heading_weight' => 700, 'heading_tracking' => -1, 'radius' => 12, 'eyebrow' => 'plain'])],
    'native-graphite' => ['label' => 'NativeCSS · Graphit', 'description' => 'Gedämpftes Grau als Marke, Navy als Zweitfarbe, Inter – sachlich und ruhig.',
        'values' => $values(
            ['#4A4A49', '#2F2F2E', '#FFFFFF', '#E7ECF3', '#111111', '#1F1F1F', '#55595F', '#FFFFFF', '#F2F4F7', '#DDE2E8', '#1B2233'],
            ['#C9C9C8', '#E2E2E1', '#111111', '#2E3440', '#F2F3F5', '#D6D8DC', '#A7ABB2', '#121417', '#1B1E23', '#2C3038', '#20283A'],
            ['secondary' => '#314164', 'on_secondary' => '#FFFFFF', 'secondary@dark' => '#9DB0D8', 'on_secondary@dark' => '#0A1628',
                'font_body' => 'inter', 'font_head' => 'inter', 'heading_weight' => 650, 'heading_tracking' => -2, 'radius' => 8, 'eyebrow' => 'plain'])],
] + $design['presets'];

return [
    'label' => 'NativeCSS',
    // Brücke Design-Leiste → NativeCSS und Gestaltung von Rahmen und Blöcken (Layer „native“)
    'css' => ['css/native.css'],
    'design' => $design,
    // Optionen mit eigenem Stylesheet von fluid, die auch hier wirken (Kopf/Fuß nicht – eigener Rahmen)
    'design_css' => array_values(array_diff((array) $parent['design_css'], ['header', 'footer', 'footer_density'])),
    // Einstieg, Karten, Text + Bild, Handlungsaufruf, FAQ, Kennzahlen, Logos (Laufband), Scrollytelling, Video & Audio,
    // Vorher/Nachher: eigene Views mit NativeCSS – deren CSS steht in native.css.
    // Varianten ohne eigene View (Einstieg „Karten“, „Skala“, „Collage“, „Vergleich“, Karten „Band“) nutzen die von fluid.
    // Einblend-Effekte für Abschnitte (Abschnitt → „Einblend-Effekt“, Standard „Keiner“) – .ncss-reveal von NativeCSS, einmal
    // beim Hereinscrollen; „Bewegung reduzieren“ zeigt den Abschnitt sofort (CSS von NativeCSS), „Dezente Animationen“ aus im
    // Design schaltet alle ab (Block::revealOption()). Auf Seiten mit Effekt: css/ncss-reveal.css und ncss/js/reveal.js (@reveal).
    'reveal' => [
        'fade' => ['label' => 'Sanft einblenden', 'class' => 'ncss-reveal ncss-reveal--fade ncss-reveal--once', 'attr' => 'data-ncss-reveal'],
        'rise' => ['label' => 'Von unten', 'class' => 'ncss-reveal ncss-reveal--slide-up ncss-reveal--once', 'attr' => 'data-ncss-reveal'],
        'circle' => ['label' => 'Kreis', 'class' => 'ncss-reveal ncss-reveal--circle ncss-reveal--once', 'attr' => 'data-ncss-reveal'],
        'curtain' => ['label' => 'Vorhang', 'class' => 'ncss-reveal ncss-reveal--split ncss-reveal--once', 'attr' => 'data-ncss-reveal'],
    ],
    // Zusätzliche Darstellung nur in diesem Kit (im Kit fluid fehlt sie in der Auswahl; gespeicherte Blöcke zeigen dort „Abwechselnd“)
    'blocks' => [
        'media_text' => [
            'variants' => ['morph' => 'Morphing beim Scrollen'],
            'variant_help' => ['morph' => 'Das Bild startet groß und rückt beim Scrollen zur Seite, der Text erscheint daneben. Bei „Bewegung reduzieren“ stehen Bild und Text einfach nebeneinander. Beim Bearbeiten wie „Bild rechts“.'],
        ],
        'video' => [
            'variants' => ['morph' => 'Video mit Morphing beim Scrollen'],
            'variant_help' => ['morph' => 'Eigene Videodatei, stumm in Schleife (wie ein Hintergrundvideo, mit Pause-Schalter): startet groß und rückt beim Scrollen zur Seite, der Text daneben erscheint. Bei „Bewegung reduzieren“ nur das Standbild neben dem Text. Beim Bearbeiten wie „Video mit Text daneben“.'],
        ],
    ],
    'assets' => [
        // NativeCSS nur auf Seiten, die es brauchen (nicht in css/ncss-base.css, siehe Kit\Native\NcssUpdate::ON_DEMAND)
        // Vorher/Nachher und Scrollytelling kommen hier von NativeCSS – Skript und CSS von fluid nur für dessen Ausgaben
        'css/hx-compare.css' => ['hero:compare'], 'js/compare.js' => ['hero:compare'], 'js/scrolly.js' => [],
        'css/ncss-reveal.css' => ['@reveal'], 'ncss/js/reveal.js' => ['@reveal'],
        'css/ncss-morph.css' => ['media_text:@morph', 'video:@morph'],
        // Runde 2: Tabellen, Filter, Zeitleiste, Kundenstimmen, Preistabelle, Preisliste, Sprachwahl ab drei Sprachen –
        // die eigenen Dateien des Kits fluid dafür (css/tables.css, css/filter.css) hier nicht
        'css/tables.css' => [], 'css/filter.css' => [],
        'css/ncss-scroll-shadow.css' => ['@table'],
        'css/ncss-table-sort.css' => ['@tablesort'], 'ncss/js/table-sort.js' => ['@tablesort'],
        'css/ncss-filter.css' => ['@filter'], 'ncss/js/filter.js' => ['@filter'],
        'css/ncss-timeline.css' => ['steps:@timeline'],
        'css/ncss-testimonial.css' => ['quote:@testimonial'],
        'css/ncss-pricing.css' => ['pricing:@plans'],
        'css/ncss-leaders.css' => ['pricing:@leaders'],
        'css/ncss-dropdown.css' => ['@dropdown'],
        'css/ncss-count-up.css' => ['stats:@count'], 'ncss/js/count-up.js' => ['stats:@count'],
        'css/ncss-marquee.css' => ['logos:@marquee'], 'ncss/js/marquee.js' => ['logos:@marquee'],
        'css/ncss-scrolly.css' => ['scrolly'], 'ncss/js/scrolly.js' => ['scrolly'],
        'css/ncss-compare.css' => ['compare'], 'ncss/js/compare.js' => ['compare'],
        // Gemeinsamer Kern beider Player (ab NativeCSS 1.4) – vor den Playern, damit er nur einmal geladen wird
        'ncss/js/media-core.js' => ['video:@player', 'video:audio'],
        'css/ncss-video-player.css' => ['video:@player'], 'ncss/js/video-player.js' => ['video:@player'],
        'css/ncss-audio-player.css' => ['video:audio'], 'ncss/js/audio-player.js' => ['video:audio'],
        // Hintergrundvideo: Schalter und Skript von fluid (css/video-toggle.css, js/hero-video.js über @videotoggle) statt
        // js/video-bg.js von NativeCSS (dessen Schalter ist ein Textknopf ohne Symbol)
        'css/ncss-video-bg.css' => [], 'ncss/js/video-bg.js' => [],
        'css/hero-x.css' => ['hero:cards'],
        'css/b-cards.css' => ['cards:reel'],
        'css/b-media-text.css' => ['media_text:split', 'media_text:overlap'],
        'css/b-cta.css' => ['cta:split', 'cta:big'],
        'css/b-faq.css' => [],
    ],
];
