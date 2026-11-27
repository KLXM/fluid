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
            $tabs += ['languages' => 'Sprachen', 'addresses' => I18n::rawMsg('fluid_addresses_tab')];
        }
        if (RoutesPerm::has($user, RoutesPerm::SITES) || null === $user) {
            $tabs['domains'] = 'Websites & Domains';
        }
        if (RoutesPerm::has($user, RoutesPerm::REDIRECTS) || null === $user) {
            $tabs['redirects'] = 'Weiterleitungen';
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
            throw new ApiError('Dafür fehlt Ihnen das Recht.');
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
                $msg = 'Gespeichert.';
                break;
            case 'site_delete':
                try {
                    Sites::delete(Request::post('id', 'string', ''));
                } catch (Throwable $e) {
                    throw new ApiError($e->getMessage());
                }
                $msg = 'Website entfernt.';
                $tab = 'domains';
                break;
            case 'domain_save':
                $msg = self::domainSave();
                $tab = 'domains';
                break;
            case 'domain_delete':
                Sites::deleteDomain(Request::post('id', 'int', 0));
                $msg = 'Domain entfernt.';
                $tab = 'domains';
                break;
            case 'redirect_test':
                $hit = Redirects::explain(Request::post('address', 'string', ''), Request::post('site', 'string', Site::MAIN));
                $msg = null === $hit ? 'Keine Weiterleitung greift – die Adresse ist eine Seite oder 404.' : 'Greift: ' . $hit['rule']['source'] . ' → ' . (410 === $hit['code'] ? 'gelöscht (410)' : $hit['location']) . ' (' . $hit['code'] . ('code' === ($hit['rule']['origin'] ?? '') ? ', aus dem Code' : '') . ')';
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
                $msg = 'Weiterleitung gelöscht.';
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
            $switch = '<nav class="site-lang" aria-label="Sprache der Texte">' . implode('', array_map(static fn (Language $l) => '<button type="button" data-site-lang="' . $l->id . '"'
                . ($l->id === $lang ? ' aria-current="true"' : '') . '>' . escape($l->name) . '</button>', Language::getAll())) . '</nav>';
            if ($lang !== Language::getStartId()) {
                // translatable fields only; text of the start language as placeholder
                $fields = array_map(static fn (array $f) => ['placeholder' => (string) ($values[$f['name']] ?? ''), 'help' => 'Leer = Text der Startsprache.'] + $f, $translatable);
                $values = Settings::translations($lang);
                $switch .= '<p class="site-intro">Texte für <b>' . escape(Language::get($lang)->name ?? '') . '</b>. Übrige Angaben (Adresse, Logo, Links …) gelten für alle Sprachen.</p>';
            }
        }

        return $switch . '<form data-site-form="site_save" data-tab="' . escape($tab) . '"><input type="hidden" name="lang" value="' . $lang . '">'
            . FieldForm::standalone($fields, $values, 'site')
            . '<div class="site-actions"><button type="submit" class="btn btn--primary">Speichern</button></div></form>';
    }

    // ------------------------------------------------------------------ Languages (core LanguageHandler)

    private static function languages(): string
    {
        $row = static function (?Language $l): string {
            $id = $l->id ?? 0;

            return '<form class="site-card" data-site-form="language_save" data-tab="languages"><input type="hidden" name="id" value="' . $id . '">'
                . '<div class="blocks-form__grid">'
                . '<div class="blocks-form__field is-half"><label class="control-label">Kürzel</label><input class="form-control" name="code" value="' . escape($l->code ?? '') . '" placeholder="fr" required maxlength="10" pattern="[a-z]{2}(-[A-Za-z]{2})?"></div>'
                . '<div class="blocks-form__field is-half"><label class="control-label">Name</label><input class="form-control" name="name" value="' . escape($l->name ?? '') . '" placeholder="Français" required maxlength="100"></div>'
                . '<div class="blocks-form__field"><label class="blocks-form__check"><input type="checkbox" name="online" value="1"' . ($l?->isOnline() ? ' checked' : '') . '> Online – für Besucher sichtbar</label></div>'
                . '</div><div class="site-actions"><button type="submit" class="btn btn--primary">' . ($id ? 'Speichern' : 'Sprache hinzufügen') . '</button></div></form>';
        };
        $h = '<p class="site-intro">Jede Seite gibt es in jeder Sprache. Eine neue Sprache übernimmt Seitennamen und Struktur; die Inhalte übertragen Sie Seite für Seite '
            . '(Sprache in der Leiste wählen → „Inhalt übernehmen“). Weitere Sprachen haben ein Präfix in der Adresse, z. B. <code>/en/…</code>. '
            . 'Neue Sprachen sind zunächst offline. Löschen nur im Backend (System → Sprachen).</p>';
        foreach (Language::getAll() as $l) {
            $h .= $row($l);
        }

        return $h . '<h3 class="site-h">Neue Sprache</h3>' . $row(null);
    }

    private static function languageSave(): string
    {
        $id = Request::post('id', 'int', 0);
        $code = strtolower(trim(Request::post('code', 'string', '')));
        $name = trim(Request::post('name', 'string', ''));
        if (!preg_match('~^[a-z]{2}(-[a-z]{2})?$~', $code) || '' === $name) {
            throw new ApiError('Bitte ein Kürzel wie „en“ und einen Namen angeben.');
        }
        foreach (Language::getAll() as $l) {
            if ($l->code === $code && $l->id !== $id) {
                throw new ApiError('Die Sprache „' . $code . '“ gibt es schon.');
            }
        }
        $online = Request::post('online', 'bool', false);
        if ($id) {
            Language::get($id) ?? throw new ApiError('Sprache nicht gefunden.');
            if (!$online && $id === Language::getStartId()) {
                throw new ApiError('Die Startsprache bleibt online.');
            }
            LanguageHandler::edit($id, ['code' => $code, 'name' => $name, 'status' => $online]);
        } else {
            LanguageHandler::add(['code' => $code, 'name' => $name, 'status' => $online]);
        }
        Routes::rebuild();

        return $id ? 'Sprache gespeichert.' : 'Sprache „' . $name . '“ angelegt – Seitennamen sind übernommen, Seiten zunächst offline.';
    }

    // ------------------------------------------------------------------ Websites & domains (klxm/routes)

    private static function domains(): string
    {
        $h = '<p class="site-intro">Eine Installation kann mehrere <b>Websites</b> haben – jede mit Startpunkt im Seitenbaum, eigenen Sprachen, 404-Seite und Domains. '
            . 'Je Website eine <b>Hauptdomain</b> (wahlweise je Sprache, z. B. <code>example.com</code> für Englisch ohne Präfix), <b>Varianten</b> ohne Weiterleitung '
            . '(Stage, lokal – nie indexiert) und <b>Aliase</b>, die per 301 zur Hauptdomain leiten. Eine Website übernimmt ihre Seiten erst, wenn sie eine Domain hat. '
            . 'Alle Domains müssen im Hosting auf diese Installation zeigen.</p>';
        foreach (Sites::all() as $site) {
            $h .= self::siteCard($site);
        }

        return $h . '<h3 class="site-h">Neue Website</h3>' . self::siteCard(null);
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
        foreach (['allow' => 'Alle erlauben (beste Sichtbarkeit in KI-Antworten)', 'training' => 'KI-Training sperren, KI-Antworten erlauben', 'all' => 'Alle KI-Crawler sperren'] as $v => $l) {
            $ai .= '<option value="' . $v . '"' . ($s->ai->value === $v ? ' selected' : '') . '>' . $l . '</option>';
        }
        $check = static fn (string $name, bool $on, string $label): string => '<label class="blocks-form__check"><input type="checkbox" name="' . $name . '" value="1"' . ($on ? ' checked' : '') . $dis . '> ' . $label . '</label>';
        $f = '<form class="site-card" data-site-form="site_save" data-tab="domains"><input type="hidden" name="key" value="' . escape($key) . '">'
            . '<div class="blocks-form__grid">'
            . (null === $site ? '<div class="blocks-form__field is-half"><label class="control-label">Kennung</label><input class="form-control" name="new_key" required pattern="[a-z0-9][a-z0-9_-]{0,63}" placeholder="shop"></div>' : '')
            . '<div class="blocks-form__field is-half"><label class="control-label">Name' . ($main ? ' (Haupt-Website)' : '') . '</label><input class="form-control" name="name" value="' . escape($site->name ?? '') . '" maxlength="191"' . $dis . '></div>'
            . ($main ? '' : '<div class="blocks-form__field"><label class="control-label">Startpunkt im Seitenbaum</label>' . Picker::link('site_root_' . $key, 'root', null !== $site && $site->root ? 'redaxo://' . $site->root : '', false) . '</div>'
                . '<div class="blocks-form__field">' . $check('single', $site->single ?? false, 'Nur diese Seite (Landing-Page, ohne Unterseiten)') . ' ' . $check('mirror', $site->mirror ?? false, 'Spiegel – Seiten bleiben kanonisch auf der Haupt-Website') . '</div>')
            . '<div class="blocks-form__field"><label class="control-label">404-Seite</label>' . Picker::link('site_nf_' . $key, 'notfound', null !== $site?->notFound ? 'redaxo://' . $site->notFound : '', false) . '<p class="help-block">Leer = die des Systems.</p></div>'
            . '<div class="blocks-form__field"><label class="control-label">Sprachen</label><div>' . $langs . '</div><p class="help-block">Die erste gewählte Sprache hat kein Präfix.</p></div>'
            . '</div><details class="site-more"><summary>SEO &amp; KI</summary><div class="blocks-form__grid">'
            . '<div class="blocks-form__field"><label class="control-label">Titel-Vorlage</label><input class="form-control" name="s[title]" value="' . escape($s->title) . '"' . $dis . '><p class="help-block">{title} {sep} {site} {parent} – der Zusatz entfällt, wenn der Titel länger als 60 Zeichen würde.</p></div>'
            . '<div class="blocks-form__field"><label class="control-label">Kurzbeschreibung der Website</label><textarea class="form-control" name="s[description]" rows="2" maxlength="1000"' . $dis . '>' . escape($s->description) . '</textarea><p class="help-block">Für llms.txt und schema.org (WebSite).</p></div>'
            . '<div class="blocks-form__field"><label class="control-label">KI-Crawler</label><select class="form-control" name="s[ai]"' . $dis . '>' . $ai . '</select></div>'
            . '<div class="blocks-form__field">' . $check('s[llms]', $s->llms, '/llms.txt für KI-Assistenten') . ' ' . $check('s[llmsFull]', $s->llmsFull, '/llms-full.txt mit Text') . ' ' . $check('s[markdown]', $s->markdown, 'Markdown-Fassung jeder Seite (/seite.md)') . '</div>'
            . '<div class="blocks-form__field">' . $check('s[locked]', $s->locked, 'Website für Suchmaschinen sperren (vor dem Start)') . ' ' . $check('s[previews]', $s->previews, 'Linkvorschau beim Teilen trotzdem erlauben') . '</div>'
            . '<div class="blocks-form__field">' . $check('s[flat]', $s->flat, 'Alle Seiten auf einer Ebene (/seite statt /bereich/seite)') . '</div>'
            . '<div class="blocks-form__field is-half"><label class="control-label">Kategorien überspringen</label><select class="form-control" name="s[skipCategories]"' . $dis . '>'
            . '<option value="0"' . (0 === $s->skipCategories ? ' selected' : '') . '>nie</option><option value="1"' . (1 === $s->skipCategories ? ' selected' : '') . '>wenn ohne Inhalt</option><option value="2"' . (2 === $s->skipCategories ? ' selected' : '') . '>immer</option></select></div>'
            . '<div class="blocks-form__field">' . $check('s[indexNow]', $s->indexNow, 'Änderungen an IndexNow melden (Bing, Yandex …)') . '</div>'
            . '<div class="blocks-form__field"><label class="control-label">Eigene Zeilen in robots.txt</label><textarea class="form-control" name="s[robots]" rows="3"' . $dis . ' placeholder="Disallow: /intern/">' . escape($s->robots) . '</textarea></div>'
            . '</div></details>'
            . '<div class="site-actions">' . ($code ? '<span class="help-block">Im Code festgelegt (' . escape((string) $site->class) . ') – nur lesbar.</span>'
                : (null !== $site && !$main ? '<button type="button" class="btn" data-site-act="site_delete" data-id="' . escape($key) . '" data-confirm="Website entfernen? Ihre Seiten gehören dann wieder zur Haupt-Website.">Entfernen</button>' : '')
                . '<button type="submit" class="btn btn--primary">' . (null === $site ? 'Website anlegen' : 'Speichern') . '</button>') . '</div></form>';
        if (null === $site) {
            return $f;
        }
        foreach (Sites::domainsOf($site) as $d) {
            $f .= self::domainCard($site, $d);
        }

        return '<section class="site-group"><h3 class="site-h">' . escape('' !== $site->name ? $site->name : $site->key) . '</h3>' . $f
            . ($code ? '' : '<details class="site-more"><summary>Domain hinzufügen</summary>' . self::domainCard($site, null) . '</details>') . '</section>';
    }

    private static function domainCard(Site $site, ?Domain $d): string
    {
        $dis = $site->isCode() ? ' disabled' : '';
        $roles = '';
        foreach (['primary' => 'Hauptdomain', 'variant' => 'Variante (Stage, lokal)', 'alias' => 'Alias (→ Hauptdomain)'] as $v => $l) {
            $roles .= '<option value="' . $v . '"' . (($d->role->value ?? 'primary') === $v ? ' selected' : '') . '>' . $l . '</option>';
        }
        $langs = '';
        $defaults = '<option value="0">Standard der Website</option>';
        foreach (Language::getAll() as $l) {
            if ($site->hasLanguage($l->id)) {
                $langs .= '<label class="blocks-form__check"><input type="checkbox" name="languages[]" value="' . $l->id . '"' . (null !== $d && in_array($l->id, $d->languages, true) ? ' checked' : '') . $dis . '> ' . escape($l->name) . '</label> ';
                $defaults .= '<option value="' . $l->id . '"' . ($d?->defaultLanguage() === $l->id ? ' selected' : '') . '>' . escape($l->name) . '</option>';
            }
        }

        return '<form class="site-card site-card--sub" data-site-form="domain_save" data-tab="domains"><input type="hidden" name="id" value="' . ($d->id ?? 0) . '"><input type="hidden" name="site" value="' . escape($site->key) . '">'
            . '<div class="blocks-form__grid">'
            . '<div class="blocks-form__field is-half"><label class="control-label">Domain</label><input class="form-control" name="host" value="' . escape($d->host ?? '') . '" placeholder="www.beispiel.de" required' . $dis . '></div>'
            . '<div class="blocks-form__field is-half"><label class="control-label">Rolle</label><select class="form-control" name="role"' . $dis . '>' . $roles . '</select></div>'
            . '<div class="blocks-form__field"><label class="control-label">Sprachen dieser Domain</label><div>' . $langs . '</div><p class="help-block">Keine gewählt = alle Sprachen der Website. Andere Sprachen leiten zu ihrer Domain.</p></div>'
            . '<div class="blocks-form__field is-half"><label class="control-label">Sprache ohne Präfix</label><select class="form-control" name="default_language"' . $dis . '>' . $defaults . '</select></div>'
            . '<div class="blocks-form__field is-half"><label class="blocks-form__check"><input type="checkbox" name="https" value="1"' . (($d->https ?? true) ? ' checked' : '') . $dis . '> https erzwingen</label></div>'
            . '</div>' . ($site->isCode() ? '' : '<div class="site-actions">' . (null !== $d ? '<button type="button" class="btn" data-site-act="domain_delete" data-id="' . $d->id . '" data-confirm="Domain entfernen?">Entfernen</button>' : '')
                . '<button type="submit" class="btn btn--primary">' . (null !== $d ? 'Speichern' : 'Domain hinzufügen') . '</button></div>') . '</form>';
    }

    private static function siteSave(): string
    {
        $key = Request::post('key', 'string', '') ?: trim(Request::post('new_key', 'string', ''));
        $old = Sites::get($key);
        $page = static fn (string $name): ?int => ($id = (int) preg_replace('~\D~', '', Request::post($name, 'string', ''))) && Article::get($id) ? $id : null;
        $root = $page('root') ?? 0;
        if (Site::MAIN !== $key && $root < 1) {
            throw new ApiError('Bitte den Startpunkt der Website im Seitenbaum wählen.');
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

        return null === $old ? 'Website angelegt – sie übernimmt ihre Seiten, sobald sie eine Domain hat.' : 'Website gespeichert.';
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

        return 'Domain gespeichert.';
    }

    // ------------------------------------------------------------------ Redirects

    private static function redirects(): string
    {
        $h = '<p class="site-intro">Ändert sich die Adresse einer Seite, entsteht die Weiterleitung automatisch. Hier legen Sie eigene an – z. B. für alte Adressen nach einem Relaunch. '
            . '„/alt/*“ leitet alles darunter um; „*“ im Ziel übernimmt den Rest.</p>'
            . '<form class="site-card" data-site-form="redirect_save" data-tab="redirects"><div class="blocks-form__grid">'
            . '<div class="blocks-form__field"><label class="control-label">Alte Adresse</label><input class="form-control" name="source" placeholder="/alte-seite oder /alt/*" required data-redirect-source></div>'
            . '<div class="blocks-form__field"><label class="control-label">Ziel</label>' . Picker::link('redirect_target', 'target', '', false) . '<p class="help-block">Seite, /pfad oder https://…</p></div>'
            . '<div class="blocks-form__field is-half"><label class="control-label">Art</label><select class="form-control" name="code"><option value="301">301 – dauerhaft</option><option value="308">308 – dauerhaft, Methode bleibt</option>'
            . '<option value="302">302 – vorübergehend</option><option value="307">307 – vorübergehend, Methode bleibt</option><option value="303">303 – siehe andere Seite</option>'
            . '<option value="410">410 – gelöscht (ohne Ziel)</option><option value="short">' . I18n::msg('fluid_shortlink_option') . '</option></select></div>'
            . self::siteSelect('is-half')
            . '<div class="blocks-form__field is-half"><label class="control-label">Gültig bis (optional)</label><input class="form-control" type="date" name="expires"></div>'
            . '<div class="blocks-form__field is-half"><label class="control-label">Notiz (optional)</label><input class="form-control" name="note" maxlength="190"></div>'
            . '<div class="blocks-form__field"><label class="blocks-form__check"><input type="checkbox" name="keep_query" value="1" checked> Parameter der Anfrage übernehmen (?…)</label></div>'
            . '</div><div class="site-actions"><button type="submit" class="btn btn--primary">Weiterleitung anlegen</button></div></form>'
            . '<form class="site-card" data-site-form="redirect_test" data-tab="redirects"><div class="blocks-form__grid">'
            . '<div class="blocks-form__field"><label class="control-label">Adresse testen</label><input class="form-control" name="address" placeholder="/alte-seite?x=1" required></div>'
            . self::siteSelect('') . '</div><div class="site-actions"><button type="submit" class="btn">Testen</button></div></form>';
        try {
            $rules = Sql::factory()->getArray('SELECT * FROM ' . Redirects::TABLE . ' ORDER BY updated DESC, id DESC LIMIT 200');
            $nf = Sql::factory()->getArray('SELECT path, hits, last_seen FROM ' . Redirects::TABLE_404 . ' ORDER BY hits DESC, last_seen DESC LIMIT 20');
        } catch (Throwable) {
            $rules = $nf = [];
        }
        if ($rules) {
            $h .= '<h3 class="site-h">Weiterleitungen (' . count($rules) . ')</h3><ul class="site-list" role="list">';
            foreach ($rules as $r) {
                $target = (string) $r['target'];
                $internal = Redirects::parseTarget($target);
                if ('page' === ($internal['type'] ?? '') && ($a = Article::get((int) $internal['id'], $internal['lang'] ?? Router::splitLanguage((string) $r['source'])[0]))) {
                    $target = $a->name . ' (' . (Router::prefix($a->languageId) . '/' . Routes::path($a->id, $a->languageId)) . ')';
                }
                $h .= '<li class="site-list__item"><span class="site-list__main"><code>' . escape((string) $r['source']) . '</code> → ' . escape(410 === (int) $r['code'] ? 'gelöscht (410)' : $target)
                    . '<small>' . (int) $r['code'] . ' · ' . match ((string) $r['origin']) {
                        'auto' => 'automatisch', Shortlinks::ORIGIN => I18n::msg('fluid_shortlink'), default => 'manuell',
                    } . ' · ' . (int) $r['hits'] . ' Aufrufe' . (null !== $r['expires'] && strtotime((string) $r['expires']) < time() ? ' · ' . I18n::msg('fluid_shortlink_expired', date('d.m.Y', (int) strtotime((string) $r['expires']))) : '') . ('' !== (string) $r['note'] && 'auto' !== $r['origin'] ? ' · ' . escape((string) $r['note']) : '') . '</small></span>'
                    . '<button type="button" class="btn btn--icon" data-site-act="redirect_delete" data-id="' . (int) $r['id'] . '" data-confirm="Weiterleitung löschen?" aria-label="Löschen">✕</button></li>';
            }
            $h .= '</ul>';
        }
        if ($nf) {
            $h .= '<h3 class="site-h">Nicht gefunden (404)</h3><p class="help-block">Adressen, die Besucher aufgerufen haben und die es nicht gibt – ohne IP. Häufige alte Adressen umleiten.</p><ul class="site-list" role="list">';
            foreach ($nf as $r) {
                $h .= '<li class="site-list__item"><span class="site-list__main"><code>' . escape((string) $r['path']) . '</code><small>' . (int) $r['hits'] . ' Aufrufe</small></span>'
                    . '<button type="button" class="btn" data-prefill-source="' . escape((string) $r['path']) . '">Umleiten …</button></li>';
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
            throw new ApiError('Bitte die alte Adresse angeben (z. B. /alte-seite).');
        }
        if (410 !== $code && '' === $target) {
            throw new ApiError('Bitte ein Ziel wählen.');
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

        return 'Weiterleitung angelegt.';
    }

    /** Choice of the website (only with several) */
    private static function siteSelect(string $class): string
    {
        if (count(Sites::all()) < 2) {
            return '';
        }
        $o = '<option value="">alle Websites</option>';
        foreach (Sites::all() as $site) {
            $o .= '<option value="' . escape($site->key) . '">' . escape('' !== $site->name ? $site->name : $site->key) . '</option>';
        }

        return '<div class="blocks-form__field ' . $class . '"><label class="control-label">Website</label><select class="form-control" name="site">' . $o . '</select></div>';
    }
}
