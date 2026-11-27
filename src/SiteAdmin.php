<?php

namespace Klxm\Fluid;

use InvalidArgumentException;
use Klxm\Fluid\Form\FieldForm;
use Klxm\Fluid\Form\Picker;
use Klxm\Fluid\Maps\Maps;
use Klxm\Routes\Domain;
use Klxm\Routes\DomainRole;
use Klxm\Routes\IndexNow;
use Klxm\Routes\Perm as RoutesPerm;
use Klxm\Routes\Redirects;
use Klxm\Routes\Router;
use Klxm\Routes\Routes;
use Klxm\Routes\Shortlinks;
use Klxm\Routes\Site;
use Klxm\Routes\Sites;
use Klxm\Routes\SiteSettings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Language\LanguageHandler;
use Redaxo\Core\Security\User;
use Redaxo\Core\Translation\I18n;
use Throwable;

use function count;
use function in_array;
use function Redaxo\Core\View\escape;

/**
 * Sidebar „Website“ on the website: tabs of the website settings (Settings::schema, admins), websites and domains
 * (klxm/routes, right routes[sites]) and redirects with 404 log (routes[redirects]).
 * Each action responds with the tab's HTML – the editor only replaces the content of the sidebar.
 *
 * @internal
 */
final class SiteAdmin
{
    /** @return array<string, string> tabs: key => label */
    public static function tabs(?User $user = null): array
    {
        $tabs = [];
        if (null === $user || $user->admin) {
            foreach (Settings::schema() as $k => $g) {
                $tabs[$k] = $g['label'];
            }
            $tabs += ['languages' => I18n::rawMsg('fluid_site_tab_languages'), 'addresses' => I18n::rawMsg('fluid_addresses_tab')];
        }
        if (RoutesPerm::has($user, RoutesPerm::SITES) || null === $user) {
            $tabs['domains'] = I18n::rawMsg('fluid_site_tab_domains');
        }
        if (RoutesPerm::has($user, RoutesPerm::REDIRECTS) || null === $user) {
            $tabs['redirects'] = I18n::rawMsg('fluid_site_tab_redirects');
        }

        return $tabs;
    }

    public static function handle(string $action, User $user): array
    {
        $need = match (true) {
            in_array($action, ['site_save', 'site_delete', 'domain_save', 'domain_delete'], true) || in_array(Request::post('tab', 'string', ''), ['domains'], true) && 'site' === $action => RoutesPerm::SITES,
            in_array($action, ['redirect_save', 'redirect_delete', 'redirect_test'], true) || 'redirects' === Request::post('tab', 'string', '') && 'site' === $action => RoutesPerm::REDIRECTS,
            default => null,
        };
        if (null !== $need ? !RoutesPerm::has($user, $need) : !$user->admin) {
            throw new ApiError(I18n::rawMsg('fluid_error_no_permission'));
        }
        $tab = Request::post('tab', 'string', 'base');
        $msg = '';
        switch ($action) {
            case 'site_save':
                if ('domains' === $tab) {
                    $msg = self::siteSave();
                    break;
                }
                Settings::save($tab, (array) Request::post('site', 'array', []), Request::post('lang', 'int', 0) ?: null);
                $msg = I18n::rawMsg('fluid_site_saved');
                break;
            case 'site_delete':
                try {
                    Sites::delete(Request::post('id', 'string', ''));
                } catch (Throwable $e) {
                    throw new ApiError($e->getMessage());
                }
                $msg = I18n::rawMsg('fluid_site_site_deleted');
                $tab = 'domains';
                break;
            case 'domain_save':
                $msg = self::domainSave();
                $tab = 'domains';
                break;
            case 'domain_delete':
                Sites::deleteDomain(Request::post('id', 'int', 0));
                $msg = I18n::rawMsg('fluid_site_domain_deleted');
                $tab = 'domains';
                break;
            case 'redirect_test':
                $hit = Redirects::explain(Request::post('address', 'string', ''), Request::post('site', 'string', Site::MAIN));
                $msg = null === $hit ? I18n::rawMsg('fluid_site_redirect_test_none') : I18n::rawMsg('fluid_site_redirect_test_hit', (string) $hit['rule']['source'], 410 === $hit['code'] ? I18n::rawMsg('fluid_site_redirect_gone') : (string) $hit['location'],
                    $hit['code'] . ('code' === ($hit['rule']['origin'] ?? '') ? ', ' . I18n::rawMsg('fluid_site_redirect_from_code') : ''));
                $tab = 'redirects';
                break;
            case 'addresses_save':
                $msg = Seo\Addresses::save(Request::post('ending', 'string', ''), Request::post('suffix', 'string', ''));
                $tab = 'addresses';
                break;
            case 'redirect_save':
                $msg = self::redirectSave();
                $tab = 'redirects';
                break;
            case 'redirect_delete':
                Sql::factory()->setQuery('DELETE FROM ' . Redirects::TABLE . ' WHERE id = ?', [Request::post('id', 'int', 0)]);
                $msg = I18n::rawMsg('fluid_site_redirect_deleted');
                $tab = 'redirects';
                break;
            case 'language_save':
                $msg = self::languageSave();
                $tab = 'languages';
                break;
            case 'geocode':
                return ['results' => Maps::geocode(Request::post('q', 'string', ''))];
        }

        return ['html' => self::render($tab, Request::post('lang', 'int', 0) ?: Language::getStartId()), 'tab' => $tab, 'tabs' => self::tabs($user), 'message' => $msg, 'reload' => in_array($action, ['site_save', 'language_save', 'addresses_save'], true)];
    }

