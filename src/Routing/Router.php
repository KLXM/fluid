<?php

namespace Klxm\Fluid\Routing;

use Klxm\Fluid\Routing\Attribute\AsEndpoint;
use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ExtensionPoint as ContentEp;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\ExtensionPoint as LanguageEp;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\BackendLogin;
use Throwable;

use function array_diff_key;
use function array_intersect;
use function array_keys;
use function explode;
use function http_build_query;
use function in_array;
use function is_callable;
use function is_string;
use function parse_url;
use function preg_match;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;
use function rtrim;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

use const PHP_URL_HOST;
use const PHP_URL_PATH;
use const PHP_URL_QUERY;

/**
 * Clean URLs for REDAXO 6: /technik instead of index.php?article_id=40 (stored paths, Routes), entries of custom
 * sources (/news/sommerfest-12, RouteSource), main address, alias and landing domains (Domains), redirects and
 * 404 log (Redirects), sitemap.xml (Sitemap), robots.txt (Robots) and page head (Seo\Head).
 *
 * Self-contained component: knows no other parts of fluid and can be split off as its own addon or replaced by
 * yrewrite + a URL addon (see README.md in this folder). Configuration in the project:
 *
 *     Router::$config = new RoutingConfig(format: UrlFormat::html());
 *
 * Extending (none of it requires knowledge of the routing):
 *   #[AsRouteSource] + RouteSource/TableSource         entries with their own address (stored, sitemap, meta)
 *   #[AsEndpoint('/pfad')] on a static method           own addresses before the pages (or Router::endpoint())
 *   Router::resolver(fn (string $path, int $lang, array $query): ?RouteMatch)   dynamic addresses without storage
 *   Router::gate(fn (string $path))                    before the page lookup (e.g. „Erste Schritte“ without start page)
 *   Router::onPage(fn (int $id, int $lang, array $query))   page found (e.g. redirect old parameters)
 *   Router::keep('param', …)                           parameters for which ?article_id=… is not redirected
 *   Extension Points: RoutesRebuilt, SitemapEntries, RobotsTxt, ArticleMeta, PageMetaResolved
 *
 * Request flow (PACKAGES_INCLUDED, early – all addons are loaded): early endpoints · alias host → 301
 * main address · endpoints (sitemap.xml, robots.txt …) · gates · old ?article_id=… → 301 · language prefix · strip
 * suffix · path → page, entry or resolver · spelling other than the canonical address → 301 · redirect · otherwise
 * "not found" page with 404. Offline pages only with backend login.
 */
final class Router
{
    /** Einstellungen des Projekts (Project::configure()); null = Vorgaben */
    public static ?RoutingConfig $config = null;

    private static ?RoutingConfig $defaults = null;

    /** Parameters for which an ?article_id request stays as it is (API, working version, Media Manager; more via keep()) */
    private static array $keep = ['rex-api-call', 'rex_version', 'rex_history_date', 'rex_media_type', 'rex_media_file'];

    /** @var list<array{match: string|callable, handler: callable, early: bool}> */
    private static array $endpoints = [];

    /** @var list<array{match: string|callable, handler: callable, early: bool}>|null per #[AsEndpoint] gefunden */
    private static ?array $discovered = null;

    /** @var list<callable(string): void> */
    private static array $gates = [];

    /** @var list<callable(string, int, array): ?RouteMatch> */
    private static array $resolvers = [];

    /** @var list<callable(int, int, array): void> */
    private static array $pageHooks = [];

    private static ?Domain $landing = null;
    private static ?RouteMatch $current = null;
    private static bool $handled = false;
    /** Only GET/HEAD are redirected – never the editor's POST to clean URLs */
    private static bool $canRedirect = true;

    public static function config(): RoutingConfig
    {
        return self::$config ?? self::$defaults ??= new RoutingConfig();
    }

    /**
     * Default configuration of an addon (e.g. chosen in its interface) – used only while the project sets no
     * Router::$config. Priority: project › addon › built-in defaults.
     */
    public static function defaults(?RoutingConfig $config): void
    {
        self::$defaults = $config;
    }

    /** Does the project set the configuration in code? Interfaces then show it read-only. */
    public static function configuredByProject(): bool
    {
        return null !== self::$config;
    }

    // ------------------------------------------------------------------ Registering components

