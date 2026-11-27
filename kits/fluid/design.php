<?php

/*
 * Design tokens of the kit „fluid“ for the style editor (from KLXM Studio, MIT) – see Klxm\Fluid\Design.
 * Not implemented in the REDAXO kit yet (hidden, end of file): header „Seitenleiste“.
 *
 * Each token becomes a CSS variable (--f-…) or a class on <html> (hdr-inline, btn-pill, cards-glass, has-dark …).
 * Typography and spacing are fluid: font sizes result from base size (min/max), ratio of the steps and
 * range of the viewport width via clamp() (Utopia principle) – no breakpoints, no media queries.
 * Presets are checked against WCAG 2.2 AA with tools/contrast.php (light and dark):
 *   php kits/fluid/tools/contrast.php
 * Default values must match :root in assets/css/_tokens.css (preset „Fluid Standard“).
 */

use Redaxo\Core\Translation\I18n;

// Labels for the style editor (lang keys fluid_design_*)
$t = static fn (string $key): string => I18n::rawMsg('fluid_design_' . $key);

// Order of the color values in the presets
$colorKeys = ['accent', 'accent_strong', 'on_accent', 'highlight', 'ink', 'text', 'muted', 'background', 'surface', 'line', 'dark_section'];

/** Preset: colors light + dark (11 values each in the order above) and remaining values */
$preset = static function (string $label, string $desc, array $light, array $dark, array $rest) use ($colorKeys): array {
    $values = [];
    foreach ($colorKeys as $i => $k) {
        $values[$k] = $light[$i];
        $values[$k . '@dark'] = $dark[$i];
    }
    return ['label' => $label, 'description' => $desc, 'values' => $values + $rest];
};

$color = static fn (string $name, string $label, string $var, string $light, string $dark, ?array $contrast = null, string $help = '') => array_filter([
    'name' => $name, 'label' => $label, 'type' => 'color', 'var' => $var, 'default' => $light, 'dark' => $dark,
    'contrast' => $contrast, 'help' => $help,
], static fn ($v) => null !== $v && '' !== $v);

// Shared values of the presets (overridden per preset)
$base = [
    'font_body' => 'inter', 'font_head' => 'inter', 'font_mono' => 'system-mono', 'label_font' => 'body',
    'fs_min' => 16, 'fs_max' => 18.5, 'ratio' => 1.25, 'vw_min' => 360, 'vw_max' => 1280,
    'heading_weight' => 650, 'heading_tracking' => -2,
    'radius' => 10, 'buttons' => 'solid', 'cards' => 'outlined', 'shadow' => 'soft', 'density' => 'normal', 'space' => 'normal', 'wrap' => 76,
    'secondary_role' => 'sections', 'heading_case' => 'normal', 'emphasis' => 'accent', 'links' => 'underline',
    'images' => 'radius', 'image_fx' => 'none', 'hover' => 'lift', 'icons' => 'soft', 'icon_pos' => 'top', 'dividers' => 'none', 'motion_style' => 'rise',
    'eyebrow' => 'line', 'header' => 'inline', 'header_sticky' => true, 'header_bg' => 'page', 'nav_style' => 'plain', 'topbar' => 'off',
    'footer' => 'columns', 'footer_density' => 'normal', 'footer_bg' => 'surface', 'sections' => 'flush', 'progress' => false, 'totop' => false, 'footer_line' => 'auto', 'footer_line_color' => 'auto', 'footer_line_width' => 'auto', 'header_height' => 'auto', 'pagebg' => 'plain',
    'logo_size' => 'normal', 'side_width' => 'normal', 'side_tree' => 'branch', 'side_banner' => 'off', 'side_banner_height' => 'normal', 'side_banner_overlay' => 'none', 'palette' => 'brand',
    'motion' => true, 'transition' => 'off', 'dark' => true,
];

