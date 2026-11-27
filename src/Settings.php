<?php

namespace Klxm\Fluid;

use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;

use function array_key_exists;
use function count;
use function is_array;

/**
 * Website settings (model from KLXM Studio – „Website“): name, logo, contact, opening hours, social media,
 * notice bar, header and footer, legal. Maintained centrally, they appear everywhere automatically (template, blocks
 * „Kontakt“ and „Karte“, structured data). Stored in the addon config ('site'); kits read them via setting().
 *
 * Fields in the kit format (FieldForm) – one group per tab. Domains and redirects have their own tabs (routing).
 */
final class Settings
{
    public const string CONFIG = 'site';

    /** @var array<string, mixed>|null */
    private static ?array $values = null;

    /** @var array<string, array{label: string, fields: list<array<string, mixed>>}>|null */
    private static ?array $schema = null;

    /** @return array<string, array{label: string, fields: list<array<string, mixed>>}> */
    public static function schema(): array
    {
        return self::$schema ??= (array) Extension::dispatch(new ExtensionPoint('FLUID_SETTINGS_SCHEMA', self::baseSchema()));
    }

    /** @return array<string, array{label: string, fields: list<array<string, mixed>>}> */
    private static function baseSchema(): array
    {
        // Tab „Kit“ only if there is more than one (project or Composer packages with #[AsKit])
        $kits = Kit::options();
        $kitTab = count($kits) > 1 ? ['kit' => ['label' => I18n::rawMsg('fluid_settings_tab_kit'), 'fields' => [
            ['name' => 'kit', 'label' => I18n::rawMsg('fluid_settings_kit'), 'type' => 'select', 'required' => true, 'default' => Kit::DEFAULT, 'options' => $kits,
                'help' => I18n::rawMsg('fluid_settings_kit_help'),
            ],
        ]]] : [];

        $days = [];
        foreach (['1' => 'mon', '2' => 'tue', '3' => 'wed', '4' => 'thu', '5' => 'fri', '6' => 'sat', '0' => 'sun'] as $n => $d) {
            $days[$n] = I18n::rawMsg('fluid_settings_day_' . $d);
        }

        return [
            'base' => ['label' => I18n::rawMsg('fluid_settings_tab_base'), 'fields' => [
                ['name' => 'org_name', 'label' => I18n::rawMsg('fluid_settings_org_name'), 'type' => 'text', 'max' => 120, 'required' => true,
                    'help' => I18n::rawMsg('fluid_settings_org_name_help')],
                ['name' => 'short_name', 'label' => I18n::rawMsg('fluid_settings_short_name'), 'type' => 'text', 'max' => 40, 'width' => 'half'],
                ['name' => 'tagline', 'translate' => true, 'label' => I18n::rawMsg('fluid_settings_tagline'), 'type' => 'text', 'max' => 120, 'width' => 'half'],
                ['name' => 'logo', 'label' => I18n::rawMsg('fluid_settings_logo'), 'type' => 'media', 'width' => 'half', 'help' => I18n::rawMsg('fluid_settings_logo_help')],
                ['name' => 'logo_dark', 'label' => I18n::rawMsg('fluid_settings_logo_dark'), 'type' => 'media', 'width' => 'half'],
                ['name' => 'schema_type', 'label' => I18n::rawMsg('fluid_settings_schema_type'), 'type' => 'select', 'default' => 'Organization', 'required' => true,
                    'options' => ['Organization' => I18n::rawMsg('fluid_settings_schema_type_organization'), 'LocalBusiness' => I18n::rawMsg('fluid_settings_schema_type_localbusiness'), 'ProfessionalService' => I18n::rawMsg('fluid_settings_schema_type_professionalservice'),
                        'MedicalBusiness' => I18n::rawMsg('fluid_settings_schema_type_medicalbusiness'), 'Restaurant' => I18n::rawMsg('fluid_settings_schema_type_restaurant'), 'Store' => I18n::rawMsg('fluid_settings_schema_type_store'), 'EducationalOrganization' => I18n::rawMsg('fluid_settings_schema_type_educationalorganization'),
                        'NGO' => I18n::rawMsg('fluid_settings_schema_type_ngo'), 'GovernmentOrganization' => I18n::rawMsg('fluid_settings_schema_type_governmentorganization'), 'Person' => I18n::rawMsg('fluid_settings_schema_type_person')],
                    'help' => I18n::rawMsg('fluid_settings_schema_type_help')],
                ['name' => 'default_meta_description', 'translate' => true, 'label' => I18n::rawMsg('fluid_settings_default_meta_description'), 'type' => 'textarea', 'rows' => 2, 'max' => 300,
                    'help' => I18n::rawMsg('fluid_settings_default_meta_description_help')],
            ]],
            'contact' => ['label' => I18n::rawMsg('fluid_settings_tab_contact'), 'fields' => [
                ['name' => 'street', 'label' => I18n::rawMsg('fluid_settings_street'), 'type' => 'text', 'max' => 120],
                ['name' => 'zip', 'label' => I18n::rawMsg('fluid_settings_zip'), 'type' => 'text', 'max' => 12, 'width' => 'half'],
                ['name' => 'city', 'label' => I18n::rawMsg('fluid_settings_city'), 'type' => 'text', 'max' => 80, 'width' => 'half'],
                ['name' => 'country', 'label' => I18n::rawMsg('fluid_settings_country'), 'type' => 'text', 'max' => 60, 'width' => 'half'],
                ['name' => 'geo', 'label' => I18n::rawMsg('fluid_settings_geo'), 'type' => 'geo', 'width' => 'half',
                    'help' => I18n::rawMsg('fluid_settings_geo_help')],
                ['name' => 'phone', 'label' => I18n::rawMsg('fluid_settings_phone'), 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => I18n::rawMsg('fluid_settings_phone_placeholder')],
                ['name' => 'email', 'label' => I18n::rawMsg('fluid_settings_email'), 'type' => 'text', 'max' => 120, 'width' => 'half'],
                ['name' => 'hours', 'label' => I18n::rawMsg('fluid_settings_hours'), 'type' => 'repeater', 'item_label' => I18n::rawMsg('fluid_settings_hours_item'), 'title_field' => 'tag', 'fields' => [
                    ['name' => 'tag', 'label' => I18n::rawMsg('fluid_settings_hours_tag'), 'type' => 'select', 'options' => $days, 'required' => true, 'width' => 'half'],
                    ['name' => 'notiz', 'label' => I18n::rawMsg('fluid_settings_hours_notiz'), 'type' => 'text', 'max' => 60, 'width' => 'half', 'placeholder' => I18n::rawMsg('fluid_settings_hours_notiz_placeholder')],
                    ['name' => 'von', 'label' => I18n::rawMsg('fluid_settings_hours_von'), 'type' => 'time', 'width' => 'half'],
                    ['name' => 'bis', 'label' => I18n::rawMsg('fluid_settings_hours_bis'), 'type' => 'time', 'width' => 'half'],
                    ['name' => 'pause_von', 'label' => I18n::rawMsg('fluid_settings_hours_pause_von'), 'type' => 'time', 'width' => 'half'],
                    ['name' => 'pause_bis', 'label' => I18n::rawMsg('fluid_settings_hours_pause_bis'), 'type' => 'time', 'width' => 'half'],
                ]],
                ['name' => 'hours_note', 'translate' => true, 'label' => I18n::rawMsg('fluid_settings_hours_note'), 'type' => 'text', 'max' => 160, 'placeholder' => I18n::rawMsg('fluid_settings_hours_note_placeholder')],
                ['name' => 'social', 'label' => I18n::rawMsg('fluid_settings_social'), 'type' => 'repeater', 'item_label' => I18n::rawMsg('fluid_settings_social_item'), 'title_field' => 'label', 'fields' => [
                    ['name' => 'label', 'label' => I18n::rawMsg('fluid_settings_social_label'), 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => I18n::rawMsg('fluid_settings_social_label_placeholder')],
                    ['name' => 'url', 'label' => I18n::rawMsg('fluid_settings_social_url'), 'type' => 'url', 'width' => 'half'],
                ]],
            ]],
            'layout' => ['label' => I18n::rawMsg('fluid_settings_tab_layout'), 'fields' => [
                ['name' => 'header_cta_label', 'translate' => true, 'label' => I18n::rawMsg('fluid_settings_header_cta_label'), 'type' => 'text', 'max' => 32, 'width' => 'half', 'placeholder' => I18n::rawMsg('fluid_settings_header_cta_label_placeholder')],
                ['name' => 'header_cta_link', 'label' => I18n::rawMsg('fluid_settings_header_cta_link'), 'type' => 'link', 'width' => 'half', 'help' => I18n::rawMsg('fluid_settings_header_cta_link_help')],
                ['name' => 'search_page', 'label' => I18n::rawMsg('fluid_settings_search_page'), 'type' => 'link', 'width' => 'half',
                    'help' => I18n::rawMsg('fluid_settings_search_page_help')],
                ['name' => 'topbar_text', 'translate' => true, 'label' => I18n::rawMsg('fluid_settings_topbar_text'), 'type' => 'text', 'max' => 120, 'help' => I18n::rawMsg('fluid_settings_topbar_text_help')],
                ['name' => 'footer_text', 'translate' => true, 'label' => I18n::rawMsg('fluid_settings_footer_text'), 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ['name' => 'footer_statement', 'translate' => true, 'label' => I18n::rawMsg('fluid_settings_footer_statement'), 'type' => 'text', 'max' => 120, 'help' => I18n::rawMsg('fluid_settings_footer_statement_help')],
                // Call to action in the footer (fluid_footer_cta): own button, separate from the header; empty = no button
                ['name' => 'footer_cta_label', 'translate' => true, 'label' => I18n::rawMsg('fluid_settings_footer_cta_label'), 'type' => 'text', 'max' => 32, 'width' => 'half', 'placeholder' => I18n::rawMsg('fluid_settings_footer_cta_label_placeholder'),
                    'help' => I18n::rawMsg('fluid_settings_footer_cta_label_help')],
                ['name' => 'footer_cta_link', 'label' => I18n::rawMsg('fluid_settings_footer_cta_link'), 'type' => 'link', 'width' => 'half', 'help' => I18n::rawMsg('fluid_settings_footer_cta_link_help')],
                ['name' => 'notice_active', 'label' => I18n::rawMsg('fluid_settings_notice_active'), 'type' => 'bool', 'default' => false],
                ['name' => 'notice_text', 'translate' => true, 'label' => I18n::rawMsg('fluid_settings_notice_text'), 'type' => 'text', 'max' => 200, 'placeholder' => I18n::rawMsg('fluid_settings_notice_text_placeholder')],
                ['name' => 'video_lightbox', 'label' => I18n::rawMsg('fluid_settings_video_lightbox'), 'type' => 'bool', 'default' => false,
                    'help' => I18n::rawMsg('fluid_settings_video_lightbox_help')],
                // <ncss-preferences> of NativeCSS in the header – kit „NativeCSS“ and kit fluid (frame of both kits); scripts and
                // panel come from kits/native, so only if that kit exists
                ...(null !== Kit::get('native') ? [
                    ['name' => 'visitor_prefs', 'label' => I18n::rawMsg('fluid_settings_visitor_prefs'), 'type' => 'bool', 'default' => false,
                        'help' => I18n::rawMsg('fluid_settings_visitor_prefs_help')],
                    ['name' => 'visitor_prefs_fonts', 'label' => I18n::rawMsg('fluid_settings_visitor_prefs_fonts'), 'type' => 'bool', 'default' => true,
                        'help' => I18n::rawMsg('fluid_settings_visitor_prefs_fonts_help')],
                ] : []),
            ]],
            ...$kitTab,
            'legal' => ['label' => I18n::rawMsg('fluid_settings_tab_legal'), 'fields' => [
                ['name' => 'imprint_page', 'label' => I18n::rawMsg('fluid_settings_imprint_page'), 'type' => 'link', 'width' => 'half'],
                ['name' => 'privacy_page', 'label' => I18n::rawMsg('fluid_settings_privacy_page'), 'type' => 'link', 'width' => 'half'],
                ['name' => 'accessibility_page', 'label' => I18n::rawMsg('fluid_settings_accessibility_page'), 'type' => 'link', 'width' => 'half'],
                ['name' => 'mail_keep_days', 'label' => I18n::rawMsg('fluid_settings_mail_keep_days'), 'type' => 'number', 'max' => 5, 'width' => 'half', 'placeholder' => (string) Mail\Mail::KEEP_DAYS,
                    'help' => I18n::rawMsg('fluid_settings_mail_keep_days_help')],
                ['name' => 'robots', 'label' => I18n::rawMsg('fluid_settings_robots'), 'type' => 'textarea', 'rows' => 4, 'max' => 2000,
                    'help' => I18n::rawMsg('fluid_settings_robots_help')],
                ['name' => 'robots_previews', 'label' => I18n::rawMsg('fluid_settings_robots_previews'), 'type' => 'bool', 'default' => false,
                    'help' => I18n::rawMsg('fluid_settings_robots_previews_help')],
            ]],
            'editorial' => ['label' => I18n::rawMsg('fluid_settings_tab_editorial'), 'fields' => [
                ['name' => 'edit_lock', 'label' => I18n::rawMsg('fluid_settings_edit_lock'), 'type' => 'bool', 'default' => false,
                    'help' => I18n::rawMsg('fluid_settings_edit_lock_help')],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (null === self::$values) {
            $v = FluidAddon::instance()->getConfig(self::CONFIG, []);
            self::$values = is_array($v) ? $v : [];
        }

        return self::$values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // further languages: translation, otherwise text of the start language
        $lang = Language::getCurrentId();
        if ($lang !== Language::getStartId()) {
            $t = self::translations($lang)[$key] ?? null;
            if (null !== $t && '' !== $t) {
                return $t;
            }
        }
        $all = self::all();

        return array_key_exists($key, $all) && '' !== $all[$key] && null !== $all[$key] ? $all[$key] : $default;
    }

    /** Translated values of a further language (fields with 'translate' => true) */
    public static function translations(int $languageId): array
    {
        $all = FluidAddon::instance()->getConfig(self::CONFIG . '_i18n', []);

        return is_array($all[$languageId] ?? null) ? $all[$languageId] : [];
    }

    /** Fields of a tab that are maintained per language */
    public static function translatable(string $tab): array
    {
        return array_values(array_filter(self::schema()[$tab]['fields'] ?? [], static fn (array $f) => !empty($f['translate'])));
    }

    /** Saves the values of a tab (schema fields only); further language: only the translatable fields */
    public static function save(string $tab, array $input, ?int $languageId = null): void
    {
        $fields = self::schema()[$tab]['fields'] ?? null;
        if (null === $fields) {
            throw new ApiError(I18n::rawMsg('fluid_settings_unknown_tab'));
        }
        if (null !== $languageId && $languageId !== Language::getStartId()) {
            $i18n = FluidAddon::instance()->getConfig(self::CONFIG . '_i18n', []);
            $i18n = is_array($i18n) ? $i18n : [];
            foreach (self::translatable($tab) as $f) {
                $v = $input[$f['name']] ?? '';
                $i18n[$languageId][$f['name']] = is_array($v) ? '' : mb_substr(trim((string) $v), 0, (int) ($f['max'] ?? 2000));
            }
            FluidAddon::instance()->setConfig(self::CONFIG . '_i18n', $i18n);

            return;
        }
        $all = self::all();
        foreach ($fields as $f) {
            $v = $input[$f['name']] ?? ('bool' === $f['type'] ? false : '');
            $all[$f['name']] = match ($f['type']) {
                'bool' => (bool) $v,
                'repeater' => is_array($v) ? array_values(array_filter($v, 'is_array')) : [],
                default => is_array($v) ? '' : mb_substr(trim((string) $v), 0, (int) ($f['max'] ?? 2000)),
            };
            if (!empty($f['required']) && '' === $all[$f['name']]) {
                throw new ApiError(I18n::rawMsg('fluid_settings_required', $f['label']));
            }
            if ('kit' === $f['name'] && !isset(Kit::all()[$all['kit']])) {
                throw new ApiError(I18n::rawMsg('fluid_settings_unknown_kit'));
            }
        }
        FluidAddon::instance()->setConfig(self::CONFIG, $all);
        self::$values = $all;
        Kit::reset();
    }

    /** Name of the website: setting or REDAXO instance name */
    public static function name(bool $short = false): string
    {
        $name = trim((string) self::get('org_name', ''));
        $shortName = trim((string) self::get('short_name', ''));
        if ($short && '' !== $shortName) {
            return $shortName;
        }

        return '' !== $name ? $name : ('' !== $shortName ? $shortName : Core::getProject()->instanceName);
    }
}