    public static function render(string $tab, int $lang = 0): string
    {
        $lang = $lang && Language::exists($lang) ? $lang : Language::getStartId();
        return match ($tab) {
            'domains' => self::domains(),
            'languages' => self::languages(),
            'redirects' => self::redirects(),
            'addresses' => Seo\Addresses::render(),
            default => isset(Settings::schema()[$tab]) ? self::settingsForm($tab, $lang) : '',
        };
    }

    /** Tab of the website settings; from two languages with a language switch for the translatable fields */
    private static function settingsForm(string $tab, int $lang): string
    {
        $fields = Settings::schema()[$tab]['fields'];
        $values = Settings::all();
        $switch = '';
        $translatable = Settings::translatable($tab);
        if (count(Language::getAll()) > 1 && $translatable) {
            $switch = '<nav class="site-lang" aria-label="' . I18n::msg('fluid_site_lang_switch') . '">' . implode('', array_map(static fn (Language $l) => '<button type="button" data-site-lang="' . $l->id . '"'
                . ($l->id === $lang ? ' aria-current="true"' : '') . '>' . escape($l->name) . '</button>', Language::getAll())) . '</nav>';
            if ($lang !== Language::getStartId()) {
                // translatable fields only; text of the start language as placeholder
                $fields = array_map(static fn (array $f) => ['placeholder' => (string) ($values[$f['name']] ?? ''), 'help' => I18n::rawMsg('fluid_site_lang_empty_help')] + $f, $translatable);
                $values = Settings::translations($lang);
                $switch .= '<p class="site-intro">' . I18n::msg('fluid_site_lang_intro', Language::get($lang)->name ?? '') . '</p>';
            }
        }

        return $switch . '<form data-site-form="site_save" data-tab="' . escape($tab) . '"><input type="hidden" name="lang" value="' . $lang . '">'
            . FieldForm::standalone($fields, $values, 'site')
            . '<div class="site-actions"><button type="submit" class="btn btn--primary">' . I18n::msg('fluid_site_save') . '</button></div></form>';
    }

    // ------------------------------------------------------------------ Languages (core LanguageHandler)

