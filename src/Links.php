<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;

use const ENT_QUOTES;
use const JSON_HEX_AMP;
use const JSON_HEX_TAG;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Artikel der Struktur für die Linkauswahl der Formatierungsleiste (`<script type="application/json" id="blocks-links">`).
 * Gespeichert wird `href` (aktuelle Adresse) + `data-link="redaxo://ID"`; die Ausgabe aktualisiert die Adresse.
 */
final class Links
{
    /**
     * Artikel in der Reihenfolge des Strukturbaums: depth = Einrückung, category = Startartikel einer Kategorie.
     *
     * @return list<array{id: int, name: string, path: string, url: string, online: bool, depth: int, category: bool}>
     */
    public static function all(?int $languageId = null): array
    {
        $languageId ??= Language::getCurrentId();
        $out = [];
        $add = static function (Article $a, string $path, int $depth, bool $category) use (&$out): void {
            $out[] = ['id' => $a->id, 'name' => $a->name, 'path' => $path, 'url' => $a->getUrl(), 'online' => $a->isOnline(), 'depth' => $depth, 'category' => $category];
        };
        $walk = static function (array $cats, string $path, int $depth) use (&$walk, $add): void {
            foreach ($cats as $c) {
                /** @var Category $c */
                $p = '' === $path ? $c->name : $path . ' › ' . $c->name;
                foreach ($c->getArticles() as $a) {
                    $start = $a->isStartArticle();
                    $add($a, $start ? $p : $p . ' › ' . $a->name, $start ? $depth : $depth + 1, $start);
                }
                $walk($c->getChildren(), $p, $depth + 1);
            }
        };
        foreach (Article::getRootArticles(false, $languageId) as $a) {
            $add($a, 'Hauptebene', 0, false);
        }
        $walk(Category::getRootCategories(false, $languageId), '', 0);

        return $out;
    }

    public static function json(?int $languageId = null): string
    {
        $languageId ??= Language::getCurrentId();
        // aktuelle Seite (Website: angezeigter Artikel, Backend: bearbeiteter Artikel) – für die Sprungmarken
        $article = Core::isBackend() ? Request::get('article_id', 'int', 0) : Article::getCurrentId();
        $api = Core::isBackend()
            ? Url::backendController(LinkApi::getUrlParams())
            : Url::frontendController(LinkApi::getUrlParams());

        return '<script type="application/json" id="blocks-links" data-language="' . $languageId . '" data-article="' . $article . '"'
            . ' data-api="' . htmlspecialchars($api, ENT_QUOTES) . '" data-media="' . htmlspecialchars(Url::media(), ENT_QUOTES) . '">'
            . json_encode(self::all($languageId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
}