$design = [
    'groups' => [
        ['id' => 'farben', 'label' => $t('group_farben'), 'tokens' => [
            $color('accent', $t('accent'), '--f-a', '#4338CA', '#A5B4FC', ['with' => 'background', 'min' => 4.5],
                $t('accent_help')),
            $color('accent_strong', $t('accent_strong'), '--f-a-strong', '#3730A3', '#C7D2FE', ['with' => 'background', 'min' => 4.5],
                $t('accent_strong_help')),
            $color('on_accent', $t('on_accent'), '--f-a-on', '#FFFFFF', '#14123A', ['with' => 'accent', 'min' => 4.5],
                $t('on_accent_help')),
            $color('highlight', $t('highlight'), '--f-a2', '#FDE68A', '#3A3470', ['with' => 'ink', 'min' => 4.5],
                $t('highlight_help')),
            $color('secondary', $t('secondary'), '--f-b', '#0F766E', '#5EEAD4', ['with' => 'background', 'min' => 3],
                $t('secondary_help')),
            $color('on_secondary', $t('on_secondary'), '--f-b-on', '#FFFFFF', '#062B27', ['with' => 'secondary', 'min' => 4.5]),
            $color('ink', $t('ink'), '--f-ink', '#0B0D17', '#F3F4F8', ['with' => 'background', 'min' => 7]),
            $color('text', $t('text'), '--f-text', '#2B2F3A', '#D2D5DE', ['with' => 'background', 'min' => 4.5]),
            $color('muted', $t('muted'), '--f-muted', '#565C6B', '#9EA3B2', ['with' => 'surface', 'min' => 4.5],
                $t('muted_help')),
            $color('background', $t('background'), '--f-bg', '#FFFFFF', '#0B0C14'),
            $color('surface', $t('surface'), '--f-surface', '#F4F5F8', '#141621', ['with' => 'text', 'min' => 4.5],
                $t('surface_help')),
            $color('line', $t('line'), '--f-line', '#E2E4EB', '#272B3A', null, $t('line_help')),
            $color('dark_section', $t('dark_section'), '--f-dark', '#0E1022', '#1A1D33', ['with' => '#FFFFFF', 'min' => 7, 'dark_with' => '#FFFFFF'],
                $t('dark_section_help')),
            // Colorful (palette): three more colors – with accent and second brand color five that alternate in cards, icons,
            // sections and lists. Only for graphics (lines, icons, markers) and as a subtle tint under dark text.
            ['name' => 'palette', 'label' => $t('palette'), 'type' => 'choice', 'class' => 'pal-{value}', 'default' => 'brand',
                'options' => ['brand' => $t('palette_brand'), 'colorful' => $t('palette_colorful')],
                'help' => $t('palette_help')],
            $color('color3', $t('color3'), '--f-c3', '#B45309', '#FCD34D', ['with' => 'background', 'min' => 3],
                $t('color3_help')),
            $color('color4', $t('color4'), '--f-c4', '#BE185D', '#F9A8D4', ['with' => 'background', 'min' => 3]),
            $color('color5', $t('color5'), '--f-c5', '#0369A1', '#7DD3FC', ['with' => 'background', 'min' => 3]),
            ['name' => 'secondary_role', 'label' => $t('secondary_role'), 'type' => 'choice', 'class' => 'sec2-{value}', 'default' => 'sections',
                'options' => ['sections' => $t('secondary_role_sections'), 'details' => $t('secondary_role_details'), 'buttons' => $t('secondary_role_buttons'), 'all' => $t('secondary_role_all')]],
        ]],
        ['id' => 'typo', 'label' => $t('group_typo'), 'tokens' => [
            ['name' => 'font_body', 'label' => $t('font_body'), 'type' => 'font', 'var' => '--f-font', 'default' => 'inter'],
            ['name' => 'font_head', 'label' => $t('font_head'), 'type' => 'font', 'var' => '--f-font-head', 'default' => 'inter'],
            ['name' => 'font_mono', 'label' => $t('font_mono'), 'type' => 'font', 'var' => '--f-font-mono', 'default' => 'system-mono',
                'help' => $t('font_mono_help')],
            ['name' => 'label_font', 'label' => $t('label_font'), 'type' => 'choice', 'var' => '--f-font-label', 'default' => 'body',
                'options' => ['body' => $t('label_font_body'), 'head' => $t('label_font_head'), 'mono' => $t('label_font_mono')],
                'values' => ['body' => 'var(--f-font)', 'head' => 'var(--f-font-head)', 'mono' => 'var(--f-font-mono)']],
            ['name' => 'fs_min', 'label' => $t('fs_min'), 'type' => 'range', 'var' => '--f-fs-min', 'unit' => '',
                'min' => 14, 'max' => 19, 'step' => 0.5, 'default' => 16, 'help' => $t('fs_min_help')],
            ['name' => 'fs_max', 'label' => $t('fs_max'), 'type' => 'range', 'var' => '--f-fs-max', 'unit' => '',
                'min' => 15, 'max' => 23, 'step' => 0.5, 'default' => 18.5],
            ['name' => 'ratio', 'label' => $t('ratio'), 'type' => 'range', 'var' => '--f-ratio', 'unit' => '',
                'min' => 1.125, 'max' => 1.6, 'step' => 0.025, 'default' => 1.25,
                'help' => $t('ratio_help')],
            ['name' => 'vw_min', 'label' => $t('vw_min'), 'type' => 'range', 'var' => '--f-vw-min', 'unit' => '',
                'min' => 320, 'max' => 480, 'step' => 10, 'default' => 360, 'help' => $t('vw_min_help')],
            ['name' => 'vw_max', 'label' => $t('vw_max'), 'type' => 'range', 'var' => '--f-vw-max', 'unit' => '',
                'min' => 960, 'max' => 1800, 'step' => 20, 'default' => 1280, 'help' => $t('vw_max_help')],
            ['name' => 'heading_weight', 'label' => $t('heading_weight'), 'type' => 'range', 'var' => '--f-hw', 'unit' => '',
                'min' => 300, 'max' => 900, 'step' => 50, 'default' => 650, 'help' => $t('heading_weight_help')],
            ['name' => 'heading_case', 'label' => $t('heading_case'), 'type' => 'choice', 'class' => 'hcase-{value}', 'default' => 'normal',
                'options' => ['normal' => $t('opt_normal'), 'upper' => $t('heading_case_upper'), 'smallcaps' => $t('heading_case_smallcaps')]],
            ['name' => 'emphasis', 'label' => $t('emphasis'), 'type' => 'choice', 'class' => 'em-{value}', 'default' => 'accent',
                'options' => ['accent' => $t('emphasis_accent'), 'italic' => $t('emphasis_italic'), 'bold' => $t('emphasis_bold'), 'marker' => $t('emphasis_marker'), 'underline' => $t('emphasis_underline'), 'gradient' => $t('emphasis_gradient'), 'outline' => $t('emphasis_outline')]],
            ['name' => 'links', 'label' => $t('links'), 'type' => 'choice', 'class' => 'ln-{value}', 'default' => 'underline',
                'options' => ['underline' => $t('opt_underline'), 'accent' => $t('links_accent'), 'marker' => $t('links_marker')]],
            ['name' => 'heading_tracking', 'label' => $t('heading_tracking'), 'type' => 'range', 'var' => '--f-track', 'unit' => '',
                'min' => -6, 'max' => 3, 'step' => 0.5, 'default' => -2, 'help' => $t('heading_tracking_help')],
        ]],
        ['id' => 'form', 'label' => $t('group_form'), 'tokens' => [
            ['name' => 'radius', 'label' => $t('radius'), 'type' => 'range', 'var' => '--f-radius', 'unit' => 'px', 'min' => 0, 'max' => 32, 'step' => 1, 'default' => 10],
            ['name' => 'buttons', 'label' => $t('buttons'), 'type' => 'choice', 'class' => 'btn-{value}', 'default' => 'solid', 'preview' => 'radius',
                'options' => ['solid' => $t('buttons_solid'), 'pill' => $t('buttons_pill'), 'outline' => $t('opt_outline'), 'soft' => $t('opt_tinted'), 'sharp' => $t('buttons_sharp'),
                    'gradient' => $t('buttons_gradient'), 'brutal' => $t('opt_brutal')],
                'values' => ['solid' => '8px', 'pill' => '999px', 'outline' => '8px', 'soft' => '12px', 'sharp' => '0', 'gradient' => '999px', 'brutal' => '6px']],
            ['name' => 'cards', 'label' => $t('cards'), 'type' => 'choice', 'class' => 'cards-{value}', 'default' => 'outlined',
                'options' => ['flat' => $t('cards_flat'), 'outlined' => $t('cards_outlined'), 'elevated' => $t('cards_elevated'), 'glass' => $t('cards_glass'),
                    'tinted' => $t('cards_tinted'), 'gradient' => $t('cards_gradient'), 'brutal' => $t('opt_brutal')]],
            ['name' => 'shadow', 'label' => $t('shadow'), 'type' => 'choice', 'var' => '--f-shadow', 'default' => 'soft',
                'options' => ['none' => $t('shadow_none'), 'soft' => $t('shadow_soft'), 'crisp' => $t('shadow_crisp'), 'layered' => $t('shadow_layered')],
                'values' => [
                    'none' => 'none',
                    'soft' => '0 1px 2px var(--f-sh1),0 12px 32px -12px var(--f-sh2)',
                    'crisp' => '4px 4px 0 var(--f-ink)',
                    'layered' => '0 1px 1px var(--f-sh1),0 4px 8px -2px var(--f-sh1),0 24px 48px -16px var(--f-sh2)',
                ]],
            ['name' => 'density', 'label' => $t('density'), 'type' => 'choice', 'var' => '--f-density', 'default' => 'normal',
                'options' => ['compact' => $t('opt_compact'), 'normal' => $t('opt_normal'), 'relaxed' => $t('density_relaxed')], 'values' => ['compact' => '.8', 'normal' => '1', 'relaxed' => '1.2']],
            ['name' => 'space', 'label' => $t('space'), 'type' => 'choice', 'var' => '--f-space', 'default' => 'normal',
                'options' => ['compact' => $t('opt_compact'), 'normal' => $t('opt_normal'), 'airy' => $t('space_airy')], 'values' => ['compact' => '.72', 'normal' => '1', 'airy' => '1.32']],
            ['name' => 'wrap', 'label' => $t('wrap'), 'type' => 'range', 'var' => '--f-wrap', 'unit' => 'rem', 'min' => 56, 'max' => 100, 'step' => 2, 'default' => 76,
                'help' => $t('wrap_help')],
            ['name' => 'eyebrow', 'label' => $t('eyebrow'), 'type' => 'choice', 'class' => 'eb-{value}', 'default' => 'line',
                'options' => ['line' => $t('eyebrow_line'), 'pill' => $t('eyebrow_pill'), 'dot' => $t('eyebrow_dot'), 'plain' => $t('eyebrow_plain')]],
            ['name' => 'images', 'label' => $t('images'), 'type' => 'choice', 'class' => 'img-{value}', 'default' => 'radius',
                'options' => ['radius' => $t('images_radius'), 'sharp' => $t('images_sharp'), 'round' => $t('images_round'), 'arch' => $t('images_arch')]],
            ['name' => 'image_fx', 'label' => $t('image_fx'), 'type' => 'choice', 'class' => 'imgfx-{value}', 'default' => 'none',
                'options' => ['none' => $t('image_fx_none'), 'mono' => $t('image_fx_mono'), 'tint' => $t('image_fx_tint'), 'frame' => $t('image_fx_frame')]],
            ['name' => 'hover', 'label' => $t('hover'), 'type' => 'choice', 'class' => 'hov-{value}', 'default' => 'lift',
                'options' => ['lift' => $t('hover_lift'), 'glow' => $t('hover_glow'), 'zoom' => $t('hover_zoom'), 'none' => $t('hover_none')]],
            ['name' => 'icons', 'label' => $t('icons'), 'type' => 'choice', 'class' => 'ic-{value}', 'default' => 'soft',
                'options' => ['soft' => $t('surface'), 'circle' => $t('icons_circle'), 'outline' => $t('opt_outline'), 'duo' => $t('icons_duo'), 'plain' => $t('icons_plain')]],
            ['name' => 'icon_pos', 'label' => $t('icon_pos'), 'type' => 'choice', 'class' => 'icp-{value}', 'default' => 'top',
                'options' => ['top' => $t('icon_pos_top'), 'inline' => $t('icon_pos_inline'), 'side' => $t('icon_pos_side')]],
            ['name' => 'dividers', 'label' => $t('dividers'), 'type' => 'choice', 'class' => 'div-{value}', 'default' => 'none',
                'options' => ['none' => $t('dividers_none'), 'wave' => $t('dividers_wave'), 'slant' => $t('dividers_slant'), 'curve' => $t('dividers_curve'), 'zigzag' => $t('dividers_zigzag')],
                'help' => $t('dividers_help')],
        ]],
        ['id' => 'navigation', 'label' => $t('group_navigation'), 'tokens' => [
            ['name' => 'header', 'label' => $t('header'), 'type' => 'choice', 'class' => 'hdr-{value}', 'default' => 'inline', 'preview' => 'nav',
                'thumbs' => ['inline' => 'left', 'centered' => 'center', 'split' => 'split', 'floating' => 'floating', 'rail' => 'sidebar', 'sidebar' => 'sidebar', 'minimal' => 'left'],
                'options' => [
                    'inline' => $t('header_inline'),
                    'centered' => $t('header_centered'),
                    'split' => $t('header_split'),
                    'floating' => $t('header_floating'),
                    'rail' => $t('header_rail'),
                    'sidebar' => $t('header_sidebar'),
                    'minimal' => $t('header_minimal'),
                ],
                'help' => $t('header_help')],
            ['name' => 'logo_size', 'label' => $t('logo_size'), 'type' => 'choice', 'class' => 'logo-{value}', 'default' => 'normal',
                'options' => ['normal' => $t('opt_normal'), 'large' => $t('logo_size_large'), 'xl' => $t('logo_size_xl')],
                'help' => $t('logo_size_help')],
            ['name' => 'header_height', 'label' => $t('header_height'), 'type' => 'choice', 'class' => 'hh-{value}', 'default' => 'auto',
                'options' => ['auto' => $t('opt_normal'), 'tall' => $t('header_height_tall'), 'xtall' => $t('header_height_xtall')],
                'help' => $t('header_height_help')],
            ['name' => 'side_width', 'label' => $t('side_width'), 'type' => 'choice', 'class' => 'sw-{value}', 'default' => 'normal',
                'options' => ['narrow' => $t('side_width_narrow'), 'normal' => $t('opt_normal'), 'wide' => $t('side_width_wide')],
                'help' => $t('side_width_help')],
            ['name' => 'side_banner', 'label' => $t('side_banner'), 'type' => 'choice', 'class' => 'sb-{value}', 'default' => 'off',
                'options' => ['off' => $t('side_banner_off'), 'band' => $t('side_banner_band'), 'image' => $t('side_banner_image')],
                'help' => $t('side_banner_help')],
            ['name' => 'side_banner_height', 'label' => $t('side_banner_height'), 'type' => 'choice', 'class' => 'sbh-{value}', 'default' => 'normal',
                'options' => ['normal' => $t('opt_normal'), 'tall' => $t('side_banner_height_tall')], 'help' => $t('side_banner_height_help')],
            ['name' => 'side_banner_overlay', 'label' => $t('side_banner_overlay'), 'type' => 'choice', 'class' => 'sbo-{value}', 'default' => 'none',
                'options' => ['none' => $t('side_banner_overlay_none'), 'soft' => $t('side_banner_overlay_soft'), 'strong' => $t('side_banner_overlay_strong')]],
            ['name' => 'side_tree', 'label' => $t('side_tree'), 'type' => 'choice', 'class' => 'st-{value}', 'default' => 'branch',
                'options' => ['branch' => $t('side_tree_branch'), 'open' => $t('side_tree_open')]],
            ['name' => 'header_sticky', 'label' => $t('header_sticky'), 'type' => 'bool', 'class' => 'hdr-sticky', 'default' => true],
            ['name' => 'header_bg', 'label' => $t('header_bg'), 'type' => 'choice', 'class' => 'hbg-{value}', 'default' => 'page',
                'options' => ['page' => $t('header_bg_page'), 'surface' => $t('surface'), 'accent' => $t('opt_accent'), 'secondary' => $t('secondary'), 'dark' => $t('opt_dark'), 'gradient' => $t('header_bg_gradient')]],
            ['name' => 'nav_style', 'label' => $t('nav_style'), 'type' => 'choice', 'class' => 'nl-{value}', 'default' => 'plain',
                'options' => ['plain' => $t('nav_style_plain'), 'underline' => $t('opt_underline'), 'pill' => $t('nav_style_pill'), 'caps' => $t('nav_style_caps'), 'dot' => $t('nav_style_dot')]],
            ['name' => 'topbar', 'label' => $t('topbar'), 'type' => 'choice', 'class' => 'meta-{value}', 'default' => 'off',
                'options' => ['off' => $t('topbar_off'), 'dark' => $t('opt_dark'), 'accent' => $t('opt_accent'), 'secondary' => $t('secondary'), 'surface' => $t('opt_tinted')],
                'help' => $t('topbar_help')],
            ['name' => 'footer', 'label' => $t('footer'), 'type' => 'choice', 'class' => 'ft-{value}', 'default' => 'columns',
                'options' => ['columns' => $t('footer_columns'), 'simple' => $t('footer_simple'), 'centered' => $t('footer_centered'), 'statement' => $t('footer_statement')]],
            // CSS css/opt-footer_density-compact.css (kit.php → design_css); „Kompakt“ also renders the address on one line
            ['name' => 'footer_density', 'label' => $t('footer_density'), 'type' => 'choice', 'class' => 'fd-{value}', 'default' => 'normal',
                'options' => ['normal' => $t('opt_normal'), 'compact' => $t('footer_density_compact')],
                'help' => $t('footer_density_help')],
            ['name' => 'footer_bg', 'label' => $t('footer_bg'), 'type' => 'choice', 'class' => 'fbg-{value}', 'default' => 'surface',
                'options' => ['surface' => $t('surface'), 'page' => $t('footer_bg_page'), 'dark' => $t('opt_dark'), 'accent' => $t('opt_accent'), 'secondary' => $t('secondary')]],
            ['name' => 'footer_line', 'label' => $t('footer_line'), 'type' => 'choice', 'class' => 'fl-{value}', 'default' => 'auto',
                'options' => ['auto' => $t('footer_line_auto'), 'none' => $t('footer_line_none'), 'line' => $t('footer_line_line'), 'shadow' => $t('footer_line_shadow'),
                    'emboss' => $t('footer_line_emboss'), 'band' => $t('footer_line_band')]],
            ['name' => 'footer_line_color', 'label' => $t('footer_line_color'), 'type' => 'choice', 'class' => 'flc-{value}', 'default' => 'auto',
                'options' => ['auto' => $t('footer_line_color_auto'), 'accent' => $t('opt_accent'), 'secondary' => $t('secondary'), 'ink' => $t('footer_line_color_ink'), 'white' => $t('footer_line_color_white')],
                'help' => $t('footer_line_color_help')],
            ['name' => 'footer_line_width', 'label' => $t('footer_line_width'), 'type' => 'choice', 'class' => 'flw-{value}', 'default' => 'auto',
                'options' => ['auto' => $t('footer_line_width_auto'), 'w1' => $t('footer_line_width_w1'), 'w2' => '2 px', 'w4' => '4 px', 'w6' => '6 px', 'w10' => '10 px']],
            ['name' => 'pagebg', 'label' => $t('pagebg'), 'type' => 'choice', 'class' => 'pagebg-{value}', 'default' => 'plain',
                'options' => ['plain' => $t('pagebg_plain'), 'grid' => $t('pagebg_grid'), 'dots' => $t('pagebg_dots'), 'glow' => $t('pagebg_glow'),
                    'aurora' => $t('pagebg_aurora'), 'noise' => $t('pagebg_noise'), 'lines' => $t('pagebg_lines')]],
            ['name' => 'sections', 'label' => $t('sections'), 'type' => 'choice', 'class' => 'secs-{value}', 'default' => 'flush',
                'options' => ['flush' => $t('sections_flush'), 'inset' => $t('sections_inset')],
                'help' => $t('sections_help')],
            ['name' => 'progress', 'label' => $t('progress'), 'type' => 'bool', 'class' => 'has-progress', 'default' => false,
                'help' => $t('progress_help')],
            ['name' => 'totop', 'label' => $t('totop'), 'type' => 'bool', 'class' => 'has-totop', 'default' => false,
                'help' => $t('totop_help')],
        ]],
        ['id' => 'modus', 'label' => $t('group_modus'), 'tokens' => [
            ['name' => 'motion', 'label' => $t('motion'), 'type' => 'bool', 'class' => 'has-motion', 'default' => true,
                'help' => $t('motion_help')],
            ['name' => 'motion_style', 'label' => $t('motion_style'), 'type' => 'choice', 'class' => 'mo-{value}', 'default' => 'rise',
                'options' => ['rise' => $t('motion_style_rise'), 'fade' => $t('motion_style_fade'), 'scale' => $t('motion_style_scale'), 'blur' => $t('motion_style_blur'), 'clip' => $t('motion_style_clip'), 'slide' => $t('motion_style_slide')]],
            ['name' => 'transition', 'label' => $t('transition'), 'type' => 'choice', 'default' => 'off',
                'options' => ['off' => $t('transition_off'), 'fade' => $t('transition_fade'), 'morph' => $t('transition_morph')],
                'help' => $t('transition_help')],
            ['name' => 'dark', 'label' => $t('dark'), 'type' => 'bool', 'class' => 'has-dark', 'default' => true],
        ]],
    ],

    // Fonts: 'fontsource' = installed by the font manager (Core\Fonts, fonts:sync; self-hosted, no external requests) – variable fonts: one download for all weights
    'fonts' => [
        'inter' => ['label' => $t('font_inter'), 'stack' => 'Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'fontsource' => 'inter'],
        'instrument-sans' => ['label' => $t('font_instrument_sans'), 'stack' => '"Instrument Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'instrument-sans'],
        'bricolage' => ['label' => $t('font_bricolage'), 'stack' => '"Bricolage Grotesque",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'bricolage-grotesque'],
        'dm-sans' => ['label' => $t('font_dm_sans'), 'stack' => '"DM Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'dm-sans'],
        'space-grotesk' => ['label' => $t('font_space_grotesk'), 'stack' => '"Space Grotesk",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'space-grotesk'],
        'fraunces' => ['label' => $t('font_fraunces'), 'stack' => 'Fraunces,ui-serif,Georgia,Cambria,serif', 'fontsource' => 'fraunces', 'styles' => ['normal', 'italic'], 'axis' => 'opsz'],
        'newsreader' => ['label' => $t('font_newsreader'), 'stack' => 'Newsreader,ui-serif,Georgia,Cambria,serif', 'fontsource' => 'newsreader', 'styles' => ['normal', 'italic']],
        'instrument-serif' => ['label' => $t('font_instrument_serif'), 'stack' => '"Instrument Serif",ui-serif,Georgia,Cambria,serif', 'fontsource' => 'instrument-serif', 'styles' => ['normal', 'italic']],
        'open-sans' => ['label' => $t('font_open_sans'), 'stack' => '"Open Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'fontsource' => 'open-sans', 'styles' => ['normal', 'italic']],
        'source-sans' => ['label' => $t('font_source_sans'), 'stack' => '"Source Sans 3",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'source-sans-3', 'styles' => ['normal', 'italic']],
        'lato' => ['label' => $t('font_lato'), 'stack' => 'Lato,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'lato', 'styles' => ['normal', 'italic'], 'variable' => false, 'weights' => [300, 400, 700, 900]],
        'roboto' => ['label' => $t('font_roboto'), 'stack' => 'Roboto,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'roboto', 'styles' => ['normal', 'italic']],
        'roboto-condensed' => ['label' => $t('font_roboto_condensed'), 'stack' => '"Roboto Condensed","Arial Narrow",ui-sans-serif,system-ui,sans-serif', 'fontsource' => 'roboto-condensed', 'styles' => ['normal', 'italic']],
        'pt-sans' => ['label' => $t('font_pt_sans'), 'stack' => '"PT Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'pt-sans', 'styles' => ['normal', 'italic'], 'variable' => false, 'weights' => [400, 700]],
        'poppins' => ['label' => $t('font_poppins'), 'stack' => 'Poppins,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'poppins', 'styles' => ['normal', 'italic'], 'variable' => false, 'weights' => [300, 400, 500, 600, 700, 800]],
        'exo' => ['label' => $t('font_exo'), 'stack' => 'Exo,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'exo', 'styles' => ['normal', 'italic']],
        'manrope' => ['label' => $t('font_manrope'), 'stack' => 'Manrope,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'manrope'],
        'atkinson' => ['label' => $t('font_atkinson'), 'stack' => '"Atkinson Hyperlegible Next","Atkinson Hyperlegible",ui-sans-serif,system-ui,sans-serif', 'fontsource' => 'atkinson-hyperlegible-next', 'styles' => ['normal', 'italic']],
        'nunito' => ['label' => $t('font_nunito'), 'stack' => 'Nunito,ui-rounded,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'nunito', 'styles' => ['normal', 'italic']],
        'roboto-slab' => ['label' => $t('font_roboto_slab'), 'stack' => '"Roboto Slab",ui-serif,Georgia,Cambria,serif', 'fontsource' => 'roboto-slab'],
        'jetbrains-mono' => ['label' => $t('font_jetbrains_mono'), 'stack' => '"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace', 'fontsource' => 'jetbrains-mono'],
        'system' => ['label' => $t('font_system'), 'stack' => 'ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif'],
        'system-mono' => ['label' => $t('font_system_mono'), 'stack' => 'ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace'],
    ],

    // Nine presets for different projects – all checked AA in light and dark (tools/contrast.php)
    'presets' => [
        'standard' => $preset('Fluid Standard', $t('preset_standard_desc'),
            ['#4338CA', '#3730A3', '#FFFFFF', '#FDE68A', '#0B0D17', '#2B2F3A', '#565C6B', '#FFFFFF', '#F4F5F8', '#E2E4EB', '#0E1022'],
            ['#A5B4FC', '#C7D2FE', '#14123A', '#3A3470', '#F3F4F8', '#D2D5DE', '#9EA3B2', '#0B0C14', '#141621', '#272B3A', '#1A1D33'],
            $base),
        // Based on redaxo.org and the core backend (assets/scss/_variables.scss): blue header, blue headings,
        // dark blue #343f50 for text and footer, background #f3f6fb, lines #dfe3e9, Source Sans. The blues are darker shades
        // of REDAXO blue #5b98d7/#4b9ad9 (white on it only 3:1 there) – so header, links and buttons meet WCAG AA.
        'redaxo' => $preset($t('preset_redaxo'), $t('preset_redaxo_desc'),
            ['#2C6AA8', '#1F5A96', '#FFFFFF', '#DBEDF7', '#1D5288', '#343F50', '#596676', '#FFFFFF', '#F3F6FB', '#DFE3E9', '#343F50'],
            ['#8CBDEE', '#B3D4F4', '#0B1A2B', '#1F3A52', '#B8D6F5', '#D3DAE3', '#9AA6B4', '#10161D', '#18202A', '#2A3542', '#1D2835'],
            ['secondary' => '#2B8466', 'on_secondary' => '#FFFFFF', 'secondary@dark' => '#7FD3A8', 'on_secondary@dark' => '#06231A',
                'font_body' => 'source-sans', 'font_head' => 'exo', 'fs_min' => 16.5, 'fs_max' => 19, 'ratio' => 1.25, 'heading_weight' => 600, 'heading_tracking' => 0,
                'radius' => 3, 'buttons' => 'pill', 'cards' => 'outlined', 'shadow' => 'none', 'eyebrow' => 'plain', 'emphasis' => 'accent', 'icons' => 'duo',
                'header' => 'inline', 'header_bg' => 'accent', 'nav_style' => 'plain', 'footer' => 'columns', 'footer_bg' => 'dark', 'pagebg' => 'plain'] + $base),
        'kanzlei' => $preset($t('preset_kanzlei'), $t('preset_kanzlei_desc'),
            ['#1D3A66', '#142B4D', '#FFFFFF', '#EDE3CC', '#0C1526', '#2A3242', '#565F70', '#FFFFFF', '#F5F4F0', '#E4E1D8', '#0F1F38'],
            ['#9BB8EA', '#BED0F2', '#0B1A33', '#3A3322', '#EEF2F8', '#CFD6E2', '#9AA4B5', '#0A0F18', '#121A27', '#253247', '#18263D'],
            ['font_body' => 'inter', 'font_head' => 'newsreader', 'ratio' => 1.2, 'fs_max' => 18, 'heading_weight' => 500, 'heading_tracking' => -1,
                'radius' => 2, 'buttons' => 'sharp', 'cards' => 'outlined', 'shadow' => 'none', 'space' => 'normal', 'wrap' => 72, 'eyebrow' => 'plain',
                'header' => 'centered', 'footer' => 'columns', 'pagebg' => 'plain'] + $base),
        'handwerk' => $preset($t('preset_handwerk'), $t('preset_handwerk_desc'),
            ['#A3380F', '#842C0A', '#FFFFFF', '#F6D46B', '#1E140E', '#3A2E25', '#63554A', '#FFFCF7', '#F6EEE3', '#E8DBC9', '#2A1B12'],
            ['#F4A57E', '#F8C3A6', '#2A1206', '#4A3A12', '#F8F1EA', '#E2D6C9', '#B2A291', '#15100C', '#211912', '#3A2D22', '#2B1F17'],
            ['font_body' => 'dm-sans', 'font_head' => 'bricolage', 'ratio' => 1.3, 'heading_weight' => 750, 'heading_tracking' => -3,
                'radius' => 16, 'buttons' => 'pill', 'cards' => 'flat', 'shadow' => 'soft', 'wrap' => 78, 'eyebrow' => 'pill',
                'header' => 'floating', 'footer' => 'statement', 'pagebg' => 'dots'] + $base),
        'praxis' => $preset($t('preset_praxis'), $t('preset_praxis_desc'),
            ['#2C6A5C', '#215347', '#FFFFFF', '#D5ECDD', '#0E1D19', '#2B3935', '#56655F', '#FFFFFF', '#F2F7F4', '#DEE8E3', '#0F2922'],
            ['#86D1BC', '#A9E0D0', '#06231B', '#1F3D33', '#EEF6F2', '#CCDCD5', '#96AAA2', '#0A110F', '#111B18', '#22322D', '#17302A'],
            ['font_body' => 'instrument-sans', 'font_head' => 'instrument-sans', 'ratio' => 1.2, 'fs_max' => 19, 'heading_weight' => 600, 'heading_tracking' => -1.5,
                'radius' => 20, 'buttons' => 'pill', 'cards' => 'elevated', 'shadow' => 'soft', 'density' => 'relaxed', 'eyebrow' => 'dot',
                'header' => 'inline', 'footer' => 'columns', 'pagebg' => 'glow'] + $base),
        'agentur' => $preset($t('preset_agentur'), $t('preset_agentur_desc'),
            ['#5B21D6', '#4A18B3', '#FFFFFF', '#D9F95B', '#0A0A0F', '#26262E', '#55555F', '#FFFFFF', '#F4F3F7', '#E3E1EA', '#120A26'],
            ['#C4AEFF', '#DACDFF', '#1A0B45', '#2F3A0C', '#F6F5FA', '#D6D4DE', '#A09EAB', '#09090D', '#13121A', '#2A2833', '#1E1438'],
            ['font_body' => 'inter', 'font_head' => 'bricolage', 'font_mono' => 'jetbrains-mono', 'label_font' => 'mono', 'ratio' => 1.45, 'fs_max' => 19, 'heading_weight' => 800, 'heading_tracking' => -4.5,
                'radius' => 24, 'buttons' => 'pill', 'cards' => 'outlined', 'shadow' => 'crisp', 'wrap' => 84, 'eyebrow' => 'pill',
                'header' => 'split', 'footer' => 'statement', 'pagebg' => 'grid'] + $base),
        'verein' => $preset($t('preset_verein'), $t('preset_verein_desc'),
            ['#0B5CAD', '#084A8C', '#FFFFFF', '#FFD84A', '#0A1626', '#283445', '#566273', '#FFFFFF', '#F2F6FB', '#DCE5F0', '#0A2540'],
            ['#8CC2FF', '#B4D7FF', '#04203F', '#3F3510', '#EEF4FB', '#CFDAE7', '#98A7BA', '#09101A', '#111A26', '#233247', '#152B47'],
            ['font_body' => 'dm-sans', 'font_head' => 'dm-sans', 'ratio' => 1.28, 'heading_weight' => 800, 'heading_tracking' => -2.5,
                'radius' => 14, 'buttons' => 'solid', 'cards' => 'elevated', 'shadow' => 'layered', 'eyebrow' => 'pill',
                'header' => 'inline', 'footer' => 'columns', 'pagebg' => 'plain'] + $base),
        'restaurant' => $preset($t('preset_restaurant'), $t('preset_restaurant_desc'),
            ['#8A1C32', '#6E1627', '#FFFFFF', '#EBCB98', '#26140F', '#3E2A24', '#6A544B', '#FBF6EE', '#F3E9DA', '#E6D6C0', '#2A1113'],
            ['#F0A2B0', '#F6C3CC', '#34060F', '#4A3515', '#F8EEE6', '#E5D5CA', '#B6A195', '#140D0B', '#1F1512', '#382823', '#2E1A1B'],
            ['font_body' => 'instrument-sans', 'font_head' => 'fraunces', 'ratio' => 1.333, 'fs_max' => 19, 'heading_weight' => 450, 'heading_tracking' => -1,
                'radius' => 6, 'buttons' => 'outline', 'cards' => 'flat', 'shadow' => 'none', 'space' => 'airy', 'wrap' => 72, 'eyebrow' => 'plain',
                'header' => 'centered', 'footer' => 'statement', 'pagebg' => 'plain'] + $base),
        'startup' => $preset($t('preset_startup'), $t('preset_startup_desc'),
            ['#8BA6FF', '#B0C3FF', '#070A16', '#1E2B5C', '#F4F6FB', '#C9D0DD', '#939CAE', '#0A0C13', '#121622', '#242B3C', '#1B1542'],
            ['#8BA6FF', '#B0C3FF', '#070A16', '#1E2B5C', '#F4F6FB', '#C9D0DD', '#939CAE', '#07090F', '#10131D', '#212838', '#1B1542'],
            ['font_body' => 'inter', 'font_head' => 'space-grotesk', 'font_mono' => 'jetbrains-mono', 'label_font' => 'mono', 'ratio' => 1.333, 'heading_weight' => 600, 'heading_tracking' => -3,
                'radius' => 12, 'buttons' => 'pill', 'cards' => 'glass', 'shadow' => 'layered', 'wrap' => 80, 'eyebrow' => 'dot',
                'header' => 'floating', 'footer' => 'simple', 'pagebg' => 'grid'] + $base),
        'kultur' => $preset($t('preset_kultur'), $t('preset_kultur_desc'),
            ['#C8102E', '#A00D25', '#FFFFFF', '#FF6A3D', '#0A0A0A', '#262626', '#595959', '#FFFFFF', '#F4F4F2', '#E2E2DE', '#111111'],
            ['#FF8A8A', '#FFB0B0', '#2A0508', '#5A1A0C', '#F6F6F4', '#D9D9D6', '#A3A3A0', '#0B0B0B', '#161616', '#2C2C2C', '#1F1F1F'],
            ['font_body' => 'inter', 'font_head' => 'instrument-serif', 'font_mono' => 'jetbrains-mono', 'label_font' => 'mono', 'ratio' => 1.5, 'fs_max' => 18, 'heading_weight' => 400, 'heading_tracking' => -1,
                'radius' => 0, 'buttons' => 'sharp', 'cards' => 'flat', 'shadow' => 'none', 'space' => 'airy', 'wrap' => 88, 'eyebrow' => 'plain',
                'header' => 'rail', 'footer' => 'statement', 'pagebg' => 'plain'] + $base),
        // Project types from KLXM client projects (social services, associations, health, businesses, councils, portals)
        'sozial' => $preset($t('preset_sozial'), $t('preset_sozial_desc'),
            ['#A93509', '#872A07', '#FFFFFF', '#FDE7C9', '#1C1917', '#3A3430', '#625A54', '#FFFFFF', '#F7F4F1', '#E7E1DB', '#1F2A1A'],
            ['#FDBA74', '#FED7AA', '#3B1406', '#5A3A12', '#F6F2EE', '#D9D2CC', '#A69D96', '#12100E', '#1C1916', '#33302C', '#1E2A1B'],
            ['secondary' => '#3F7D20', 'secondary@dark' => '#86EFAC', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#052E16', 'secondary_role' => 'all',
                'font_body' => 'lato', 'font_head' => 'lato', 'ratio' => 1.22, 'heading_weight' => 700, 'heading_tracking' => -1,
                'radius' => 8, 'buttons' => 'solid', 'cards' => 'elevated', 'shadow' => 'soft', 'eyebrow' => 'line', 'emphasis' => 'marker', 'icons' => 'circle',
                'header' => 'inline', 'nav_style' => 'underline', 'topbar' => 'secondary', 'footer' => 'columns', 'footer_bg' => 'dark', 'pagebg' => 'plain'] + $base),
        'verband' => $preset($t('preset_verband'), $t('preset_verband_desc'),
            ['#880E4F', '#6B0B3E', '#FFFFFF', '#FFE2C6', '#1A1416', '#352C30', '#5E5458', '#FFFFFF', '#F6F2F3', '#E6DDE0', '#2A0A1B'],
            ['#F4A3C8', '#F9C6DC', '#3D0420', '#5A2A12', '#F7F1F3', '#DCD2D6', '#A99BA1', '#120D10', '#1C1519', '#35292F', '#2B0C1D'],
            ['secondary' => '#E8590C', 'secondary@dark' => '#FDBA74', 'on_secondary' => '#1A0A00', 'on_secondary@dark' => '#2A1203', 'secondary_role' => 'details',
                'font_body' => 'roboto', 'font_head' => 'roboto-condensed', 'ratio' => 1.3, 'heading_weight' => 700, 'heading_tracking' => 0, 'heading_case' => 'upper',
                'radius' => 4, 'buttons' => 'solid', 'cards' => 'outlined', 'shadow' => 'none', 'eyebrow' => 'plain', 'dividers' => 'slant',
                'header' => 'inline', 'header_bg' => 'accent', 'nav_style' => 'caps', 'topbar' => 'dark', 'footer' => 'columns', 'footer_bg' => 'dark', 'pagebg' => 'plain'] + $base),
        'gesundheit' => $preset($t('preset_gesundheit'), $t('preset_gesundheit_desc'),
            ['#00618E', '#004E73', '#FFFFFF', '#D7F0FB', '#0B1B24', '#22333D', '#4D5E68', '#FFFFFF', '#F2F7FA', '#D9E6EE', '#0A2A3B'],
            ['#7CC8F0', '#A8DCF6', '#032536', '#13405A', '#EEF6FA', '#CFDFE8', '#97ABB7', '#0A1218', '#111C23', '#22323C', '#0F2B3A'],
            ['secondary' => '#2E7D32', 'secondary@dark' => '#86D98B', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#052E0F', 'secondary_role' => 'details',
                'font_body' => 'atkinson', 'font_head' => 'atkinson', 'fs_min' => 17, 'fs_max' => 19.5, 'ratio' => 1.2, 'heading_weight' => 700, 'heading_tracking' => -0.5,
                'radius' => 12, 'buttons' => 'solid', 'cards' => 'outlined', 'shadow' => 'soft', 'eyebrow' => 'dot', 'emphasis' => 'underline', 'images' => 'round', 'icons' => 'circle', 'icon_pos' => 'side', 'hover' => 'glow',
                'header' => 'inline', 'nav_style' => 'pill', 'topbar' => 'surface', 'footer' => 'columns', 'footer_bg' => 'surface', 'pagebg' => 'plain'] + $base),
        'betrieb' => $preset($t('preset_betrieb'), $t('preset_betrieb_desc'),
            ['#B91C1C', '#991B1B', '#FFFFFF', '#FDE68A', '#111827', '#27303D', '#4B5563', '#FFFFFF', '#F3F4F6', '#E5E7EB', '#111827'],
            ['#FCA5A5', '#FECACA', '#450A0A', '#4A3B0C', '#F3F4F6', '#D1D5DB', '#9CA3AF', '#0B0F17', '#131923', '#283040', '#172030'],
            ['secondary' => '#374151', 'secondary@dark' => '#CBD5E1', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#0F172A', 'secondary_role' => 'sections',
                'font_body' => 'roboto', 'font_head' => 'roboto-slab', 'ratio' => 1.25, 'heading_weight' => 700, 'heading_tracking' => -1,
                'radius' => 2, 'buttons' => 'sharp', 'cards' => 'outlined', 'shadow' => 'none', 'eyebrow' => 'line', 'icons' => 'outline', 'icon_pos' => 'inline', 'images' => 'sharp',
                'header' => 'inline', 'header_bg' => 'dark', 'nav_style' => 'caps', 'topbar' => 'accent', 'footer' => 'columns', 'footer_bg' => 'dark', 'pagebg' => 'plain'] + $base),
        'kommune' => $preset($t('preset_kommune'), $t('preset_kommune_desc'),
            ['#6D28D9', '#5B21B6', '#FFFFFF', '#FDE68A', '#1E1B2E', '#37344A', '#5E5A73', '#FFFFFF', '#F6F4FB', '#E6E2F0', '#24124A'],
            ['#C4B5FD', '#DDD6FE', '#2E1065', '#4A3B0C', '#F5F3FF', '#DAD6EA', '#A6A1BC', '#0F0D18', '#171423', '#2C2840', '#241848'],
            ['secondary' => '#0E7490', 'secondary@dark' => '#67E8F9', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#083344', 'secondary_role' => 'all',
                'font_body' => 'poppins', 'font_head' => 'poppins', 'ratio' => 1.25, 'heading_weight' => 700, 'heading_tracking' => -1.5,
                'radius' => 18, 'buttons' => 'pill', 'cards' => 'elevated', 'shadow' => 'soft', 'eyebrow' => 'pill', 'emphasis' => 'marker', 'images' => 'round', 'icons' => 'circle', 'dividers' => 'wave',
                'header' => 'inline', 'nav_style' => 'pill', 'footer' => 'centered', 'footer_bg' => 'secondary', 'pagebg' => 'plain'] + $base),
        'wissen' => $preset($t('preset_wissen'), $t('preset_wissen_desc'),
            ['#86EFAC', '#BBF7D0', '#052E16', '#14532D', '#F2F5F3', '#CAD3CD', '#93A098', '#0B100D', '#121A15', '#22302A', '#0F1F17'],
            ['#86EFAC', '#BBF7D0', '#052E16', '#14532D', '#F2F5F3', '#CAD3CD', '#93A098', '#090D0B', '#101712', '#1F2B25', '#0F1F17'],
            ['secondary' => '#FBBF24', 'secondary@dark' => '#FBBF24', 'on_secondary' => '#1C1402', 'on_secondary@dark' => '#1C1402', 'secondary_role' => 'details',
                'font_body' => 'source-sans', 'font_head' => 'manrope', 'ratio' => 1.333, 'heading_weight' => 800, 'heading_tracking' => -2.5,
                'radius' => 14, 'buttons' => 'pill', 'cards' => 'glass', 'shadow' => 'layered', 'eyebrow' => 'dot', 'emphasis' => 'gradient', 'motion_style' => 'blur', 'wrap' => 80,
                'header' => 'floating', 'footer' => 'statement', 'footer_bg' => 'page', 'pagebg' => 'glow'] + $base),
        // Networks, education, associations with many members: sidebar with menu tree, large logo, five alternating colors
        'netzwerk' => $preset($t('preset_netzwerk'), $t('preset_netzwerk_desc'),
            ['#B23A0B', '#8F2E09', '#FFFFFF', '#FED7AA', '#1C1917', '#3A302A', '#665950', '#FFFBF6', '#FFF1E4', '#F0DFCF', '#2A1A12'],
            ['#FDBA74', '#FED7AA', '#3B1406', '#5A3A12', '#FBF4EE', '#E2D7CE', '#AE9F94', '#14100D', '#1E1814', '#3A2F28', '#2A1D16'],
            ['secondary' => '#15803D', 'secondary@dark' => '#86EFAC', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#052E16', 'secondary_role' => 'sections',
                'color3' => '#7C3AED', 'color3@dark' => '#C4B5FD', 'color4' => '#DB2777', 'color4@dark' => '#F9A8D4', 'color5' => '#2563EB', 'color5@dark' => '#93C5FD',
                'palette' => 'colorful',
                'font_body' => 'nunito', 'font_head' => 'nunito', 'ratio' => 1.25, 'fs_max' => 19, 'heading_weight' => 800, 'heading_tracking' => -1.5,
                'radius' => 18, 'buttons' => 'pill', 'cards' => 'elevated', 'shadow' => 'soft', 'eyebrow' => 'pill', 'emphasis' => 'marker', 'icons' => 'circle', 'wrap' => 72,
                'header' => 'sidebar', 'header_bg' => 'gradient', 'logo_size' => 'xl', 'side_width' => 'normal', 'side_tree' => 'branch', 'side_banner' => 'band', 'side_banner_height' => 'normal', 'nav_style' => 'pill',
                'footer' => 'columns', 'footer_bg' => 'surface', 'pagebg' => 'plain'] + $base),
        // Colors from „Fluid Standard“ or „Agentur“ (AA-checked) – new are shapes, background and motion
        'aurora' => $preset($t('preset_aurora'), $t('preset_aurora_desc'),
            ['#4338CA', '#3730A3', '#FFFFFF', '#FDE68A', '#0B0D17', '#2B2F3A', '#565C6B', '#FFFFFF', '#F4F5F8', '#E2E4EB', '#0E1022'],
            ['#A5B4FC', '#C7D2FE', '#14123A', '#3A3470', '#F3F4F8', '#D2D5DE', '#9EA3B2', '#0B0C14', '#141621', '#272B3A', '#1A1D33'],
            ['secondary' => '#0F766E', 'secondary@dark' => '#5EEAD4', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#042F2E',
                'font_body' => 'manrope', 'font_head' => 'manrope', 'ratio' => 1.3, 'fs_max' => 18, 'heading_weight' => 750, 'heading_tracking' => -3,
                'radius' => 16, 'buttons' => 'gradient', 'cards' => 'gradient', 'shadow' => 'soft', 'eyebrow' => 'pill', 'emphasis' => 'gradient', 'wrap' => 76,
                'hover' => 'zoom', 'nav_style' => 'dot', 'motion_style' => 'clip', 'sections' => 'inset', 'progress' => true, 'totop' => true,
                'header' => 'floating', 'footer' => 'columns', 'footer_line' => 'band', 'footer_line_width' => 'w2', 'pagebg' => 'aurora'] + $base),
        'brutal' => $preset($t('preset_brutal'), $t('preset_brutal_desc'),
            ['#5B21D6', '#4A18B3', '#FFFFFF', '#D9F95B', '#0A0A0F', '#26262E', '#55555F', '#FFFFFF', '#F4F3F7', '#E3E1EA', '#120A26'],
            ['#C4AEFF', '#DACDFF', '#1A0B45', '#2F3A0C', '#F6F5FA', '#D6D4DE', '#A09EAB', '#09090D', '#13121A', '#2A2833', '#1E1438'],
            ['font_body' => 'space-grotesk', 'font_head' => 'space-grotesk', 'font_mono' => 'jetbrains-mono', 'label_font' => 'mono', 'ratio' => 1.4, 'fs_max' => 19, 'heading_weight' => 700, 'heading_tracking' => -2.5,
                'radius' => 6, 'buttons' => 'brutal', 'cards' => 'brutal', 'shadow' => 'crisp', 'eyebrow' => 'pill', 'emphasis' => 'outline', 'wrap' => 80,
                'nav_style' => 'caps', 'motion_style' => 'slide', 'totop' => true,
                'header' => 'inline', 'footer' => 'statement', 'footer_line' => 'band', 'footer_line_width' => 'w6', 'pagebg' => 'noise'] + $base),
    ],

    // Dark values with „Dunkles Farbschema“ (class has-dark) and device setting; the preview in the editor sets is-dark
    // 'attr': the visitor's choice (<ncss-preferences>, data-theme on <html>) beats the device setting
    'dark' => ['media' => '(prefers-color-scheme: dark)', 'scope' => 'html.has-dark', 'force' => 'is-dark', 'attr' => 'data-theme'],

    // Sample page for the preview in the editor (showcase, see tools/demo.php)
    'sample' => 'showcase',
];

// REDAXO kit: hide settings without matching markup (header „Seitenleiste“)
$drop = ['side_width', 'side_banner', 'side_banner_height', 'side_banner_overlay', 'side_tree'];
$limit = ['header' => ['inline', 'centered', 'split', 'floating', 'rail', 'minimal'], 'footer' => ['columns', 'simple', 'centered', 'statement']];
foreach ($design['groups'] as &$g) {
    $g['tokens'] = array_values(array_filter($g['tokens'] ?? [], static fn (array $t) => !in_array($t['name'] ?? '', $drop, true)));
    foreach ($g['tokens'] as &$t) {
        if (isset($limit[$t['name']])) {
            $t['options'] = array_intersect_key($t['options'], array_flip($limit[$t['name']]));
        }
    }
    unset($t);
}
unset($g);
foreach ($design['presets'] as &$p) {
    foreach (['header' => 'inline', 'footer' => 'columns'] as $k => $fallback) {
        if (isset($p['values'][$k]) && !in_array($p['values'][$k], $limit[$k], true)) {
            $p['values'][$k] = $fallback;
        }
    }
}
unset($p);

return $design;
