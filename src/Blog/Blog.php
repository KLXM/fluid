<?php

namespace Klxm\Fluid\Blog;

use Klxm\Fluid\PageSettings;
use Klxm\Fluid\Routing\ExtensionPoint\PageMetaResolved;
use Klxm\Fluid\Routing\RouteMatch;
use Klxm\Fluid\Routing\Router;
use Klxm\Fluid\Routing\Routes;
use Klxm\Fluid\Routing\Seo\HeadTag;
use Klxm\Fluid\Routing\Seo\PageMeta;
use Klxm\Fluid\Settings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaManager\MediaManager;

use function array_slice;
use function count;
use function in_array;
use function Redaxo\Core\View\escape;

use const DATE_RSS;

/**
 * Blog / news: a category (menu item) becomes a blog in its page settings – its pages are then posts.
 * Posts are normal articles with blocks (draft, versions, permissions, addresses, languages, search – all from the rest);
 * plus date, author and tags (table rex_fluid_page). Teaser image = image for social networks,
 * short text = description for search engines. Sorted by date, newest first; RSS at {blog}/feed.xml.
 *
 *     Blog::posts($blogId, ['limit' => 3]);          // newest three posts
 *     Blog::blogOf(Article::getCurrent());           // ID of the blog if the page is a post
 */
final class Blog
{
    /** @var array<int, bool>|null */
    private static ?array $blogs = null;

    /** Register with routing and SEO: RSS /{blog}/feed.xml, feed link in the page head, schema.org Blog/BlogPosting */
    public static function boot(): void
    {
        Router::resolver(static function (string $full, int $lang): ?RouteMatch {
            if (preg_match('~^(.+)/feed\.xml$~', $full, $m) && null !== ($bid = Routes::find($m[1], $lang)) && self::isBlog($bid)) {
                Language::setCurrentId($lang);
                Router::respond(self::feed($bid), 'application/rss+xml; charset=utf-8');
            }

            return null;
        });
        // feed link in the page head of the overview and the posts
        Extension::register(PageMetaResolved::class, static function (PageMetaResolved $ep): PageMeta {
            $article = $ep->article;
            $blog = self::isBlog($article->id) && $article->isStartArticle() ? $article->id : self::blogOf($article);
            if ($blog && ($b = Article::get($blog, $article->languageId))) {
                $feed = self::feedUrl($blog, $article->languageId);
                $ep->subject->add(HeadTag::link('alternate', $feed, ['type' => 'application/rss+xml', 'title' => $b->name]));
            }

            return $ep->subject;
        });
        // overview = Blog, post = BlogPosting (date, author, image)
        Extension::register('FLUID_STRUCTURED_DATA', static function (ExtensionPoint $ep): array {
            $data = (array) $ep->subject;
            $article = $ep->getParam('article');
            $url = (string) $ep->getParam('url');
            $org = (string) $ep->getParam('org');
            if (self::isBlog($article->id) && $article->isStartArticle()) {
                $data['@graph'][] = ['@type' => 'Blog', '@id' => $url . '#blog', 'url' => $url, 'name' => $article->name, 'mainEntityOfPage' => ['@id' => $url . '#webpage'], 'publisher' => ['@id' => $org]];
            } elseif (null !== ($blog = self::blogOf($article)) && ($p = Post::get($article->id, $article->languageId))) {
                $data['@graph'][] = array_filter([
                    '@type' => 'BlogPosting', '@id' => $url . '#post', 'headline' => mb_substr($article->name, 0, 110), 'url' => $url,
                    'datePublished' => $p->date->format('Y-m-d'), 'dateModified' => $article->updateDate ? date('c', $article->updateDate) : null,
                    'author' => '' !== $p->author ? ['@type' => 'Person', 'name' => $p->author] : ['@id' => $org],
                    'publisher' => ['@id' => $org], 'description' => '' !== $p->excerpt ? $p->excerpt : null,
                    'image' => '' !== $p->image ? Router::absolute(MediaManager::getUrl('rex_media_large', $p->image)) : null,
                    'keywords' => $p->tags ? implode(', ', $p->tags) : null, 'inLanguage' => (string) $ep->getParam('lang'),
                    'mainEntityOfPage' => ['@id' => $url . '#webpage'], 'isPartOf' => ['@id' => Router::canonical($blog, $article->languageId) . '#blog'],
                ]);
            }

            return $data;
        });
    }

