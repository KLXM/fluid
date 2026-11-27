<?php

/*
 * Kit „fluid“ for klxm/fluid – taken from KLXM Studio (MIT): no breakpoints, container queries,
 * fluid type and spacing steps. The blocks are classes with field attributes in src/Module/,
 * their output in views/{type}.php.
 */

use Redaxo\Core\Translation\I18n;

$t = static fn (string $key): string => I18n::rawMsg('fluid_rich_' . $key);
$kitText = static fn (string $key): string => I18n::rawMsg('fluid_kit_' . $key);

return [
    'label' => $kitText('fluid'),
    // Text formats in the text editor, class => label (Klxm\Fluid\TextFormats) – styled in css/prose.css and css/rich-extra.css
    'text' => [
        'paragraphs' => ['t-lead' => $t('t-lead'), 't-small' => $t('t-small'), 't-note' => $t('t-note'), 't-tip' => $t('t-tip'),
            't-warn' => $t('t-warn'), 't-kicker' => $t('t-kicker'), 't-center' => $t('t-center')],
        'headings' => ['h-display' => $t('h-display')],
        'colors' => ['c-accent' => $t('c_accent'), 'c-secondary' => $t('c_secondary'), 'c-muted' => $t('c_muted'),
            'c-success' => $t('c_success'), 'c-warning' => $t('c_warning'), 'c-danger' => $t('c_danger')],
        'lists' => ['check' => ['label' => $t('check'), 'list' => 'ul']],
        'links' => ['btn btn--primary' => $t('btn'), 'btn btn--secondary' => $t('btn_secondary')],
    ],
    // Style editor: colors, fonts, shape, header & footer, presets (Klxm\Fluid\Design)
    'design' => require __DIR__ . '/design.php',
    // Design options with their own stylesheet (only the selected one is loaded): css/opt-{option}-{value}.css
    'design_css' => ['header', 'footer', 'footer_density', 'pagebg', 'buttons', 'eyebrow', 'cards', 'dividers', 'palette', 'sections', 'transition'],
    'backgrounds' => ['white' => $kitText('bg_white'), 'muted' => $kitText('bg_muted'), 'tint' => $kitText('bg_tint'), 'accent' => $kitText('bg_accent'), 'secondary' => $kitText('bg_secondary'), 'dark' => $kitText('bg_dark')],
    'dark_backgrounds' => ['accent', 'secondary', 'dark'],

    // Stylesheets and scripts per block type (or type:variant) – only on pages that need them
    'assets' => [
        'css/hero-x.css' => ['hero:fullbleed', 'hero:cards', 'hero:type'],
        'css/hx-scale.css' => ['hero:scale'], 'css/hx-collage.css' => ['hero:collage'], 'css/hx-compare.css' => ['hero:compare', 'compare'],   // new heroes
        'css/prose.css' => ['richtext', 'tabs', 'faq', 'media_text', '@rich'],
        'css/rich-extra.css' => ['richtext', 'faq', 'media_text'],   // paragraph styles of the formatting bar (tip, warning, eyebrow, centered)   // @rich: rich text styles in other blocks (Core\Sanitizer::styled)
        'css/layout.css' => ['layout'],
        'css/b-article.css' => ['richtext:article', 'richtext:columns'],
        'css/b-features.css' => ['features'],
        'css/b-media-text.css' => ['media_text'],
        'css/b-cta.css' => ['cta'],
        'css/reel.css' => ['cards:reel', 'quote:reel'],
        'css/b-bento.css' => ['bento'],
        'css/b-cards.css' => ['cards'],
        'css/b-logos.css' => ['logos'],
        'css/b-stats.css' => ['stats'],
        'css/b-chart.css' => ['chart'],
        'css/b-media.css' => ['gallery', 'slider'],
        'js/media.js' => ['gallery', 'slider'],
        'css/b-quote.css' => ['quote'],
        'css/b-steps.css' => ['steps'],
        'css/b-pricing.css' => ['pricing'],
        'css/b-faq.css' => ['faq'],
        'css/b-tabs.css' => ['tabs'],
        'css/b-scrolly.css' => ['scrolly'],
        'css/b-contact.css' => ['contact', 'map'],
        'css/pages.css' => ['contact'],
        'css/b-search.css' => ['search'],
        'css/b-video.css' => ['video'],
        'js/embed.js' => ['video'],
        'js/compare.js' => ['hero:compare', 'compare'],
        'css/b-downloads.css' => ['downloads'],
        'css/dataform.css' => ['contact', 'form'],
        'css/b-form.css' => ['form', 'contact'],
        'js/formsteps.js' => ['form'],
        'css/data.css' => ['datalist', 'posts', 'glossary'],
        'css/b-posts.css' => ['posts', '@post', 'datalist'],
        'js/tabs.js' => ['tabs'],
        'css/b-code.css' => ['code'],
        // Tables (scroll shadows, „Spalten sortierbar“) and „Filter nach Kategorie“ – their scripts from NativeCSS
        // (FluidTemplate.view.php, Kit::used('@tablesort' | '@filter'))
        'css/tables.css' => ['@table', '@tablesort'],
        'css/filter.css' => ['@filter'],
        'css/glossary.css' => ['@glossary', 'glossary'], 'js/glossary.js' => ['@glossary', 'glossary'],   // glossary (block and terms in the text)
        'css/knowledge.css' => ['@knowledge', 'datalist:toc'], 'js/knowledge.js' => ['@knowledge'],        // knowledge base
        'js/code.js' => ['code'],
        'js/reel.js' => ['quote', 'cards'],
        'js/scrolly.js' => ['scrolly'],
        // Interactive image (Klxm\Fluid\Hotspots): buttons, popup, list
        'css/hotspots.css' => ['hotspots'],
        'js/hotspots.js' => ['hotspots'],
        // Play/pause toggle of background videos (fluid_video_toggle(), also in kit NativeCSS)
        'css/video-toggle.css' => ['@videotoggle'],
        'js/hero-video.js' => ['@videotoggle'],
    ],
];