    /**
     * Own address answered before the pages (like #[AsEndpoint]). $match: exact path ("/fluid-live"),
     * prefix with "*" ("/proxy/*", the rest goes to the handler) or fn (string $path): bool. $handler(string $path,
     * string $rest) ends the request itself. early: even before redirecting alias domains (services like SSE, MCP).
     */
    public static function endpoint(string|callable $match, callable $handler, bool $early = false): void
    {
        self::$endpoints[] = ['match' => $match, 'handler' => $handler, 'early' => $early];
    }

    public static function gate(callable $gate): void
    {
        self::$gates[] = $gate;
    }

    /** Dynamische Adressen (ohne Speicherung): fn (string $path, int $lang, array $query): ?RouteMatch */
    public static function resolver(callable $resolver): void
    {
        self::$resolvers[] = $resolver;
    }

    public static function onPage(callable $hook): void
    {
        self::$pageHooks[] = $hook;
    }

    public static function keep(string ...$params): void
    {
        self::$keep = [...self::$keep, ...$params];
    }

    /** @return list<array{match: string|callable, handler: callable, early: bool}> */
    private static function allEndpoints(): array
    {
        if (null === self::$discovered) {
            self::$discovered = [];
            try {
                foreach (ClassDiscovery::getInstance()->discoverByMethodAttribute(AsEndpoint::class) as $e) {
                    if (!$e['isStatic']) {
                        continue;
                    }
                    /** @var AsEndpoint $a */
                    $a = $e['attribute'];
                    $match = null === $a->param ? $a->path : static fn (string $p): bool => null !== self::matches($a->path, $p) || (Core::hasRequest() && Core::getRequest()->query->has($a->param));
                    self::$discovered[] = ['match' => $match, 'handler' => [$e['class'], $e['method']], 'early' => $a->early];
                }
            } catch (Throwable) {
                // Class discovery not available (setup)
            }
        }

        return [...self::$discovered, ...self::$endpoints];
    }

    /** Path of an endpoint if it matches: rest after the prefix (or '') – otherwise null */
    private static function matches(string|callable $match, string $path): ?string
    {
        if (is_callable($match) && !is_string($match)) {
            return $match($path) ? '' : null;
        }
        if (str_ends_with($match, '*')) {
            $prefix = substr($match, 0, -1);

            return str_starts_with($path . '/', $prefix) || str_starts_with($path, $prefix) ? (string) substr($path, strlen($prefix)) : null;
        }

        return $path === $match ? '' : null;
    }

    private static function runEndpoints(string $path, bool $early): bool
    {
        foreach (self::allEndpoints() as $e) {
            if ($e['early'] === $early && null !== ($rest = self::matches($e['match'], $path))) {
                ($e['handler'])($path, $rest);
            }
        }

        return false;
    }