    private static function languages(): string
    {
        $row = static function (?Language $l): string {
            $id = $l->id ?? 0;

            return '<form class="site-card" data-site-form="language_save" data-tab="languages"><input type="hidden" name="id" value="' . $id . '">'
                . '<div class="blocks-form__grid">'
                . '<div class="blocks-form__field is-half"><label class="control-label">' . I18n::msg('fluid_site_lang_code') . '</label><input class="form-control" name="code" value="' . escape($l->code ?? '') . '" placeholder="fr" required maxlength="10" pattern="[a-z]{2}(-[A-Za-z]{2})?"></div>'
                . '<div class="blocks-form__field is-half"><label class="control-label">' . I18n::msg('fluid_site_name') . '</label><input class="form-control" name="name" value="' . escape($l->name ?? '') . '" placeholder="Français" required maxlength="100"></div>'
                . '<div class="blocks-form__field"><label class="blocks-form__check"><input type="checkbox" name="online" value="1"' . ($l?->isOnline() ? ' checked' : '') . '> ' . I18n::msg('fluid_page_online') . '</label></div>'
                . '</div><div class="site-actions"><button type="submit" class="btn btn--primary">' . ($id ? I18n::msg('fluid_site_save') : I18n::msg('fluid_site_lang_add')) . '</button></div></form>';
        };
        $h = '<p class="site-intro">' . I18n::msg('fluid_site_lang_help') . '</p>';
        foreach (Language::getAll() as $l) {
            $h .= $row($l);
        }

        return $h . '<h3 class="site-h">' . I18n::msg('fluid_site_lang_new') . '</h3>' . $row(null);
    }

    private static function languageSave(): string
    {
        $id = Request::post('id', 'int', 0);
        $code = strtolower(trim(Request::post('code', 'string', '')));
        $name = trim(Request::post('name', 'string', ''));
        if (!preg_match('~^[a-z]{2}(-[a-z]{2})?$~', $code) || '' === $name) {
            throw new ApiError(I18n::rawMsg('fluid_site_lang_invalid'));
        }
        foreach (Language::getAll() as $l) {
            if ($l->code === $code && $l->id !== $id) {
                throw new ApiError(I18n::rawMsg('fluid_site_lang_exists', $code));
            }
        }
        $online = Request::post('online', 'bool', false);
        if ($id) {
            Language::get($id) ?? throw new ApiError(I18n::rawMsg('fluid_site_lang_not_found'));
            if (!$online && $id === Language::getStartId()) {
                throw new ApiError(I18n::rawMsg('fluid_site_lang_start_online'));
            }
            LanguageHandler::edit($id, ['code' => $code, 'name' => $name, 'status' => $online]);
        } else {
            LanguageHandler::add(['code' => $code, 'name' => $name, 'status' => $online]);
        }
        Routes::rebuild();

        return $id ? I18n::rawMsg('fluid_site_lang_saved') : I18n::rawMsg('fluid_site_lang_added', $name);
    }

    // ------------------------------------------------------------------ Websites & domains (klxm/routes)

    private static function domains(): string
    {
        $h = '<p class="site-intro">' . I18n::msg('fluid_site_domains_help') . '</p>';
        foreach (Sites::all() as $site) {
            $h .= self::siteCard($site);
        }

        return $h . '<h3 class="site-h">' . I18n::msg('fluid_site_site_new') . '</h3>' . self::siteCard(null);
    }