    /** Address of the RSS feed of a blog: {blog}/feed.xml (without the extension of the URL format) */
    public static function feedUrl(int $blogId, int $languageId): string
    {
        return rtrim(Router::config()->format->strip(Router::canonical($blogId, $languageId)), '/') . '/feed.xml';
    }

    /** Categories that are blogs: [ID => name] */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::ids()) as $id) {
            if ($c = Category::get($id)) {
                $out[$id] = $c->name;
            }
        }

        return $out;
    }

    private static function ids(): array
    {
        if (null === self::$blogs) {
            self::$blogs = [];
            foreach (Sql::factory()->getArray('SELECT DISTINCT article_id FROM ' . PageSettings::TABLE . ' WHERE blog = 1') as $r) {
                self::$blogs[(int) $r['article_id']] = true;
            }
        }

        return self::$blogs;
    }

    public static function isBlog(int $categoryId): bool
    {
        return isset(self::ids()[$categoryId]);
    }

    /** Blog category if the page is a post (article, not a start article, in a blog category) */
    public static function blogOf(?Article $a): ?int
    {
        return $a && !$a->isStartArticle() && $a->categoryId && self::isBlog($a->categoryId) ? $a->categoryId : null;
    }

    public static function setBlog(int $categoryId, bool $on): void
    {
        foreach (Language::getAll() as $l) {
            Sql::factory()->setQuery('INSERT INTO ' . PageSettings::TABLE . ' (article_id, language_id, blog) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE blog = VALUES(blog)',
                [$categoryId, $l->id, $on ? 1 : 0]);
        }
        self::$blogs = null;
    }

    /** Date, author, tags of a post (per language; the date applies to all languages) */
    public static function saveMeta(Article $a, string $date, string $author, string $tags): void
    {
        $date = preg_match('~^\d{4}-\d{2}-\d{2}$~', $date) ? $date : null;
        $tags = implode(', ', self::splitTags($tags));
        Sql::factory()->setQuery('INSERT INTO ' . PageSettings::TABLE . ' (article_id, language_id, author, tags) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE author = VALUES(author), tags = VALUES(tags)',
            [$a->id, $a->languageId, mb_substr(trim($author), 0, 120), mb_substr($tags, 0, 255)]);
        foreach (Language::getAll() as $l) {
            Sql::factory()->setQuery('INSERT INTO ' . PageSettings::TABLE . ' (article_id, language_id, post_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE post_date = VALUES(post_date)',
                [$a->id, $l->id, $date]);
        }
    }

    /** @return list<string> */
    public static function splitTags(string $tags): array
    {
        $out = [];
        foreach (preg_split('~[,;]~', $tags) ?: [] as $t) {
            $t = trim(preg_replace('~\s+~u', ' ', $t) ?? '');
            if ('' !== $t && !in_array(mb_strtolower($t), array_map('mb_strtolower', $out), true)) {
                $out[] = mb_substr($t, 0, 40);
            }
        }

        return array_slice($out, 0, 12);
    }

    public static function tagSlug(string $tag): string
    {
        return Routes::slugify($tag);
    }

    /**
     * Posts of a blog (0 = all blogs), newest first; online only (except $drafts for the editorial team).
     *
     * @param array{limit?: int, offset?: int, tag?: string, drafts?: bool, language?: int, exclude?: int} $opt
     * @return array{total: int, posts: list<Post>}
     */
    public static function posts(int $blogId, array $opt = []): array
    {
        $lang = $opt['language'] ?? Language::getCurrentId();
        $blogIds = $blogId ? [$blogId] : array_keys(self::ids());
        if (!$blogIds) {
            return ['total' => 0, 'posts' => []];
        }
        $where = 'a.parent_id IN (' . implode(',', array_map('intval', $blogIds)) . ') AND a.id NOT IN (SELECT id FROM rex_category)';
        $params = [$lang];
        if (empty($opt['drafts'])) {
            $where .= ' AND t.status = 1';
        }
        if (!empty($opt['exclude'])) {
            $where .= ' AND a.id <> ' . (int) $opt['exclude'];
        }
        $tag = trim((string) ($opt['tag'] ?? ''));
        $sql = 'SELECT a.id, p.post_date, p.tags, a.createdate FROM rex_article a JOIN rex_article_translation t ON t.article_id = a.id AND t.language_id = ?'
            . ' LEFT JOIN ' . PageSettings::TABLE . ' p ON p.article_id = a.id AND p.language_id = t.language_id WHERE ' . $where
            . ' ORDER BY COALESCE(p.post_date, DATE(a.createdate)) DESC, a.id DESC';
        $rows = Sql::factory()->getArray($sql, $params);
        if ('' !== $tag) {
            $rows = array_values(array_filter($rows, static fn (array $r) => in_array($tag, array_map(self::tagSlug(...), self::splitTags((string) $r['tags'])), true)));
        }
        $total = count($rows);
        $rows = array_slice($rows, max(0, (int) ($opt['offset'] ?? 0)), ($opt['limit'] ?? 0) > 0 ? (int) $opt['limit'] : null);
        $posts = [];
        foreach ($rows as $r) {
            if ($p = Post::get((int) $r['id'], $lang)) {
                $posts[] = $p;
            }
        }

        return ['total' => $total, 'posts' => $posts];
    }

    /** Previous (older) and next (newer) post */
    public static function neighbors(Article $a): array
    {
        $blog = self::blogOf($a);
        if (!$blog) {
            return [null, null];
        }
        $all = self::posts($blog, ['language' => $a->languageId])['posts'];
        $ids = array_map(static fn (Post $p) => $p->article->id, $all);
        $i = array_search($a->id, $ids, true);
        if (false === $i) {
            return [null, null];
        }

        return [$all[$i + 1] ?? null, $all[$i - 1] ?? null];
    }

    /** All tags of a blog with count: [slug => [name, count]] */
    public static function tags(int $blogId): array
    {
        $out = [];
        foreach (self::posts($blogId)['posts'] as $p) {
            foreach ($p->tags as $t) {
                $s = self::tagSlug($t);
                $out[$s] = [$out[$s][0] ?? $t, ($out[$s][1] ?? 0) + 1];
            }
        }
        uasort($out, static fn ($x, $y) => $y[1] <=> $x[1] ?: strcmp($x[0], $y[0]));

        return $out;
    }

    /** RSS 2.0 of a blog (language of the request) */
    public static function feed(int $blogId): string
    {
        $lang = Language::getCurrentId();
        $blog = Article::get($blogId, $lang);
        $site = Settings::name();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
            . '<title>' . escape(($blog->name ?? 'Blog') . ' – ' . $site) . '</title>'
            . '<link>' . escape(Router::canonical($blogId, $lang)) . '</link>'
            . '<description>' . escape((string) (PageSettings::get($blogId, $lang)['description'] ?: $site)) . '</description>'
            . '<language>' . escape(Language::get($lang)->code ?? 'de') . '</language>'
            . '<atom:link href="' . escape(self::feedUrl($blogId, $lang)) . '" rel="self" type="application/rss+xml"/>';
        foreach (self::posts($blogId, ['limit' => 30])['posts'] as $p) {
            $url = Router::canonical($p->article->id, $lang);
            $xml .= '<item><title>' . escape($p->article->name) . '</title><link>' . escape($url) . '</link><guid isPermaLink="true">' . escape($url) . '</guid>'
                . '<pubDate>' . $p->date->format(DATE_RSS) . '</pubDate>'
                . ('' !== $p->excerpt ? '<description>' . escape($p->excerpt) . '</description>' : '')
                . implode('', array_map(static fn ($t) => '<category>' . escape($t) . '</category>', $p->tags))
                . '</item>';
        }

        return (string) Extension::dispatch(new ExtensionPoint('FLUID_BLOG_FEED', $xml . '</channel></rss>', ['blog' => $blogId]));
    }
}
