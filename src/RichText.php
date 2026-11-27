<?php

namespace Klxm\Fluid;

use Klxm\Fluid\Data\Detail;
use Klxm\Fluid\Routing\Router;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Util\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

use function in_array;

use const ENT_QUOTES;

/**
 * Rich Text sicher ausgeben – Klassenvertrag der Formatierungsleiste aus KLXM Studio (Core\Sanitizer):
 *   <p class="t-lead|t-small|t-note|t-tip|t-warn|t-kicker|t-center">, <span class="c-accent|c-muted|c-success|c-warning|c-danger">,
 *   <ul class="check">, <mark>, <code>, <sup>, <sub>, Überschriften h2–h4, Zitat, Listen, Links (auch data-link="redaxo://ID").
 * Keine style-Attribute; Links auf Artikel bekommen bei der Ausgabe die aktuelle Adresse.
 */
final class RichText
{
    public const array P_CLASSES = ['t-lead', 't-small', 't-note', 't-tip', 't-warn', 't-kicker', 't-center'];
    public const array COLORS = ['accent', 'muted', 'success', 'warning', 'danger'];

    public static function html(?string $html): string
    {
        $html = trim((string) $html);
        if ('' === $html) {
            return '';
        }
        // Reiner Text aus einem Textfeld → Absätze
        if (!preg_match('~<(p|h[2-4]|ul|ol|blockquote)\b~i', $html)) {
            $html = '<p>' . preg_replace('~\R{2,}~', '</p><p>', nl2br(htmlspecialchars($html, ENT_QUOTES), false)) . '</p>';
        }
        $html = Str::sanitizeHtml($html, self::config());

        // Klassen auf den Vertrag beschränken
        $html = (string) preg_replace_callback('~<(p|span|ul)\b([^>]*)\sclass="([^"]*)"~i', static function (array $m): string {
            $keep = array_filter(explode(' ', $m[3]), static fn (string $c) => match (strtolower($m[1])) {
                'p' => in_array($c, self::P_CLASSES, true),
                'span' => str_starts_with($c, 'c-') && in_array(substr($c, 2), self::COLORS, true),
                default => 'check' === $c,
            });

            return '<' . $m[1] . $m[2] . ($keep ? ' class="' . implode(' ', $keep) . '"' : '');
        }, $html);

        // Links: aktuelle Adresse aus data-link – redaxo://ID (Seite), media://datei (Medienpool), detail://seite/id und
        // route://quelle/id (Datensatz)
        return (string) preg_replace_callback('~<a\b([^>]*)>~i', static function (array $m): string {
            $attrs = $m[1];
            if (!preg_match('~\sdata-link="([^"]+)"~', $attrs, $l)) {
                return $m[0];
            }
            $url = self::linkUrl(html_entity_decode($l[1], ENT_QUOTES));
            if (null === $url) {
                return $m[0];
            }
            $attrs = preg_replace('~\shref="[^"]*"~', '', $attrs) ?? $attrs;

            return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '"' . $attrs . '>';
        }, $html);
    }

    /** Adresse zu einem gespeicherten Linkziel ('#' wenn es das Ziel nicht mehr gibt, null bei unbekanntem Format) */
    public static function linkUrl(string $ref): ?string
    {
        if (preg_match('~^redaxo://(\d+)(#[\w-]+)?$~', $ref, $r)) {
            $art = Article::get((int) $r[1]);

            return $art ? $art->getUrl() . ($r[2] ?? '') : '#';
        }
        if (preg_match('~^media://(.+)$~', $ref, $r)) {
            $media = Media::get(rawurldecode($r[1]));

            return null !== $media ? $media->getUrl() : '#';
        }
        if (preg_match('~^detail://(\d+)/(\d+)(#[\w-]+)?$~', $ref, $r)) {
            $lang = Language::getCurrentId();
            $cfg = Detail::config((int) $r[1], $lang);
            $title = null !== $cfg ? Detail::title($cfg, (int) $r[2]) : null;

            return null === $title ? '#' : Detail::url(Router::url((int) $r[1], $lang), $title, (int) $r[2]) . ($r[3] ?? '');
        }
        if (preg_match('~^route://([\w.-]+)/([^/#]+)(#[\w-]+)?$~', $ref, $r)) {
            $url = Router::urlFor($r[1], rawurldecode($r[2]));

            return ('' !== $url ? $url : '#') . ('' !== $url ? ($r[3] ?? '') : '');
        }

        return null;
    }

    private static function config(): HtmlSanitizerConfig
    {
        static $config = null;

        return $config ??= new HtmlSanitizerConfig()
            ->allowElement('p', ['class'])->allowElement('br')->allowElement('strong')->allowElement('b')->allowElement('em')->allowElement('i')
            ->allowElement('mark')->allowElement('code')->allowElement('sup')->allowElement('sub')->allowElement('span', ['class'])
            ->allowElement('h2', ['id'])->allowElement('h3', ['id'])->allowElement('h4', ['id'])
            ->allowElement('ul', ['class'])->allowElement('ol')->allowElement('li')->allowElement('blockquote')
            ->allowElement('a', ['href', 'title', 'target', 'rel', 'data-link', 'data-video-lb'])
            ->allowRelativeLinks()->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])->withMaxInputLength(-1);
    }
}