    private static function siteCard(?Site $site): string
    {
        $key = $site->key ?? '';
        $main = null !== $site && $site->isMain();
        $code = null !== $site && $site->isCode();
        $s = $site->settings ?? new SiteSettings();
        $dis = $code ? ' disabled' : '';
        $langs = '';
        foreach (Language::getAll() as $l) {
            $on = null === $site || [] === $site->languages || in_array($l->id, $site->languages, true);
            $langs .= '<label class="blocks-form__check"><input type="checkbox" name="languages[]" value="' . $l->id . '"' . ($on ? ' checked' : '') . $dis . '> ' . escape($l->name) . '</label> ';
        }
        $ai = '';
        foreach (['allow', 'training', 'all'] as $v) {
            $ai .= '<option value="' . $v . '"' . ($s->ai->value === $v ? ' selected' : '') . '>' . I18n::msg('fluid_site_ai_' . $v) . '</option>';
        }
        $check = static fn (string $name, bool $on, string $label): string => '<label class="blocks-form__check"><input type="checkbox" name="' . $name . '" value="1"' . ($on ? ' checked' : '') . $dis . '> ' . $label . '</label>';
        $f = '<form class="site-card" data-site-form="site_save" data-tab="domains"><input type="hidden" name="key" value="' . escape($key) . '">'
            . '<div class="blocks-form__grid">'
            . (null === $site ? '<div class="blocks-form__field is-half"><label class="control-label">' . I18n::msg('fluid_site_key') . '</label><input class="form-control" name="new_key" required pattern="[a-z0-9][a-z0-9_-]{0,63}" placeholder="shop"></div>' : '')
            . '<div class="blocks-form__field is-half"><label class="control-label">' . I18n::msg('fluid_site_name') . ($main ? ' (' . I18n::msg('fluid_site_main') . ')' : '') . '</label><input class="form-control" name="name" value="' . escape($site->name ?? '') . '" maxlength="191"' . $dis . '></div>'
            . ($main ? '' : '<div class="blocks-form__field"><label class="control-label">' . I18n::msg('fluid_site_root') . '</label>' . Picker::link('site_root_' . $key, 'root', null !== $site && $site->root ? 'redaxo://' . $site->root : '', false) . '</div>'
                . '<div class="blocks-form__field">' . $check('single', $site->single ?? false, I18n::msg('fluid_site_single')) . ' ' . $check('mirror', $site->mirror ?? false, I18n::msg('fluid_site_mirror')) . '</div>')
            . '<div class="blocks-form__field"><label class="control-label">' . I18n::msg('fluid_site_notfound') . '</label>' . Picker::link('site_nf_' . $key, 'notfound', null !== $site?->notFound ? 'redaxo://' . $site->notFound : '', false) . '<p class="help-block">' . I18n::msg('fluid_site_notfound_help') . '</p></div>'
            . '<div class="blocks-form__field"><label class="control-label">' . I18n::msg('fluid_site_tab_languages') . '</label><div>' . $langs . '</div><p class="help-block">' . I18n::msg('fluid_site_languages_help') . '</p></div>'
            . '</div><details class="site-more"><summary>' . I18n::msg('fluid_site_seo_ai') . '</summary><div class="blocks-form__grid">'
            . '<div class="blocks-form__field"><label class="control-label">' . I18n::msg('fluid_site_title_template') . '</label><input class="form-control" name="s[title]" value="' . escape($s->title) . '"' . $dis . '><p class="help-block">' . I18n::msg('fluid_site_title_template_help') . '</p></div>'
            . '<div class="blocks-form__field"><label class="control-label">' . I18n::msg('fluid_site_description') . '</label><textarea class="form-control" name="s[description]" rows="2" maxlength="1000"' . $dis . '>' . escape($s->description) . '</textarea><p class="help-block">' . I18n::msg('fluid_site_description_help') . '</p></div>'
            . '<div class="blocks-form__field"><label class="control-label">' . I18n::msg('fluid_site_ai') . '</label><select class="form-control" name="s[ai]"' . $dis . '>' . $ai . '</select></div>'
            . '<div class="blocks-form__field">' . $check('s[llms]', $s->llms, I18n::msg('fluid_site_llms')) . ' ' . $check('s[llmsFull]', $s->llmsFull, I18n::msg('fluid_site_llms_full')) . ' ' . $check('s[markdown]', $s->markdown, I18n::msg('fluid_site_markdown')) . '</div>'
            . '<div class="blocks-form__field">' . $check('s[locked]', $s->locked, I18n::msg('fluid_site_locked')) . ' ' . $check('s[previews]', $s->previews, I18n::msg('fluid_site_previews')) . '</div>'
            . '<div class="blocks-form__field">' . $check('s[flat]', $s->flat, I18n::msg('fluid_site_flat')) . '</div>'
            . '<div class="blocks-form__field is-half"><label class="control-label">' . I18n::msg('fluid_site_skip') . '</label><select class="form-control" name="s[skipCategories]"' . $dis . '>'
            . '<option value="0"' . (0 === $s->skipCategories ? ' selected' : '') . '>' . I18n::msg('fluid_site_skip_never') . '</option><option value="1"' . (1 === $s->skipCategories ? ' selected' : '') . '>' . I18n::msg('fluid_site_skip_empty') . '</option><option value="2"' . (2 === $s->skipCategories ? ' selected' : '') . '>' . I18n::msg('fluid_site_skip_always') . '</option></select></div>'
            . '<div class="blocks-form__field">' . $check('s[indexNow]', $s->indexNow, I18n::msg('fluid_site_indexnow')) . '</div>'
            . '<div class="blocks-form__field"><label class="control-label">' . I18n::msg('fluid_site_robots') . '</label><textarea class="form-control" name="s[robots]" rows="3"' . $dis . ' placeholder="Disallow: /intern/">' . escape($s->robots) . '</textarea></div>'
            . '</div></details>'
            . '<div class="site-actions">' . ($code ? '<span class="help-block">' . I18n::msg('fluid_site_code', (string) $site->class) . '</span>'
                : (null !== $site && !$main ? '<button type="button" class="btn" data-site-act="site_delete" data-id="' . escape($key) . '" data-confirm="' . I18n::msg('fluid_site_site_delete_confirm') . '">' . I18n::msg('fluid_site_remove') . '</button>' : '')
                . '<button type="submit" class="btn btn--primary">' . (null === $site ? I18n::msg('fluid_site_site_add') : I18n::msg('fluid_site_save')) . '</button>') . '</div></form>';
        if (null === $site) {
            return $f;
        }
        foreach (Sites::domainsOf($site) as $d) {
            $f .= self::domainCard($site, $d);
        }

        return '<section class="site-group"><h3 class="site-h">' . escape('' !== $site->name ? $site->name : $site->key) . '</h3>' . $f
            . ($code ? '' : '<details class="site-more"><summary>' . I18n::msg('fluid_site_domain_add') . '</summary>' . self::domainCard($site, null) . '</details>') . '</section>';
    }

