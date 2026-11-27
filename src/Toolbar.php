<?php

namespace Klxm\Fluid;

use Klxm\Fluid\Media\ImageEffect;
use Klxm\Fluid\Media\ImageFit;
use Klxm\ImageEditor\ImageEditorAddon;
use Klxm\Routes\Router;
use Klxm\Routes\Sites;
use Klxm\Routes\Sso;
use Klxm\Tiptap\Tiptap;
use Klxm\Tiptap\TiptapAddon;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\User;

use function count;
use function is_array;
use function Redaxo\Core\View\escape;

use const JSON_HEX_AMP;
use const JSON_HEX_TAG;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_URL_PATH;

/**
 * Bar and editor for logged-in editors – inserted via OUTPUT_FILTER, only when a backend user
 * with permission on the article is logged in. Visitors get no additional markup.
 * UI: assets/editor/editor.js (Shadow DOM, no collision with the website's CSS).
 *
 * @internal
 */
final class Toolbar
{
    public static function inject(string $html): string
    {
        $user = Editing::user();
        $article = Article::getCurrent();
        if (!$user || !$article || !str_contains($html, '</body>')) {
            return $html;
        }

        $editing = Editing::requested();
        $guest = Editing::isGuest();   // guest access (FLUID_GUEST_USER, e.g. addon fluid_demo): only blocks of this page
        $addon = FluidAddon::instance();
        $perm = $user->getComplexPerm('modules');
        // Detail view of a record (/page/title-ID): edit, view, working version stay on the entry
        $entry = Request::get(Data\Detail::PARAM, 'int', 0);
        $entryTitle = $entry > 0 && ($cfg = Data\Detail::config($article->id, $article->languageId)) ? Data\Detail::title($cfg, $entry) : null;
        $here = static fn (array $params = []): string => null !== $entryTitle
            ? Data\Detail::url(Url::article($article->id, $article->languageId, $params), $entryTitle, $entry)
            : Url::article($article->id, $article->languageId, $params);
        $modules = [];
        foreach (BlockModule::all() as $key => $module) {
            if (!$module::available() || !Template::checkModuleAllowed((string) $article->templateKey, 1, $key)) {
                continue;
            }
            if (!$perm instanceof ModulePermission || !$perm->hasPerm($key)) {
                continue;
            }
            $def = $module::definition();
            $modules[] = [
                'key' => $key,
                'label' => $module::label(),
                'group' => (string) ($def['group'] ?? 'Inhalt'),
                'help' => (string) ($def['help'] ?? ''),
                'nestable' => $module::nestable(),
                'drags' => self::drags($module, $def),
            ];
        }

        // ordinary REDAXO modules whose fields the form of fluid shows completely: also in the block library (form first)
        foreach (Module::getAll() as $key => $class) {
            if ($guest || !ModuleForm::addable($key, $article) || !$perm instanceof ModulePermission || !$perm->hasPerm($key)) {
                continue;
            }
            $modules[] = ['key' => $key, 'label' => $class::name(), 'group' => 'REDAXO-Module', 'help' => 'Gewöhnliches Modul – Felder in der Seitenleiste', 'nestable' => false, 'drags' => [], 'native' => true];
        }

        $config = [
            'editing' => $editing,
            // Block picker „Häufig verwendet“: occurrences per block type on the website (published version)
            'usage' => self::usage(),
            'article' => ['id' => $article->id, 'name' => $article->name, 'language' => $article->languageId, 'online' => $article->isOnline(), 'category' => $article->getCategory()->name ?? '', 'menu' => $article->isStartArticle() && null !== Category::get($article->id)],
            'api' => Url::article($article->id, $article->languageId, [Api::PARAM => '__action__']),
            'csrf' => CsrfToken::factory(Api::CSRF)->getValue(),
            'entry' => null !== $entryTitle ? $entry : 0,   // detail view: to the API so blocks are re-rendered with the entry
            // Entry of a source (\Klxm\Routes\RouteSource) at this address: its fields in the page settings
            'route' => Router::current()?->entry?->key() ?? '',
            'editUrl' => $here([Editing::PARAM => 1]),
            'viewUrl' => $here(),
            // View of the working version (REDAXO core: rex_version=1, only with backend login)
            'draftUrl' => $here(['rex_version' => ArticleRevision::WORK]),
            'viewingDraft' => !$editing && ArticleRevision::WORK === Request::get('rex_version', 'int', ArticleRevision::LIVE),
            'backendUrl' => Url::backendPage('content/edit', ['article_id' => $article->id, 'language' => $article->languageId, 'mode' => 'edit']),
            // Menu ⋯ → log out (Api::logout), the page stays open afterwards as a visitor
            'logout' => !$guest,
            'user' => $user->name ?: $user->login,
            'modules' => $modules,
            'state' => Api::state($article),
            // Design: admins save; guests (e.g. demo) only preview in their own browser – the API rejects saving
            'design' => $user->admin || $guest ? self::designSchema($article) : null,
            'designSave' => $user->admin && !$guest,
            // „SEO & KI“: hints for this page and the whole website (klxm/routes Advice)
            'seo' => true,
            // Several domains (klxm/routes): switch to another one – logged in there on demand (Sso)
            'domains' => $guest ? null : self::domains($user),
            // Website settings (sidebar „Website“): admins only
            'site' => !$guest && [] !== ($siteTabs = SiteAdmin::tabs($user)) ? ['tabs' => $siteTabs] : null,
            // Action permissions of the core (roles → options) – the bar only shows what is allowed
            // Guests: only edit blocks – creating pages, page settings etc. are off
            'can' => $guest ? ['editArticle' => false, 'editCategory' => false, 'addArticle' => false, 'addCategory' => false] + Perm::flags($user) : Perm::flags($user),
            // Guest access: bar without page tree, history, backend links; notice and buttons in the bar via 'notice' (FLUID_EDITOR_CONFIG)
            'guest' => $guest,
            'notice' => null,
            // further scripts of addons (FLUID_EDITOR_CONFIG), run before the editor; window.fluidEditorWait: promises the editor waits for
            'scripts' => [],
            // Language switch (2+ languages): open the page in another language in the same mode; „leer“ = no blocks yet
            'languages' => count(Language::getAll()) > 1 ? array_map(static fn (array $l) => $l + [
                'href' => Url::article($article->id, $l['id'], $editing ? [Editing::PARAM => 1] : []),
                'empty' => !Sql::factory()->getArray('SELECT id FROM rex_article_slice WHERE article_id = ? AND language_id = ? LIMIT 1', [$article->id, $l['id']]),
            ], Router::alternates($article, !$guest)) : null,   // guests: only languages in which the page is online
            // Inquiry inbox: only if there are forms with YForm tables
            'inbox' => !$guest && [] !== Data\Inbox::tables($user) ? ['new' => Data\Inbox::countNew($user)] : null,
            // Quick glossary (menu ⋯ → Glossar, Alt+G): only with permission on the glossary's data source
            'glossary' => $guest ? null : Glossary\Glossary::toolbar($user),
            // Addon image_editor (optional): edit images on the website
            'imageEditor' => !$guest && class_exists(ImageEditorAddon::class) ? ImageEditorAddon::config() : null,
            // Image in this place (effect, display, crop): select with labels from lang/
            'imagePlace' => [
                'effects' => array_map(static fn (ImageEffect $e) => [$e->value, $e->label()], ImageEffect::cases()),
                'fits' => array_map(static fn (ImageFit $f) => [$f->value, $f->label()], ImageFit::cases()),
            ],
            'css' => $addon->getAssetsUrl('editor/editor.shadow.css') . '?v=' . self::version('editor/editor.shadow.css'),
            // text editor TipTap (klxm/tiptap): styles for the shadow root of the interface
            'tiptapCss' => TiptapAddon::instance()->assetUrl('tiptap.css'),
            // Versions view (timeline, preview, comparison) – loads only when opened
            // Presence of the editors: who is working where right now (PresenceApi, assets/editor/presence.js)
            'presence' => $guest ? null : PresenceApi::toolbar($article, $editing),
            'versions' => ['js' => $addon->getAssetsUrl('editor/versions.js') . '?v=' . self::version('editor/versions.js'), 'css' => $addon->getAssetsUrl('editor/versions.css') . '?v=' . self::version('editor/versions.css')],
        ];

        $head = '<link rel="stylesheet" href="' . escape($addon->getAssetsUrl('editor/editor.css') . '?v=' . self::version('editor/editor.css')) . '" data-blocks-editor>';
        // FLUID_EDITOR_CONFIG: change the editor settings (e.g. hide blocks, disable menu items)
        $config = (array) Extension::dispatch(new ExtensionPoint('FLUID_EDITOR_CONFIG', $config, ['article' => $article, 'user' => $user]));
        $body = '<script type="application/json" id="blocks-editor-config">' . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>'
            . ($editing ? Links::json($article->languageId) : '')
            . implode('', array_map(static fn ($src) => '<script src="' . escape((string) $src) . '" defer></script>', (array) ($config['scripts'] ?? [])))
            // Media and page picker (Picker\FrontendPicker, also for other addons); guests only read (no upload)
            . Picker\FrontendPicker::head($article->languageId)
            . '<script src="' . escape($addon->getAssetsUrl('form.js') . '?v=' . self::version('form.js')) . '" defer></script>'
            // TipTap: loader, plugins (fluid: tiptap-fluid.js) and profiles – before editor.js, which uses RexTiptap
            . Tiptap::assets(true)
            . '<script src="' . escape($addon->getAssetsUrl('editor/editor.js') . '?v=' . self::version('editor/editor.js')) . '" defer></script>'
            . (null !== ($config['presence'] ?? null) ? '<script type="module" src="' . escape((string) $config['presence']['js']) . '"></script>' : '');

        $html = preg_replace('~</head>~i', $head . '</head>', $html, 1) ?? $html;
        $pos = strripos($html, '</body>');

        return false === $pos ? $html : substr($html, 0, $pos) . $body . substr($html, $pos);
    }

