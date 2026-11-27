<?php

use Redaxo\Core\Translation\I18n;

/*
 * Kit „NativeCSS“ – inherits from kit fluid ($parent). Own page frame (template.view.php) and own views for
 * hero, cards, text + image, call to action and FAQ with the NativeCSS components; all other blocks come
 * from fluid and adopt colors, font, radii and shadows via the token bridge (assets/css/native.css).
 *
 * NativeCSS: https://github.com/skerbis/nativecss (MIT) – unchanged in assets/ncss/ (version: assets/ncss/VERSION.txt).
 */

// Defaults in NativeCSS style: deep blue, navy, blue-tinted surfaces, system font (no download).
// Light and dark checked against WCAG 2.2 AA (accent/background 8.9:1, text/background 15:1, secondary text/surface 6:1).
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
// „Kopf & Fuß“ belongs to the page frame of fluid – this kit's frame comes from NativeCSS
// „Dichte des Fußbereichs“ stays (group „Form & Raum“; CSS in native.css)
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

$t = static fn (string $key): string => I18n::rawMsg('fluid_rich_' . $key);

return [
    'label' => 'NativeCSS',
    // Text formats: those of fluid plus the lists and buttons of NativeCSS (components/lists.css, helpers/forms.css)
    'text' => [
        'headings' => ['ncss-text-gradient-brand' => $t('ncss_gradient')],
        'lists' => ['ncss-list--dot' => ['label' => $t('ncss_dot'), 'list' => 'ul'], 'ncss-list--dash' => ['label' => $t('ncss_dash'), 'list' => 'ul'],
            'ncss-list--divided' => $t('ncss_divided'), 'ncss-list--steps' => ['label' => $t('ncss_steps'), 'list' => 'ol']],
        'links' => ['btn btn--primary' => false, 'btn btn--secondary' => false,
            'ncss-btn ncss-btn--primary' => $t('btn'), 'ncss-btn ncss-btn--secondary' => $t('btn_secondary'), 'ncss-btn ncss-btn--ghost' => $t('ncss_ghost')],
    ],
    // Bridge design bar → NativeCSS and styling of frame and blocks (layer „native“)
    'css' => ['css/native.css'],
    'design' => $design,
    // Options with their own stylesheet from fluid that also apply here (not header/footer – own frame)
    'design_css' => array_values(array_diff((array) $parent['design_css'], ['header', 'footer', 'footer_density'])),
    // Hero, cards, text + image, call to action, FAQ, key figures, logos (marquee), scrollytelling, video & audio,
    // before/after: own views with NativeCSS – their CSS is in native.css.
    // Variants without own view (hero „Karten“, „Skala“, „Collage“, „Vergleich“, cards „Band“) use those of fluid.
    // Reveal effects for sections (section → „Einblend-Effekt“, default „Keiner“) – .ncss-reveal of NativeCSS, once
    // on scrolling into view; „Bewegung reduzieren“ shows the section at once (NativeCSS CSS), „Dezente Animationen“ off in
    // the design turns all off (Block::revealOption()). On pages with effect: css/ncss-reveal.css and ncss/js/reveal.js (@reveal).
    'reveal' => [
        'fade' => ['label' => 'Sanft einblenden', 'class' => 'ncss-reveal ncss-reveal--fade ncss-reveal--once', 'attr' => 'data-ncss-reveal'],
        'rise' => ['label' => 'Von unten', 'class' => 'ncss-reveal ncss-reveal--slide-up ncss-reveal--once', 'attr' => 'data-ncss-reveal'],
        'circle' => ['label' => 'Kreis', 'class' => 'ncss-reveal ncss-reveal--circle ncss-reveal--once', 'attr' => 'data-ncss-reveal'],
        'curtain' => ['label' => 'Vorhang', 'class' => 'ncss-reveal ncss-reveal--split ncss-reveal--once', 'attr' => 'data-ncss-reveal'],
    ],
    // Additional display only in this kit (missing from the select in kit fluid; saved blocks show „Abwechselnd“ there)
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
        // NativeCSS only on pages that need it (not in css/ncss-base.css, see Kit\Native\NcssUpdate::ON_DEMAND)
        // Before/after and scrollytelling come from NativeCSS here – script and CSS of fluid only for its own output
        'css/hx-compare.css' => ['hero:compare'], 'js/compare.js' => ['hero:compare'], 'js/scrolly.js' => [],
        'css/ncss-reveal.css' => ['@reveal'], 'ncss/js/reveal.js' => ['@reveal'],
        'css/ncss-morph.css' => ['media_text:@morph', 'video:@morph'],
        // Round 2: tables, filter, timeline, testimonials, pricing table, price list, language switch from 3 languages –
        // not the kit fluid's own files for this (css/tables.css, css/filter.css)
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
        // Shared core of both players (from NativeCSS 1.4) – before the players so it is loaded only once
        'ncss/js/media-core.js' => ['video:@player', 'video:audio'],
        'css/ncss-video-player.css' => ['video:@player'], 'ncss/js/video-player.js' => ['video:@player'],
        'css/ncss-audio-player.css' => ['video:audio'], 'ncss/js/audio-player.js' => ['video:audio'],
        // Background video: toggle and script of fluid (css/video-toggle.css, js/hero-video.js via @videotoggle) instead of
        // js/video-bg.js of NativeCSS (its toggle is a text button without icon)
        'css/ncss-video-bg.css' => [], 'ncss/js/video-bg.js' => [],
        'css/hero-x.css' => ['hero:cards'],
        'css/b-cards.css' => ['cards:reel'],
        'css/b-media-text.css' => ['media_text:split', 'media_text:overlap'],
        'css/b-cta.css' => ['cta:split', 'cta:big'],
        'css/b-faq.css' => [],
    ],
];