    private static function domainCard(Site $site, ?Domain $d): string
    {
        $dis = $site->isCode() ? ' disabled' : '';
        $roles = '';
        foreach (['primary', 'variant', 'alias'] as $v) {
            $roles .= '<option value="' . $v . '"' . (($d->role->value ?? 'primary') === $v ? ' selected' : '') . '>' . I18n::msg('fluid_site_role_' . $v) . '</option>';
        }
        $langs = '';
        $defaults = '<option value="0">' . I18n::msg('fluid_site_lang_default') . '</option>';
        foreach (Language::getAll() as $l) {
            if ($site->hasLanguage($l->id)) {
                $langs .= '<label class="blocks-form__check"><input type="checkbox" name="languages[]" value="' . $l->id . '"' . (null !== $d && in_array($l->id, $d->languages, true) ? ' checked' : '') . $dis . '> ' . escape($l->name) . '</label> ';
                $defaults .= '<option value="' . $l->id . '"' . ($d?->defaultLanguage() === $l->id ? ' selected' : '') . '>' . escape($l->name) . '</option>';
            }
        }

        return '<form class="site-card site-card--sub" data-site-form="domain_save" data-tab="domains"><input type="hidden" name="id" value="' . ($d->id ?? 0) . '"><input type="hidden" name="site" value="' . escape($site->key) . '">'
            . '<div class="blocks-form__grid">'
            . '<div class="blocks-form__field is-half"><label class="control-label">Domain</label><input class="form-control" name="host" value="' . escape($d->host ?? '') . '" placeholder="' . I18n::msg('fluid_site_host_placeholder') . '" required' . $dis . '></div>'
            . '<div class="blocks-form__field is-half"><label class="control-label">' . I18n::msg('fluid_site_role') . '</label><select class="form-control" name="role"' . $dis . '>' . $roles . '</select></div>'
            . '<div class="blocks-form__field"><label class="control-label">' . I18n::msg('fluid_site_domain_languages') . '</label><div>' . $langs . '</div><p class="help-block">' . I18n::msg('fluid_site_domain_languages_help') . '</p></div>'
            . '<div class="blocks-form__field is-half"><label class="control-label">' . I18n::msg('fluid_site_lang_noprefix') . '</label><select class="form-control" name="default_language"' . $dis . '>' . $defaults . '</select></div>'
            . '<div class="blocks-form__field is-half"><label class="blocks-form__check"><input type="checkbox" name="https" value="1"' . (($d->https ?? true) ? ' checked' : '') . $dis . '> ' . I18n::msg('fluid_site_https') . '</label></div>'
            . '</div>' . ($site->isCode() ? '' : '<div class="site-actions">' . (null !== $d ? '<button type="button" class="btn" data-site-act="domain_delete" data-id="' . $d->id . '" data-confirm="' . I18n::msg('fluid_site_domain_delete_confirm') . '">' . I18n::msg('fluid_site_remove') . '</button>' : '')
                . '<button type="submit" class="btn btn--primary">' . (null !== $d ? I18n::msg('fluid_site_save') : I18n::msg('fluid_site_domain_add')) . '</button></div>') . '</form>';
    }