    /** @return array<string, int> module key → number of blocks, descending */
    private static function usage(): array
    {
        $out = [];
        foreach (Sql::factory()->getArray('SELECT module, COUNT(*) AS n FROM rex_article_slice WHERE revision = ? GROUP BY module ORDER BY n DESC', [ArticleRevision::LIVE]) as $r) {
            $out[(string) $r['module']] = (int) $r['n'];
        }

        return $out;
    }

    /** Style editor: schema of the page's kit (administration only) */
    /**
     * Other domains of the installation for the menu ⋯ → „Domain wechseln“: the same page on another domain of the
     * same website, the start page on another website – logged in there on demand (klxm/routes Sso).
     *
     * @return list<array{label: string, host: string, url: string, loggedIn: bool}>|null
     */
    private static function domains(User $user): ?array
    {
        if (!Sso::enabled()) {
            return null;
        }
        $site = Router::site()->key;
        $path = (string) parse_url((string) Request::server('REQUEST_URI', 'string', '/'), PHP_URL_PATH);
        $out = [];
        foreach (Sso::domains($user) as $d) {
            if ($d['current'] || ('variant' === $d['role'] && !Sites::isLocal($d['host']))) {
                continue;
            }
            $lang = null !== $d['language'] ? (Language::get($d['language'])->name ?? '') : '';
            $out[] = ['label' => $d['siteName'] . ('' !== $lang ? ' · ' . $lang : ''), 'host' => $d['host'],
                'url' => Sso::switchUrl($d['host'], $d['site'] === $site ? $path : '/'), 'loggedIn' => $d['loggedIn']];
        }

        return [] !== $out ? $out : null;
    }