    /** Is the path a registered endpoint? (e.g. so that a gate lets it pass) */
    public static function isEndpoint(string $path): bool
    {
        foreach (self::allEndpoints() as $e) {
            if (null !== self::matches($e['match'], $path)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------ Start

    /** In the frontend: asset, media and controller URLs from the base path (otherwise they break on /page/subpage) */
    public static function boot(): void
    {
        if (self::config()->enabled && Core::isFrontend() && Core::hasRequest()) {
            Url::init(new RootPathProvider(Core::getProject(), Core::getRequest()->getBasePath()));
        }
    }

    /** @internal Address → page, once all addons are loaded (their sources and endpoints are known then) */
    #[AsExtension('PACKAGES_INCLUDED', ExtensionLevel::Early)]
    public static function handleRequest(): void
    {
        if (self::$handled || !self::config()->enabled || !Core::isFrontend() || !Core::hasRequest()) {
            return;
        }
        self::$handled = true;
        self::resolve();
    }

    /** @internal Address of a page; without a path (null) the core address remains */
    #[AsExtension]
    public static function articleUrl(ContentEp\ArticleUrl $ep): ?string
    {
        if (!self::config()->enabled) {
            return null;
        }
        $url = self::url($ep->articleId, $ep->languageId, $ep->params);

        return '' !== $url ? $url : null;
    }

    /** @internal Structure or languages changed → rebuild paths (including automatic redirects) */
    #[AsExtension(ContentEp\ArticleAdded::class)]
    #[AsExtension(ContentEp\ArticleUpdated::class)]
    #[AsExtension(ContentEp\ArticleDeleted::class)]
    #[AsExtension(ContentEp\ArticleMoved::class)]
    #[AsExtension(ContentEp\ArticleCopied::class)]
    #[AsExtension(ContentEp\ArticleStatusChanged::class)]
    #[AsExtension(ContentEp\ArticleConvertedToCategory::class)]
    #[AsExtension(ContentEp\ArticleConvertedToStartArticle::class)]
    #[AsExtension(ContentEp\CategoryAdded::class)]
    #[AsExtension(ContentEp\CategoryUpdated::class)]
    #[AsExtension(ContentEp\CategoryDeleted::class)]
    #[AsExtension(ContentEp\CategoryMoved::class)]
    #[AsExtension(ContentEp\CategoryStatusChanged::class)]
    #[AsExtension(ContentEp\CategoryConvertedToArticle::class)]
    #[AsExtension(LanguageEp\LanguageAdded::class)]
    #[AsExtension(LanguageEp\LanguageUpdated::class)]
    #[AsExtension(LanguageEp\LanguageDeleted::class)]
    public static function structureChanged(): void
    {
        if (self::config()->enabled) {
            Routes::rebuild();
        }
    }

    // ------------------------------------------------------------------ Resolving

    private static function resolve(): void
    {
        $r = Core::getRequest();
        $host = Domains::host($r->getHost());
        $raw = rawurldecode($r->getPathInfo());
        $path = '/' . trim($raw, '/');
        if ('/index.php' === $path) {
            $path = $raw = '/';
        }
        // early endpoints (services like SSE, MCP)
        self::runEndpoints($path, true);
        $query = $r->query->all();
        self::$canRedirect = in_array($r->getMethod(), ['GET', 'HEAD'], true);
        $domain = Domains::forHost($host);

        // Weitere Domain → Hauptadresse
        if (DomainRole::Alias === $domain?->role && ($primary = Domains::primary()) && !Domains::isLocal($host)) {
            self::redirect(self::origin($primary) . $r->getRequestUri(), 301);
        }
        self::$landing = DomainRole::Landing === $domain?->role ? $domain : null;

        // endpoints (sitemap.xml, robots.txt, components) and gates (e.g. „Erste Schritte“ without start page)
        self::runEndpoints($path, false);
        foreach (self::$gates as $gate) {
            $gate($path);
        }

        // ?article_id=…: editor, API, media stay; plain requests → clean URL (301)
        if (isset($query['article_id']) || isset($query['rex_media_type']) || isset($query['rex-api-call'])) {
            if ('/' === $path && isset($query['article_id']) && 'GET' === $r->getMethod() && !array_intersect(self::$keep, array_keys($query))) {
                $id = (int) $query['article_id'];
                $lang = (int) ($query['language'] ?? Language::getStartId());
                unset($query['article_id'], $query['language']);
                if (Article::get($id, $lang) && '' !== ($to = self::url($id, $lang, $query))) {
                    self::redirect(self::absolute($to), 301);
                }
            }
            if ('/' === $path) {
                return;
            }
        }

        // language from the prefix, strip suffix
        [$lang, $rest] = Routes::splitLanguage($path);
        $key = self::config()->format->strip($rest);

        // Landing-Domain: „/“ = Einstiegsseite, Unterseiten relativ dazu
        $full = $key;
        $landingId = self::$landing?->articleId;
        if (null !== $landingId) {
            $base = (string) Routes::path((int) $landingId, $lang);
            $full = '' === $key ? $base : (self::$landing->subtree ? ltrim($base . '/' . $key, '/') : $key);
        }

        $match = self::match($full, $lang, $query);
        if (null === $match && null !== $landingId && !self::$landing->subtree && '' !== $key) {
            // entries of a landing domain's entry page without subpages
            $e = Routes::findEntry(ltrim(Routes::path((int) $landingId, $lang) . '/' . $key, '/'), $lang);
            $match = null !== $e && $e->articleId === $landingId ? self::entryMatch($e) : null;
        }
        if (null !== $match) {
            if (self::$landing && !self::inLanding($match->articleId)) {
                // page outside the landing page → main address
                self::redirect(self::absolute(self::url($match->articleId, $lang, $query)), 302);
            } else {
                // one address per page: other spelling, suffix, slash, language prefix → canonical address
                $want = self::wanted($match, array_diff_key($query, $match->params));
                if ('' !== $want && self::pathOf($want) !== $raw) {
                    self::redirect(self::absolute($want), 301, $match->cacheRedirect);
                }
                if (null === $match->entry && [] === $match->params) {
                    foreach (self::$pageHooks as $hook) {
                        $hook($match->articleId, $lang, $query);
                    }
                }
                self::select($match);

                return;
            }
        }
        // natural address of the start page (/start) → "/"
        $home = Article::getSiteStartArticleId();
        if (!self::$landing && '' !== $key && strtolower($full) === Routes::slug($home, $lang)) {
            self::redirect(self::absolute(self::url($home, $lang, $query)), 301);
        }
        // redirects (also for the spelling without suffix)
        $qs = (string) parse_url($r->getRequestUri(), PHP_URL_QUERY);
        $plain = '/' . ltrim(Routes::prefix($lang) . '/' . $key, '/');
        $hit = Redirects::match($path, $qs) ?? ($plain !== $path ? Redirects::match($plain, $qs) : null);
        if (null !== $hit) {
            Redirects::hit((int) $hit['rule']['id']);
            if (410 === $hit['code']) {
                self::notFound($lang, '410 Gone');

                return;
            }
            self::redirect(self::absolute($hit['location']), $hit['code']);
        }
        Redirects::log404($path);
        self::notFound($lang, Response::HTTP_NOT_FOUND);
    }

    /** Page, source entry or resolver result for a path (without prefix and suffix) */
    private static function match(string $full, int $lang, array $query): ?RouteMatch
    {
        if ('' === $full) {
            return new RouteMatch(Article::getSiteStartArticleId(), $lang);
        }
        $id = Routes::find($full, $lang) ?? Routes::findInsensitive($full, $lang);
        if (null !== $id) {
            return new RouteMatch($id, $lang);
        }
        if (null !== ($e = Routes::findEntry($full, $lang))) {
            return self::entryMatch($e);
        }
        foreach (self::$resolvers as $resolver) {
            $m = $resolver($full, $lang, $query);
            if ($m instanceof RouteMatch) {
                return $m;
            }
        }

        return null;
    }

    private static function entryMatch(RouteEntry $e): RouteMatch
    {
        $alternates = [];
        foreach (Routes::entryLanguages($e->source, $e->id) as $lang) {
            $alternates[$lang] = self::canonicalFor($e->source, $e->id, $lang);
        }

        return new RouteMatch($e->articleId, $e->languageId, $e->params, self::urlFor($e->source, $e->id, $e->languageId), true, null, $alternates, $e);
    }

    /** Canonical address of a match with the request's parameters */
    private static function wanted(RouteMatch $m, array $query): string
    {
        if (null === $m->url) {
            return self::url($m->articleId, $m->languageId, $query);
        }
        if ([] === $query) {
            return $m->url;
        }
        [$u, $q] = array_pad(explode('?', $m->url, 2), 2, '');
        parse_str($q, $own);

        return $u . '?' . http_build_query($query + $own);
    }

    /** Path of an address from the base path, as the request has it (other domain: null-safe different) */
    private static function pathOf(string $url): string
    {
        if (preg_match('~^https?://~i', $url) && strtolower((string) parse_url($url, PHP_URL_HOST)) !== strtolower(Domains::host(Core::getRequest()->getHost()))) {
            return "\0" . $url;
        }
        $p = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $base = self::base();

        return '' !== $base && str_starts_with($p, $base) ? (string) substr($p, strlen($base)) : $p;
    }

    private static function select(RouteMatch $m): void
    {
        $article = Article::get($m->articleId, $m->languageId);
        // offline: only with backend login (preview for editors); entries that no longer exist: 404
        if (!$article || (!$article->isOnline() && !BackendLogin::hasSession())
            || (null !== $m->entry && !(Sources::get($m->entry->source)?->exists($m->entry) ?? false))) {
            self::notFound($m->languageId, Response::HTTP_NOT_FOUND);

            return;
        }
        if ([] !== $m->params) {
            $r = Core::getRequest();
            foreach ($m->params as $k => $v) {
                $r->query->set($k, (string) $v);
                $_GET[$k] = (string) $v;
            }
        }
        self::$current = $m;
        Article::setCurrentId($m->articleId);
        Language::setCurrentId($m->languageId);
    }

    private static function notFound(int $lang, string $status): void
    {
        self::$current = null;
        Article::setCurrentId(Article::getNotfoundArticleId());
        Language::setCurrentId($lang);
        Response::setStatus($status);
    }

    /** Match of the current request (page, source entry, resolver) – null on 404 or outside the frontend */
    public static function current(): ?RouteMatch
    {
        return self::$current;
    }

    // ------------------------------------------------------------------ Adressen

    /**
     * Address of a page: relative on the same domain, absolute to another one (landing ↔ main address) and in the backend.
     * '' = no stored address (core default index.php?article_id=…).
     */
    public static function url(int $id, int $languageId, array $params = []): string
    {
        $languageId = $languageId ?: Language::getCurrentId();
        $path = Routes::path($id, $languageId);

        return null === $path ? '' : self::build($id, $path, Routes::isCategory($id, $languageId), $languageId, $params);
    }

    /** Address of a source entry ('' = none) */
    public static function urlFor(string $source, string|int $id, ?int $languageId = null, array $params = []): string
    {
        $languageId = $languageId ?: Language::getCurrentId();
        $path = Routes::entryPath($source, (string) $id, $languageId);
        $article = null !== $path ? Routes::entryArticle($source, (string) $id, $languageId) : null;

        return null === $article ? '' : self::build($article, $path, false, $languageId, $params);
    }

    /** Address from a path (without prefix and suffix) and the page it belongs to */
    private static function build(int $articleId, string $path, bool $category, int $languageId, array $params): string
    {
        $fmt = self::config()->format;
        $prefix = Routes::prefix($languageId);
        $query = $params ? '?' . http_build_query($params) : '';
        $local = $prefix . ('' === $path ? '/' : '/' . $fmt->append($path, $category));

        if (self::$landing) {
            if (self::inLanding($articleId)) {
                $base = (string) Routes::path((int) self::$landing->articleId, $languageId);
                $rel = $path === $base ? '' : ltrim((string) substr($path, strlen($base)), '/');

                return self::base() . $prefix . '/' . ('' !== $rel ? $fmt->append($rel, $category) : '') . $query;
            }
            $primary = Domains::primary();

            return ($primary ? self::origin($primary) : '') . $local . $query;
        }
        if (Core::isBackend() || !Core::hasRequest()) {
            $primary = Domains::primary();

            return ($primary && !Core::hasRequest() ? self::origin($primary) : self::currentOrigin()) . $local . $query;
        }

        return self::base() . $local . $query;
    }

    /**
     * Language versions of a page: all online languages in which the page is online (for the language switch).
     * With $all also offline ones (for editors). On an entry's address: the entry's language versions.
     *
     * @return list<array{id: int, code: string, name: string, url: string, current: bool, online: bool}>
     */
    public static function alternates(Article $article, bool $all = false): array
    {
        $entry = self::$current?->entry;
        $entry = null !== $entry && $entry->articleId === $article->id ? $entry : null;
        $out = [];
        foreach (Language::getAll() as $l) {
            $a = Article::get($article->id, $l->id);
            $online = $l->isOnline() && $a && $a->isOnline();
            if (!$a || (!$all && !$online)) {
                continue;
            }
            $url = null !== $entry ? self::urlFor($entry->source, $entry->id, $l->id) : self::url($article->id, $l->id);
            if ('' === $url && null !== $entry) {
                $url = self::url($article->id, $l->id);   // entry does not exist in this language: page
            }
            $out[] = ['id' => $l->id, 'code' => $l->code, 'name' => $l->name, 'url' => $url, 'current' => $l->id === $article->languageId, 'online' => $online];
        }

        return $out;
    }

    /** Kanonische, absolute Adresse einer Seite (Landing „own“ → Landing-Domain, sonst Hauptadresse) */
    public static function canonical(int $id, int $languageId): string
    {
        $path = Routes::path($id, $languageId);

        return null === $path ? '' : self::canonicalPath($id, $path, Routes::isCategory($id, $languageId), $languageId);
    }

    /** Canonical absolute address of an entry ('' = none) */
    public static function canonicalFor(string $source, string|int $id, int $languageId): string
    {
        $path = Routes::entryPath($source, (string) $id, $languageId);
        $article = null !== $path ? Routes::entryArticle($source, (string) $id, $languageId) : null;

        return null === $article ? '' : self::canonicalPath($article, $path, false, $languageId);
    }

    private static function canonicalPath(int $id, string $path, bool $category, int $languageId): string
    {
        $fmt = self::config()->format;
        $prefix = Routes::prefix($languageId);
        $a = Article::get($id, $languageId);
        $landing = $a ? Domains::landingFor($id, $a->path) : null;
        if ($landing && $landing->canonicalOwn) {
            $base = (string) Routes::path((int) $landing->articleId, $languageId);
            $rel = $path === $base ? '' : ltrim((string) substr($path, strlen($base)), '/');

            return self::origin($landing) . $prefix . '/' . ('' !== $rel ? $fmt->append($rel, $category) : '');
        }
        $primary = Domains::primary();

        return ($primary ? self::origin($primary) : self::currentOrigin()) . $prefix . ('' === $path ? '/' : '/' . $fmt->append($path, $category));
    }

    private static function inLanding(int $id): bool
    {
        $l = self::$landing;
        if (!$l || null === $l->articleId) {
            return false;
        }
        if ($id === $l->articleId) {
            return true;
        }

        return $l->subtree && in_array($l->articleId, Article::get($id)->path ?? [], true);
    }

    /** Current landing domain (or null) – e.g. for a menu limited to the landing page */
    public static function landing(): ?Domain
    {
        return self::$landing;
    }

    /** Base path of the website (subfolder), without trailing slash */
    public static function base(): string
    {
        if (!Core::hasRequest()) {
            return '';
        }
        $base = Core::getRequest()->getBasePath();
        if (Core::isBackend()) {
            $base = substr($base, 0, (int) strrpos($base, '/'));
        }

        return rtrim($base, '/');
    }

    public static function origin(Domain $d): string
    {
        if (Core::hasRequest() && Domains::host(Core::getRequest()->getHost()) === $d->host) {
            return self::currentOrigin();
        }

        // Nicht-Standard-Port (lokale Entwicklung) mitnehmen
        $port = Core::hasRequest() ? (int) Core::getRequest()->getPort() : 443;
        $portPart = in_array($port, [80, 443], true) ? '' : ':' . $port;

        return (Core::hasRequest() ? Core::getRequest()->getScheme() : 'https') . '://' . $d->host . $portPart . self::base();
    }

    private static function currentOrigin(): string
    {
        return Core::hasRequest() ? Core::getRequest()->getSchemeAndHttpHost() . self::base() : rtrim((string) Core::getProject()->baseUrl, '/');
    }

    public static function absolute(string $url): string
    {
        return preg_match('~^https?://~i', $url) ? $url : self::currentOrigin() . (str_starts_with($url, self::base() . '/') ? substr($url, strlen(self::base())) : $url);
    }

    /**
     * Redirect (only for GET/HEAD) – for components; relative addresses become absolute.
     *
     * @param bool $cache cache the 301 for an hour – not for addresses that may change (title of a record)
     */
    public static function redirectTo(string $url, int $code = 301, bool $cache = true): void
    {
        self::redirect(self::absolute($url), $code, $cache);
    }

    private static function redirect(string $url, int $code, bool $cache = true): void
    {
        if (!self::$canRedirect) {
            return;
        }
        header('Location: ' . (string) preg_replace_callback('~[^\x21-\x7E]+~', static fn ($m) => rawurlencode($m[0]), $url), true, $code);
        header('Cache-Control: ' . (301 === $code && $cache ? 'public, max-age=3600' : 'no-cache'));
        if ('' !== self::config()->redirectBy) {
            header('X-Redirect-By: ' . self::config()->redirectBy);
        }
        exit;
    }

    /**
     * Send the response and exit (for endpoints and resolvers of components).
     *
     * @param list<string> $headers additional headers
     */
    public static function respond(string $body, string $type, array $headers = []): never
    {
        header('Content-Type: ' . $type);
        foreach ($headers as $h) {
            header($h);
        }
        echo $body;
        exit;
    }

    // ------------------------------------------------------------------ eigene Endpunkte

    /** @internal Keep sitemap and robots.txt out of proxy caches (otherwise the old one stays valid long after a change) */
    #[AsEndpoint('/sitemap.xml')]
    public static function sitemap(): void
    {
        self::respond(Sitemap::xml(self::$landing) ?? '', 'application/xml; charset=utf-8', ['Cache-Control: no-cache']);
    }

    /** @internal Teile ab Sitemap::MAX Adressen: /sitemap-1.xml … */
    #[AsEndpoint('/sitemap-*')]
    public static function sitemapPart(string $path, string $rest): void
    {
        if (preg_match('~^([1-9][0-9]*)\.xml$~', $rest, $m) && null !== ($xml = Sitemap::xml(self::$landing, (int) $m[1]))) {
            self::respond($xml, 'application/xml; charset=utf-8', ['Cache-Control: no-cache']);
        }
    }

    /** @internal */
    #[AsEndpoint('/sitemap.xsl')]
    public static function sitemapXsl(): void
    {
        self::respond(Sitemap::xsl(), 'text/xsl; charset=utf-8');
    }

    /** @internal */
    #[AsEndpoint('/robots.txt')]
    public static function robots(): void
    {
        self::respond(Robots::text(), 'text/plain; charset=utf-8', ['Cache-Control: no-cache']);
    }
}