    private static function siteSave(): string
    {
        $key = Request::post('key', 'string', '') ?: trim(Request::post('new_key', 'string', ''));
        $old = Sites::get($key);
        $page = static fn (string $name): ?int => ($id = (int) preg_replace('~\D~', '', Request::post($name, 'string', ''))) && Article::get($id) ? $id : null;
        $root = $page('root') ?? 0;
        if (Site::MAIN !== $key && $root < 1) {
            throw new ApiError(I18n::rawMsg('fluid_site_root_required'));
        }
        $languages = array_values(array_filter(array_map('intval', (array) Request::post('languages', 'array', [])), Language::exists(...)));
        $languages = count($languages) === Language::count() && (null === $old || [] === $old->languages) ? [] : $languages;
        $raw = (array) Request::post('s', 'array', []);
        $settings = SiteSettings::fromArray([...($old?->settings ?? new SiteSettings())->toArray(), ...$raw,
            ...array_fill_keys(array_diff(['llms', 'llmsFull', 'markdown', 'locked', 'previews', 'flat', 'indexNow'], array_keys($raw)), false)]);
        if ($settings->indexNow && '' === $settings->indexNowKey) {
            $settings = SiteSettings::fromArray([...$settings->toArray(), 'indexNowKey' => IndexNow::newKey()]);
        }
        try {
            Sites::save(new Site($key, trim(Request::post('name', 'string', '')), $root, Request::post('single', 'bool', false), Request::post('mirror', 'bool', false),
                $page('notfound'), $languages, $settings));
        } catch (Throwable $e) {
            throw new ApiError($e->getMessage());
        }

        return null === $old ? I18n::rawMsg('fluid_site_site_added') : I18n::rawMsg('fluid_site_site_saved');
    }

    private static function domainSave(): string
    {
        $langs = array_values(array_filter(array_map('intval', (array) Request::post('languages', 'array', [])), Language::exists(...)));
        $default = Request::post('default_language', 'int', 0);
        if ($default > 0) {
            $langs = array_values(array_unique([$default, ...$langs]));   // the language without prefix comes first
        }
        try {
            Sites::saveDomain(new Domain(Request::post('host', 'string', ''), Request::post('site', 'string', Site::MAIN),
                DomainRole::tryFrom(Request::post('role', 'string', 'primary')) ?? DomainRole::Primary, $langs,
                Request::post('https', 'bool', false), Request::post('id', 'int', 0)));
        } catch (Throwable $e) {
            throw new ApiError($e->getMessage());
        }

        return I18n::rawMsg('fluid_site_domain_saved');
    }

    // ------------------------------------------------------------------ Redirects

