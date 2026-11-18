<?php

namespace Klxm\Fluid;

use Klxm\Fluid\Data\Detail;
use Klxm\Fluid\Routing\Routes;
use Klxm\Fluid\Routing\Router;
use Klxm\Fluid\Routing\Sources;
use Override;
use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Throwable;

use function array_any;
use function array_slice;
use function is_string;
use function mb_stripos;
use function mb_substr;
use function trim;

/**
 * Ziele für den Link-Dialog der Formatierungsleiste (nur lesend, angemeldete Redakteure; Backend und Website):
 *  - `?rex-api-call=fluid_links&kind=records&q=…`  Datensätze mit eigener Adresse: Datenlisten mit Detailansicht
 *    (ref detail://seite/id) und Route-Quellen (ref route://quelle/id)
 *  - `?rex-api-call=fluid_links&kind=anchors&article=ID`  Sprungmarken der Blöcke einer Seite → #anker
 */
#[AsApiFunction('fluid_links')]
final class LinkApi extends ApiFunction
{
    protected bool $published = true;
    protected bool $requiresCsrfProtection = false;

    #[Override]
    public function execute(): Result
    {
        $user = Core::isBackend() ? Core::getUser() : Editing::user();
        Response::cleanOutputBuffers();
        Response::setHeader('Cache-Control', 'no-store');
        if (null === $user) {
            Response::setStatus(Response::HTTP_FORBIDDEN);
            Response::sendJson(['error' => 'forbidden']);
            exit;
        }
        $language = Request::get('language', 'int', 0) ?: Language::getCurrentId();
        Response::sendJson(match (Request::get('kind', 'string', '')) {
            'records' => ['items' => self::records(mb_substr(trim(Request::get('q', 'string', '')), 0, 100), $language)],
            'anchors' => ['items' => self::anchors(Request::get('article', 'int', 0), $language)],
            default => ['items' => []],
        });
        exit;
    }

    /**
     * Datensätze aller Route-Quellen (News, Termine, Projekte …) in der Sprache; Suche in Titel und Adresse.
     *
     * @return list<array{ref: string, label: string, meta: string, url: string}>
     */
    public static function records(string $q, int $language): array
    {
        $out = [];
        $match = static fn (string ...$texts): bool => '' === $q || array_any($texts, static fn (string $t) => false !== mb_stripos($t, $q));
        // Datenlisten mit Detailansicht: Einträge unter der Adresse ihrer Seite (/seite/titel-ID)
        $pages = Sql::factory()->getArray("SELECT DISTINCT article_id FROM rex_article_slice WHERE language_id = ? AND revision = ? AND status = 1 AND module LIKE '%.datalist'",
            [$language, ArticleRevision::LIVE]);
        foreach ($pages as $row) {
            $pid = (int) $row['article_id'];
            $cfg = Detail::config($pid, $language);
            $page = Article::get($pid, $language);
            if (null === $cfg || null === $page) {
                continue;
            }
            $pageUrl = Router::url($pid, $language);
            foreach (Detail::entries($cfg) as [$eid, $title]) {
                if (!$match((string) $title, $page->name)) {
                    continue;
                }
                $out[] = ['ref' => 'detail://' . $pid . '/' . $eid, 'label' => (string) $title, 'meta' => $page->name, 'url' => Detail::url($pageUrl, (string) $title, (int) $eid)];
                if (\count($out) >= 60) {
                    return $out;
                }
            }
        }
        // Route-Quellen (eigene Adressen, z. B. News, Termine)
        foreach (Sources::all() as $source) {
            foreach (Routes::entries($source->key()) as $entry) {
                if ($entry->languageId !== $language) {
                    continue;
                }
                try {
                    $title = $source->meta($entry)?->title;
                } catch (Throwable) {
                    $title = null;
                }
                $label = is_string($title) && '' !== $title ? $title : $entry->slug;
                if (!$match($label, (string) $entry->path, $source->label())) {
                    continue;
                }
                $out[] = [
                    'ref' => 'route://' . $source->key() . '/' . $entry->id,
                    'label' => $label,
                    'meta' => $source->label(),
                    'url' => Router::urlFor($source->key(), $entry->id, $language),
                ];
                if (\count($out) >= 60) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /**
     * Sprungmarken der Blöcke einer Seite (Feld „Sprungmarke“ unter „Abschnitt“).
     *
     * @return list<array{anchor: string, label: string}>
     */
    public static function anchors(int $articleId, int $language): array
    {
        if (null === Article::get($articleId, $language)) {
            return [];
        }
        $out = [];
        foreach (ArticleSlice::getSlicesForArticle($articleId, $language) as $slice) {
            $tunes = BlockModule::tunesOf($slice);
            $anchor = trim((string) ($tunes['anchor'] ?? ''));
            if ('' === $anchor) {
                continue;
            }
            $values = BlockModule::valuesOf($slice);
            $title = trim(strip_tags((string) ($values['title'] ?? $values['eyebrow'] ?? '')));
            $out[] = ['anchor' => $anchor, 'label' => '' !== $title ? $title : BlockOps::label($slice->moduleKey)];
        }

        return array_slice($out, 0, 200);
    }
}
