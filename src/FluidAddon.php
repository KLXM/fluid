<?php

namespace Klxm\Fluid;

use Klxm\NavigationArray\ExtensionPoint\NavigationStart;
use Klxm\Routes\ExtensionPoint\RobotsTxt;
use Klxm\Routes\Robots;
use Klxm\Routes\Router;
use Klxm\Routes\Sitemap;
use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\OwnedSchema;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Backend\MainPage;
use Redaxo\Core\Backend\Page;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentRendering;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Content\ExtensionPoint\ArticleSlicesQuery;
use Redaxo\Core\Content\ExtensionPoint\ContentPageBeforeSlices;
use Redaxo\Core\Content\ExtensionPoint\SliceDeleting;
use Redaxo\Core\Content\ExtensionPoint\SliceOutput;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\Index;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MediaPool\MediaPool;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Asset;

final class FluidAddon extends Addon
{
    /** Tables of fluid's former routing (until 1.0.0-beta.16) – moved to klxm/routes, removed in a later release */
    public const array LEGACY_ROUTING_TABLES = ['rex_fluid_route', 'rex_fluid_route_entry', 'rex_fluid_domain', 'rex_fluid_redirect', 'rex_fluid_404'];

    #[Override]
    public function install(): void
    {
        // Page settings on the website (PageSettings)
        Table::get(PageSettings::TABLE)
            ->ensureColumn(new Column('article_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('language_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('description', 'text', true))
            ->ensureColumn(new Column('image', 'varchar(255)', true))
            ->ensureColumn(new Column('noindex', 'tinyint(1)', false, '0'))
            ->ensureColumn(new Column('title', 'varchar(191)', true))
            // Blog: category is a blog (blog) or settings of a post
            ->ensureColumn(new Column('blog', 'tinyint(1)', false, '0'))
            ->ensureColumn(new Column('post_date', 'date', true))
            ->ensureColumn(new Column('author', 'varchar(120)', true))
            ->ensureColumn(new Column('tags', 'varchar(255)', true))
            ->setPrimaryKey(['article_id', 'language_id'])
            ->ensure();

        // MCP server: access keys (hash only), the user's permissions apply
        Table::get(Mcp\Tokens::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('name', 'varchar(120)'))
            ->ensureColumn(new Column('token_hash', 'char(64)'))
            ->ensureColumn(new Column('prefix', 'varchar(16)'))
            ->ensureColumn(new Column('scope', 'varchar(10)', false, 'read'))
            ->ensureColumn(new Column('user_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('publish', 'tinyint(1)', false, '0'))
            ->ensureColumn(new Column('expires', 'date', true))
            ->ensureColumn(new Column('created', 'datetime', true))
            ->ensureColumn(new Column('last_used', 'datetime', true))
            ->ensureColumn(new Column('client', 'varchar(120)', true))
            ->ensureIndex(new Index('token_hash', ['token_hash'], Index::UNIQUE))
            ->ensure();

        Table::get(Activity::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('created', 'datetime', true))
            ->ensureColumn(new Column('user', 'varchar(191)', true))
            ->ensureColumn(new Column('action', 'varchar(40)'))
            ->ensureColumn(new Column('article_id', 'int(10) unsigned', true))
            ->ensureColumn(new Column('detail', 'varchar(191)', true))
            ->ensure();
        Table::get(Data\Inbox::TABLE)
            ->ensureColumn(new Column('table_name', 'varchar(191)'))
            ->ensureColumn(new Column('dataset_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('state', 'varchar(10)', false, 'new'))
            ->ensureColumn(new Column('updated', 'datetime', true))
            ->ensureColumn(new Column('user', 'varchar(191)', true))
            ->setPrimaryKey(['table_name', 'dataset_id'])
            ->ensure();
        // List templates of the data list (Data\ListTemplates)
        Data\ListTemplates::install();
        ArticleSliceHistory::checkTables();
        // Kit assets from the project and Composer packages to assets/fluid-kits/ (the core ships the kit fluid)
        Kit::publishAll();
        // Changed defaults of block fields: existing blocks keep their look (once per change)
        DefaultChanges::run();
        // Design permissions (1.0.0-beta.10): existing roles keep everything (once)
        Perm::migrateRoles();
    }

    /**
     * fluid's tables: the core removes them with `console migrate:cleanup` once fluid is removed via Composer –
     * including settings, data and cache. Content (the blocks' slices) and articles remain, they belong to the core;
     * without fluid only the modules rendering them are missing.
     */
    #[Override]
    public function getOwnedSchema(): OwnedSchema
    {
        return new OwnedSchema(tables: [PageSettings::TABLE, ...self::LEGACY_ROUTING_TABLES, Activity::TABLE, Data\Inbox::TABLE, Mcp\Tokens::TABLE, Data\ListTemplates::TABLE]);
    }

    /** Backend: AddOns → fluid (status, help) – admins only */
    #[Override]
    public function getPages(): iterable
    {
        $main = new MainPage('addons', 'fluid', 'fluid');
        $main->setIcon('rex-icon fa-pen-to-square');
        $main->setRequiredPermissions('admin');
        $main->addSubpage(new Page('status', I18n::msg('fluid_backend_page_status')));
        $main->addSubpage(new Page('mcp', I18n::msg('fluid_backend_page_mcp')));
        $main->addSubpage(new Page('help', I18n::msg('fluid_backend_page_help')));
        if (!Core::getUser()?->admin) {
            $main->setHidden();
        }

        yield $main;
    }

    #[Override]
    public function boot(): void
    {
        // Category „Seitenvorlagen“: cannot be deleted while fluid is installed
        PageTemplatesGuard::boot();
        // Language of the interface on the website: the editor's (profile), guests the page's
        Locale::boot();
        // Design permissions (role → options) and locked blocks in the backend
        Perm::register();
        Lock::boot();
        // navigation_array: start of the navigation = start point of the current website (klxm/routes: websites and domains)
        Extension::register(NavigationStart::class, static function (NavigationStart $ep): mixed {
            $site = Router::site();

            return !$site->isMain() && !$site->single && $site->root > 0 ? $site->root : $ep->subject;
        });
        // Seiten des AddOns (Status, MCP …)
        if (Core::isBackend() && str_starts_with(Request::get('page', 'string'), 'fluid')) {
            Asset::addCssFile($this->getAssetsUrl('backend.css'));
        }
        // YForm form: field „Fluid-Blöcke“ (Data\FluidBlocks) needs the same form scripts as the slices
        // Inside the website frame (editor.js, iframe with fluid_frame=1): page without navigation like a core popup –
        // YForm form in the overlay and form of an ordinary module in the sidebar. After saving, YForm and the core
        // redirect without the parameter – but the browser still reports the frame (Sec-Fetch-Dest).
        $page = Request::get('page', 'string');
        if (Core::isBackend() && (str_starts_with($page, 'yform') || 'content/edit' === $page)
            && (Request::get('fluid_frame', 'bool') || 'iframe' === Core::getRequest()->headers->get('Sec-Fetch-Dest'))) {
            Extension::register('PAGE_CHECKED', static function (): void {
                Controller::getCurrentPageObject()?->setPopup(true);
            });
            Asset::addCssFile($this->getAssetsUrl('frame.css'));
        }
        if (Core::isBackend() && str_starts_with(Request::get('page', 'string'), 'yform')) {
            Asset::addCssFile($this->getAssetsUrl('form.css'));
            Asset::addCssFile($this->getAssetsUrl('blocks-field.css'));
            Asset::addJsFile($this->getAssetsUrl('form.js'));
            Asset::addJsFile($this->getAssetsUrl('blocks-field.js'));
        }
        if (Core::isBackend() && str_starts_with(Request::get('page', 'string'), 'content')) {
            Backend\SliceGrid::boot();   // Blocks of a layout side by side in their columns
            Asset::addCssFile($this->getAssetsUrl('form.css'));
            Asset::addJsFile($this->getAssetsUrl('form.js'));
            // Link „Auf der Website bearbeiten“ above the slices – only for articles with block template
            Extension::register(ContentPageBeforeSlices::class, static function (ContentPageBeforeSlices $ep): string {
                $article = $ep->article;
                $template = $article->templateKey ? Template::get($article->templateKey) : null;
                $blocks = array_filter(array_keys(BlockModule::all()), static fn (string $key) => $template && Template::checkModuleAllowed($template->key, 1, $key));
                if (!$blocks) {
                    return (string) $ep->subject;
                }
                $url = Url::article($article->id, $article->languageId, [Editing::PARAM => 1]);

                return (string) $ep->subject . Links::json($article->languageId) . '<p class="blocks-frontend-link"><a class="rex-button" data-variant="primary" href="' . $url . '" target="_blank" rel="noopener">'
                    . '<i class="rex-icon fa-solid fa-pen-to-square" aria-hidden="true"></i> ' . I18n::msg('fluid_backend_edit_on_website') . '</a></p>';
            });
        }

        // Pretty URLs (ArticleUrl, structure changes; in the frontend: URL → page)
        // Subtitles: PHP detects .srt as application/x-subrip, the core's list only expects text/plain
        $mime = &MediaPool::$allowedMimeTypes;
        if ($mime) {
            $mime['srt'] = array_values(array_unique([...($mime['srt'] ?? []), 'text/plain', 'application/x-subrip', 'application/x-srt']));
            $mime['vtt'] = array_values(array_unique([...($mime['vtt'] ?? []), 'text/vtt', 'text/plain']));
        }
        unset($mime);

        // Components register with routing (endpoints, URLs, sitemap) and with SEO – routing doesn't know them.
        // This way they can later be split off as separate addons (see docs/entwickler.md → Bausteine).
        // Endpoints (live, presence, MCP, map proxy, subtitles) register themselves via #[AsEndpoint].
        Maps\Maps::boot();
        Data\Detail::boot();
        Glossary\Glossary::boot();   // Glossar: Detailseiten, Sitemap, Einstellungen, schema.org
        Data\Knowledge::boot();      // Wissensbereich: schema.org
        Blog\Blog::boot();
        Search\Search::boot();
        Welcome::boot();
        // fluid core: editor parameters, „nicht indexieren“, custom robots.txt text
        Router::keep(Api::PARAM, Editing::PARAM);
        Sitemap::images(PageSettings::sitemapImages(...));
        Extension::register(RobotsTxt::class, static function (RobotsTxt $ep): string {
            if ('rules' !== $ep->part) {
                // website settings → „Linkvorschau beim Teilen erlauben“: when the own text locks everything
                $text = (string) $ep->subject;
                $previews = (bool) Settings::get('robots_previews', false) && !($ep->site?->settings->previews ?? false);

                return $previews && Robots::blocksAll($text) && !str_contains($text, 'facebookexternalhit')
                    ? (string) preg_replace('~\n\nSitemap:~', "\n\n" . Robots::previewRules() . "\n\nSitemap:", $text, 1) : $text;
            }
            $own = trim((string) Settings::get('robots', ''));

            return '' !== $own ? $own : (string) $ep->subject . "\nDisallow: /*?" . Editing::PARAM . '=';
        });
        // Addresses, websites, domains, SEO and GEO: klxm/routes (installs and boots itself)
        Tiptap\FluidTiptap::boot();   // text editor: plugin of fluid for klxm/tiptap (profile as class: Tiptap\FluidProfile)
        Seo\Addresses::boot();   // ending chosen on the website, unless the project sets Router::$config

        // Search index: every content change in the core (adding, deleting, moving blocks, online/offline, copying content –
        // backend and website) marks it; the next search rebuilds it
        Extension::register(ArticleContentUpdated::NAME, static fn () => Editing::isGuest() ? null : Search\Search::markDirty());   // Guests never change anything permanently

        // Layout deleted (backend or website): its column blocks become normal sections again
        Extension::register(SliceDeleting::class, static function (SliceDeleting $ep): void {
            $slice = $ep->slice;
            if (!Layout::isLayout($slice)) {
                return;
            }
            $sql = Sql::factory();
            foreach (Layout::children($slice) as $child) {
                $tunes = BlockModule::tunesOf($child);
                unset($tunes['col']);
                $sql->setQuery('UPDATE rex_article_slice SET data = ? WHERE id = ?', [BlockModule::encodeStore(BlockModule::valuesOf($child), $tunes), $child->id]);
            }
        });

        if (!Core::isFrontend()) {
            return;
        }

        // Video links in the lightbox: extra field „Video ohne Lightbox öffnen“ after each link field (website editor only –
        // it is saved with the sidebar's form; the core's form in the backend only knows the properties)
        if (VideoLinks::on()) {
            Extension::register('FLUID_BLOCK_DEFINITION', static function (ExtensionPoint $ep): array {
                $def = (array) $ep->subject;
                $def['fields'] = VideoLinks::blockFields((array) ($def['fields'] ?? []));

                return $def;
            });
        }

        // Editor endpoints (?fluid_api=…): answer before the page is rendered
        if ('' !== Request::get(Api::PARAM, 'string')) {
            Extension::register('FE_OUTPUT', static fn () => Api::handle());
        }

        // Reload on action (?fluid_block=…): render only the block of the published page (Live::block)
        if (Request::get(Live::BLOCK, 'int', 0) > 0 && '' === Request::get(Api::PARAM, 'string') && 1 !== Request::get(Editing::PARAM, 'int', 0)) {
            Extension::register('FE_OUTPUT', static fn () => Live::block());
        }

        // Edit mode: articles live from the database (working version, if enabled) instead of from the cache
        Extension::register(ArticleContentRendering::class, static function (ArticleContentRendering $ep): void {
            if (!Editing::requested()) {
                return;
            }
            Editing::start();
            $article = $ep->content;
            $article->eval = true;
            $article->sliceRevision = self::revision();
        });

        // Versions: preview of a revision (?fluid_version=…) – only with permission for the page, without toolbar, never cached
        Extension::register(ArticleContentRendering::class, static function (ArticleContentRendering $ep): void {
            $key = Request::get(Versions::PARAM, 'string', '');
            if ('' === $key || null === Editing::user() || Editing::isGuest() || null === $state = Versions::parseKey($key)) {
                return;
            }
            $article = $ep->content;
            $article->eval = true;
            $article->sliceRevision = $state['revision'];
            Versions::$preview = $state;
            if (1 === Request::get('fluid_mark', 'int', 0)) {
                Versions::prepareMarks($article->articleId, $article->languageId, $key);
            }
            header('Cache-Control: no-store');
        });
        Extension::register(ArticleSlicesQuery::class, static fn (ArticleSlicesQuery $ep): string => Versions::sliceQuery((string) $ep->subject));
        Extension::register(SliceOutput::class, static fn (SliceOutput $ep): string => Versions::markSlice((string) $ep->subject, $ep->slice->id));
        // Mark ordinary REDAXO modules (not fluid blocks) in edit mode: editor.js offers „Modul bearbeiten“
        // and opens the core's form in the sidebar
        Extension::register(SliceOutput::class, static function (SliceOutput $ep): string {
            $html = (string) $ep->subject;
            if (!Editing::active() || Editing::isGuest() || null !== BlockModule::classFor($ep->slice->moduleKey)) {
                return $html;
            }
            $class = Module::getClass($ep->slice->moduleKey);
            $label = null !== $class ? (string) $class::name() : $ep->slice->moduleKey;

            return '<div class="fluid-mod" data-fluid-slice="' . $ep->slice->id . '" data-section="' . $ep->slice->contentSectionId . '" data-label="' . \Redaxo\Core\View\escape($label) . '">' . $html . '</div>';
        });

        // In edit mode also show offline blocks (marked) so they can be switched on again
        Extension::register(ArticleSlicesQuery::class, static function (ArticleSlicesQuery $ep): ?string {
            if (!Editing::active()) {
                return null;
            }

            return str_replace(' AND rex_article_slice.status = 1', '', (string) $ep->subject);
        }, ExtensionLevel::Late);

        // KitHead::render() in a template: write the head once the page is rendered (elements and SEO values are known)
        Extension::register('OUTPUT_FILTER', static fn (ExtensionPoint $ep): string => KitHead::replace((string) $ep->subject), ExtensionLevel::Early);

        // Toolbar and editor only for logged-in editors – visitors get no extra markup
        Extension::register('OUTPUT_FILTER', static function (ExtensionPoint $ep): string {
            $html = (string) $ep->subject;
            // Editorial comments [# … #] (like KLXM Studio): invisible to visitors and in view mode, marked in edit mode (editor.js)
            if (!Editing::active() && str_contains($html, '[#')) {
                $html = (string) preg_replace_callback('~<main\b.*?</main>~s', static fn (array $m) => (string) preg_replace('~\s?\[#.*?#\]~s', '', $m[0]), $html);
            }
            // Glossary: mark terms (Website → Glossar, off by default; never while editing)
            $html = Glossary\Glossary::annotate($html);
            if (null === Editing::user() || null !== Versions::$preview) {
                return $html;
            }

            return Toolbar::inject($html);
        }, ExtensionLevel::Late);
    }

    /** Revision edited on the website: working version if enabled – otherwise live */
    public static function revision(): int
    {
        if (null !== ($guest = Editing::revisionForGuest())) {
            return $guest;   // Gastzugang (z. B. AddOn fluid_demo)
        }
        return Core::getConfig('article_work_version', false) ? ArticleRevision::WORK : ArticleRevision::LIVE;
    }
}