    private static function redirects(): string
    {
        $h = '<p class="site-intro">' . I18n::msg('fluid_site_redirects_help') . '</p>'
            . '<form class="site-card" data-site-form="redirect_save" data-tab="redirects"><div class="blocks-form__grid">'
            . '<div class="blocks-form__field"><label class="control-label">' . I18n::msg('fluid_site_redirect_source') . '</label><input class="form-control" name="source" placeholder="' . I18n::msg('fluid_site_redirect_source_placeholder') . '" required data-redirect-source></div>'
            . '<div class="blocks-form__field"><label class="control-label">' . I18n::msg('fluid_site_redirect_target') . '</label>' . Picker::link('redirect_target', 'target', '', false) . '<p class="help-block">' . I18n::msg('fluid_site_redirect_target_help') . '</p></div>'
            . '<div class="blocks-form__field is-half"><label class="control-label">' . I18n::msg('fluid_site_redirect_code') . '</label><select class="form-control" name="code"><option value="301">301 – ' . I18n::msg('fluid_redirect_permanent') . '</option><option value="308">308 – ' . I18n::msg('fluid_redirect_permanent_method') . '</option>'
            . '<option value="302">302 – ' . I18n::msg('fluid_redirect_temporary') . '</option><option value="307">307 – ' . I18n::msg('fluid_redirect_temporary_method') . '</option><option value="303">303 – ' . I18n::msg('fluid_redirect_see_other') . '</option>'
            . '<option value="410">410 – ' . I18n::msg('fluid_redirect_gone') . '</option><option value="short">' . I18n::msg('fluid_shortlink_option') . '</option></select></div>'
            . self::siteSelect('is-half')
            . '<div class="blocks-form__field is-half"><label class="control-label">' . I18n::msg('fluid_shortlink_expires') . '</label><input class="form-control" type="date" name="expires"></div>'
            . '<div class="blocks-form__field is-half"><label class="control-label">' . I18n::msg('fluid_site_redirect_note') . '</label><input class="form-control" name="note" maxlength="190"></div>'
            . '<div class="blocks-form__field"><label class="blocks-form__check"><input type="checkbox" name="keep_query" value="1" checked> ' . I18n::msg('fluid_site_redirect_keep_query') . '</label></div>'
            . '</div><div class="site-actions"><button type="submit" class="btn btn--primary">' . I18n::msg('fluid_site_redirect_add') . '</button></div></form>'
            . '<form class="site-card" data-site-form="redirect_test" data-tab="redirects"><div class="blocks-form__grid">'
            . '<div class="blocks-form__field"><label class="control-label">' . I18n::msg('fluid_site_redirect_test') . '</label><input class="form-control" name="address" placeholder="' . I18n::msg('fluid_site_redirect_test_placeholder') . '" required></div>'
            . self::siteSelect('') . '</div><div class="site-actions"><button type="submit" class="btn">' . I18n::msg('fluid_site_redirect_test_btn') . '</button></div></form>';
        try {
            $rules = Sql::factory()->getArray('SELECT * FROM ' . Redirects::TABLE . ' ORDER BY updated DESC, id DESC LIMIT 200');
            $nf = Sql::factory()->getArray('SELECT path, hits, last_seen FROM ' . Redirects::TABLE_404 . ' ORDER BY hits DESC, last_seen DESC LIMIT 20');
        } catch (Throwable) {
            $rules = $nf = [];
        }
        if ($rules) {
            $h .= '<h3 class="site-h">' . I18n::msg('fluid_site_tab_redirects') . ' (' . count($rules) . ')</h3><ul class="site-list" role="list">';
            foreach ($rules as $r) {
                $target = (string) $r['target'];
                $internal = Redirects::parseTarget($target);
                if ('page' === ($internal['type'] ?? '') && ($a = Article::get((int) $internal['id'], $internal['lang'] ?? Router::splitLanguage((string) $r['source'])[0]))) {
                    $target = $a->name . ' (' . (Router::prefix($a->languageId) . '/' . Routes::path($a->id, $a->languageId)) . ')';
                }
                $h .= '<li class="site-list__item"><span class="site-list__main"><code>' . escape((string) $r['source']) . '</code> → ' . escape(410 === (int) $r['code'] ? I18n::rawMsg('fluid_site_redirect_gone') : $target)
                    . '<small>' . (int) $r['code'] . ' · ' . match ((string) $r['origin']) {
                        'auto' => I18n::msg('fluid_site_redirect_auto'), Shortlinks::ORIGIN => I18n::msg('fluid_shortlink'), default => I18n::msg('fluid_site_redirect_manual'),
                    } . ' · ' . I18n::msg('fluid_site_hits', (int) $r['hits']) . (null !== $r['expires'] && strtotime((string) $r['expires']) < time() ? ' · ' . I18n::msg('fluid_shortlink_expired', date('d.m.Y', (int) strtotime((string) $r['expires']))) : '') . ('' !== (string) $r['note'] && 'auto' !== $r['origin'] ? ' · ' . escape((string) $r['note']) : '') . '</small></span>'
                    . '<button type="button" class="btn btn--icon" data-site-act="redirect_delete" data-id="' . (int) $r['id'] . '" data-confirm="' . I18n::msg('fluid_site_redirect_delete_confirm') . '" aria-label="' . I18n::msg('fluid_shortlink_delete') . '">✕</button></li>';
            }
            $h .= '</ul>';
        }
        if ($nf) {
            $h .= '<h3 class="site-h">' . I18n::msg('fluid_site_404') . '</h3><p class="help-block">' . I18n::msg('fluid_site_404_help') . '</p><ul class="site-list" role="list">';
            foreach ($nf as $r) {
                $h .= '<li class="site-list__item"><span class="site-list__main"><code>' . escape((string) $r['path']) . '</code><small>' . I18n::msg('fluid_site_hits', (int) $r['hits']) . '</small></span>'
                    . '<button type="button" class="btn" data-prefill-source="' . escape((string) $r['path']) . '">' . I18n::msg('fluid_site_redirect_prefill') . '</button></li>';
            }
            $h .= '</ul>';
        }

        return $h;
    }

