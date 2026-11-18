<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Language\Language;

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
        return '<script type="application/json" id="blocks-links" data-language="' . ($languageId ?? Language::getCurrentId()) . '">'
            . json_encode(self::all($languageId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
}
