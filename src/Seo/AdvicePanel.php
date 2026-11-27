<?php

namespace Klxm\Fluid\Seo;

use Klxm\Routes\Advice;
use Klxm\Routes\Router;
use Redaxo\Core\Content\Article;

use function count;
use function Redaxo\Core\View\escape;

/**
 * Sidebar „SEO & KI“ on the website: what this page or the whole website can do better for search engines and AI
 * answers ({@see Advice} of klxm/routes) – in plain language, with a score.
 *
 * @internal
 */
final class AdvicePanel
{
    public static function render(Article $article, string $mode): string
    {
        return 'site' === $mode ? self::site($article) : self::page($article);
    }

    private static function page(Article $article): string
    {
        $a = Advice::page($article);
        $h = self::score($a['score']) . '<p class="site-intro">So finden Suchmaschinen und KI-Assistenten (ChatGPT, Claude, Perplexity …) diese Seite – und zitieren sie.</p>';
        $groups = ['todo' => 'Offen', 'tip' => 'Tipps', 'ok' => 'Gut'];
        foreach ($groups as $level => $label) {
            $items = array_values(array_filter($a['hints'], static fn (array $x): bool => $x['level'] === $level));
            if ([] === $items) {
                continue;
            }
            $list = '<ul class="advice" role="list">';
            foreach ($items as $x) {
                $list .= '<li class="advice__item is-' . $level . '"><b>' . escape($x['topic']) . '</b> ' . escape($x['text']) . '</li>';
            }
            $list .= '</ul>';
            $h .= 'ok' === $level ? '<details class="site-more"><summary>' . $label . ' (' . count($items) . ')</summary>' . $list . '</details>'
                : '<h3 class="site-h">' . $label . ' (' . count($items) . ')</h3>' . $list;
        }

        return $h . '<p class="help-block">Titel, Beschreibung und Bild ändern Sie in den Seiteneinstellungen.</p>';
    }

    private static function site(Article $article): string
    {
        $rows = Advice::site(Router::site(), $article->languageId);
        if ([] === $rows) {
            return '<p class="site-intro">Alles erledigt – keine offenen Punkte auf den Seiten dieser Website.</p>';
        }
        $h = '<p class="site-intro">' . count($rows) . ' Seiten mit offenen Punkten, die wichtigsten zuerst. Ein Klick öffnet die Seite – dort zeigt „Diese Seite“ alle Hinweise, auch zum Inhalt.</p><ul class="site-list" role="list">';
        foreach ($rows as $r) {
            $parts = array_filter([[] !== $r['open'] ? 'Offen: ' . implode(', ', $r['open']) : '', [] !== $r['tipTopics'] ? 'Tipps: ' . implode(', ', $r['tipTopics']) : '']);
            $h .= '<li class="site-list__item"><a class="site-list__main advice__row" href="' . escape($r['url']) . '"><span><b>' . escape($r['name']) . '</b>'
                . '<small>' . escape(implode(' · ', $parts)) . '</small></span>' . self::badge($r['score']) . '</a></li>';
        }

        return $h . '</ul>';
    }

    private static function score(int $score): string
    {
        $word = $score >= 85 ? 'sehr gut' : ($score >= 65 ? 'gut, mit Luft nach oben' : 'ausbaufähig');

        return '<div class="advice__score" data-level="' . ($score >= 85 ? 'ok' : ($score >= 65 ? 'tip' : 'todo')) . '"><span>' . $score . '</span><small>von 100 – ' . $word . '</small></div>';
    }

    private static function badge(int $score): string
    {
        return '<span class="advice__badge" data-level="' . ($score >= 85 ? 'ok' : ($score >= 65 ? 'tip' : 'todo')) . '">' . $score . '</span>';
    }
}