    private static function redirectSave(): string
    {
        $source = Redirects::normalize(Request::post('source', 'string', ''));
        $short = 'short' === Request::post('code', 'string', '');
        $code = $short ? 302 : Request::post('code', 'int', 301);
        $target = trim(Request::post('target', 'string', ''));
        if ('' === $source || '/' === $source) {
            throw new ApiError(I18n::rawMsg('fluid_site_redirect_source_required'));
        }
        if (410 !== $code && '' === $target) {
            throw new ApiError(I18n::rawMsg('fluid_site_redirect_target_required'));
        }
        if (preg_match('~^\d+$~', $target)) {
            $target = 'redaxo://' . $target;
        }
        if ($short) {
            try {
                Shortlinks::create($source, $target, null, Request::post('note', 'string', ''), Request::post('site', 'string', '') ?: Site::MAIN);
            } catch (InvalidArgumentException $e) {
                throw new ApiError(I18n::rawMsg('fluid_shortlink_taken', $e->getMessage()));
            }

            return I18n::rawMsg('fluid_shortlink_saved', $source);
        }
        try {
            Redirects::save(['source' => $source, 'target' => 410 === $code ? '' : $target, 'code' => $code, 'note' => Request::post('note', 'string', ''),
                'site' => Request::post('site', 'string', ''), 'keep_query' => Request::post('keep_query', 'bool', false) ? 1 : 0,
                'expires' => Request::post('expires', 'string', '') ?: null]);
        } catch (InvalidArgumentException $e) {
            throw new ApiError($e->getMessage());
        }

        return I18n::rawMsg('fluid_site_redirect_added');
    }

    /** Choice of the website (only with several) */
    private static function siteSelect(string $class): string
    {
        if (count(Sites::all()) < 2) {
            return '';
        }
        $o = '<option value="">' . I18n::msg('fluid_site_all_sites') . '</option>';
        foreach (Sites::all() as $site) {
            $o .= '<option value="' . escape($site->key) . '">' . escape('' !== $site->name ? $site->name : $site->key) . '</option>';
        }

        return '<div class="blocks-form__field ' . $class . '"><label class="control-label">' . I18n::msg('fluid_site_site') . '</label><select class="form-control" name="site">' . $o . '</select></div>';
    }
}
