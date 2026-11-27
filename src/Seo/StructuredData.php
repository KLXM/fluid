<?php

namespace Klxm\Fluid\Seo;

use Klxm\Fluid\Block;
use Klxm\Fluid\Editing;
use Klxm\Fluid\PageSettings;
use Klxm\Fluid\Routing\Router;
use Klxm\Fluid\Routing\Seo\PageMeta;
use Klxm\Fluid\Settings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;

use function count;
use function in_array;
use function is_array;
use function is_callable;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * Structured data (schema.org, JSON-LD) as a linked @graph per page (modeled on KLXM Studio – Core\StructuredData):
 *  - Organization: complete on the start page, otherwise as a reference (website settings, Head::organizationNode).
 *  - WebSite, WebPage, BreadcrumbList: automatic.
 *  - Blocks declare their data in the kit: 'jsonld' => ['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a']
 *    or a function fn(Block $b): ?array. Collected during rendering (not in edit mode).
 *  - Components add further nodes (blog, geo coordinates …) via the extension point FLUID_STRUCTURED_DATA.
 */
final class StructuredData
{
    /** @var list<array<string, mixed>> */
    private static array $nodes = [];
    private static int $faq = 0;

    public static function collect(Block $b): void
    {
        if (Editing::active()) {
            return;
        }
        $spec = $b->module::definition()['jsonld'] ?? null;
        if (is_callable($spec)) {
            if ($n = $spec($b)) {
                self::$nodes[] = $n;
            }

            return;
        }
        if (is_array($spec) && 'faq' === ($spec['type'] ?? '')) {
            $qs = [];
            foreach ((array) ($b->data[$spec['items'] ?? 'items'] ?? []) as $it) {
                $q = trim(strip_tags((string) preg_replace('~\*([^*]+)\*~u', '$1', (string) ($it[$spec['question'] ?? 'q'] ?? ''))));
                $a = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags((string) ($it[$spec['answer'] ?? 'a'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
                if ('' !== $q && '' !== $a && !str_contains($q . $a, '[')) {
                    $qs[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
                }
            }
            if ($qs) {
                self::$nodes[] = ['@type' => 'FAQPage', '@id' => '#faq' . (++self::$faq), 'mainEntity' => $qs];
            }
        }
    }

    /**
     * @param PageMeta|null $meta data of the page head – on an entry's address its address, title, text
     * @return array<string, mixed>
     */
    public static function graph(Article $article, ?PageMeta $meta = null): array
    {
        $pageUrl = Router::canonical($article->id, $article->languageId);
        $url = $meta?->url ?? $pageUrl;
        $entry = $url !== $pageUrl;
        $home = Router::canonical(Article::getSiteStartArticleId(), $article->languageId);
        $lang = Language::get($article->languageId)->code ?? 'de';
        $org = Head::organizationNode();
        $isHome = $article->id === Article::getSiteStartArticleId();
        $graph = [];
        $graph[] = $isHome ? $org : ['@type' => $org['@type'], '@id' => $org['@id'], 'name' => $org['name'], 'url' => $org['url']];
        $graph[] = ['@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => Settings::name(), 'inLanguage' => $lang, 'publisher' => ['@id' => $org['@id']]];
        // Brotkrumen: Startseite › Elternseiten › Seite
        $crumbs = [];
        if (!$isHome) {
            $crumbs[] = [Article::getSiteStartArticle()->name ?? 'Start', $home];
            foreach ($article->path as $cid) {
                if (($c = Category::get($cid, $article->languageId)) && $c->isOnline()) {
                    $crumbs[] = [$c->name, Router::canonical($c->id, $article->languageId)];
                }
            }
            if (!$article->isStartArticle() || !in_array($article->id, $article->path, true)) {
                $crumbs[] = [$article->name, $pageUrl];
            }
        }
        if ($entry && null !== $meta) {
            $crumbs[] = [(string) $meta->title, $url];   // entry below the page
        }
        $s = PageSettings::get($article->id, $article->languageId);
        $page = array_filter([
            '@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url,
            'name' => null !== $meta ? ($meta->documentTitle ?? $meta->title) : ($s['title'] ?: $article->name),
            'description' => null !== $meta ? $meta->description : ($s['description'] ?: (string) Settings::get('default_meta_description', '') ?: null), 'inLanguage' => $lang,
            'isPartOf' => ['@id' => $home . '#website'], 'breadcrumb' => count($crumbs) > 1 ? ['@id' => $url . '#breadcrumb'] : null,
            'dateModified' => null !== $meta?->modified ? date('c', $meta->modified) : ($article->updateDate ? date('c', $article->updateDate) : null),
        ]);
        if (count($crumbs) > 1) {
            $items = [];
            foreach ($crumbs as $i => [$name, $href]) {
                $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $href];
            }
            $graph[] = ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $items];
        }
        $graph[] = $page;
        foreach (self::$nodes as $n) {
            if (isset($n['@id']) && str_starts_with((string) $n['@id'], '#')) {
                $n['@id'] = $url . $n['@id'];
            }
            $graph[] = $n;
        }

        return (array) Extension::dispatch(new ExtensionPoint('FLUID_STRUCTURED_DATA',
            ['@context' => 'https://schema.org', '@graph' => $graph], ['article' => $article, 'url' => $url, 'org' => $org['@id'], 'lang' => $lang]));
    }
}
