<?php

namespace Klxm\Fluid\Seo;

use Klxm\Routes\Advice;
use Klxm\Routes\Router;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Translation\I18n;

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
        $h = self::score($a['score']) . '<p class="site-intro">' . I18n::msg('fluid_seo_intro') . '</p>';
        $groups = ['todo' => I18n::msg('fluid_seo_todo'), 'tip' => I18n::msg('fluid_seo_tips'), 'ok' => I18n::msg('fluid_seo_ok')];
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

        return $h . '<p class="help-block">' . I18n::msg('fluid_seo_page_help') . '</p>';
    }

    private static function site(Article $article): string
    {
        $rows = Advice::site(Router::site(), $article->languageId);
        if ([] === $rows) {
            return '<p class="site-intro">' . I18n::msg('fluid_seo_site_done') . '</p>';
        }
        $h = '<p class="site-intro">' . I18n::msg('fluid_seo_site_intro', count($rows)) . '</p><ul class="site-list" role="list">';
        foreach ($rows as $r) {
            $parts = array_filter([[] !== $r['open'] ? I18n::rawMsg('fluid_seo_todo_list', implode(', ', $r['open'])) : '', [] !== $r['tipTopics'] ? I18n::rawMsg('fluid_seo_tips_list', implode(', ', $r['tipTopics'])) : '']);
            $h .= '<li class="site-list__item"><a class="site-list__main advice__row" href="' . escape($r['url']) . '"><span><b>' . escape($r['name']) . '</b>'
                . '<small>' . escape(implode(' · ', $parts)) . '</small></span>' . self::badge($r['score']) . '</a></li>';
        }

        return $h . '</ul>';
    }

    private static function score(int $score): string
    {
        $word = I18n::rawMsg($score >= 85 ? 'fluid_seo_score_great' : ($score >= 65 ? 'fluid_seo_score_good' : 'fluid_seo_score_poor'));

        return '<div class="advice__score" data-level="' . ($score >= 85 ? 'ok' : ($score >= 65 ? 'tip' : 'todo')) . '"><span>' . $score . '</span><small>' . I18n::msg('fluid_seo_score', $word) . '</small></div>';
    }

    private static function badge(int $score): string
    {
        return '<span class="advice__badge" data-level="' . ($score >= 85 ? 'ok' : ($score >= 65 ? 'tip' : 'todo')) . '">' . $score . '</span>';
    }
}