    private static function designSchema(Article $article): ?array
    {
        foreach (BlockModule::all() as $key => $m) {
            if (Template::checkModuleAllowed((string) $article->templateKey, 1, $key)) {
                return $m::kit()->design()->schema();
            }
        }

        return null;
    }

    private static function version(string $file): string
    {
        $path = FluidAddon::instance()->getPath('assets/' . $file);

        return is_file($path) ? substr(md5((string) filemtime($path)), 0, 8) : '0';
    }

    /**
     * Handles on the page (kit setting 'drags', as in KLXM Studio): field, target element, class per value, options/default
     * from the field schema – e.g. text width (body text) or image/text split.
     *
     * @return list<array<string, mixed>>
     */
    /** @param class-string<BlockModule> $module */
    private static function drags(string $module, array $def): array
    {
        $out = [];
        foreach ((array) ($def['drags'] ?? []) as $g) {
            $field = null;
            foreach ($module::fields() as $f) {
                if (($f['name'] ?? '') === ($g['field'] ?? null)) {
                    $field = $f;
                }
            }
            if (!$field || !is_array($field['options'] ?? null)) {
                continue;
            }
            $out[] = [
                'field' => (string) $g['field'], 'target' => (string) $g['target'], 'class' => (string) $g['class'],
                'apply' => (string) ($g['apply'] ?? ''), 'kind' => isset($g['apply']) ? 'split' : 'width',
                'label' => (string) ($g['label'] ?? $field['label'] ?? $g['field']),
                'options' => $field['options'], 'default' => (string) ($field['default'] ?? array_key_first($field['options'])),
            ];
        }

        return $out;
    }
}
