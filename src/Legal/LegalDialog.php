<?php

namespace Klxm\Fluid\Legal;

use Klxm\Fluid\BlockModule;
use Klxm\Fluid\FluidAddon;
use Klxm\Routes\Attribute\AsEndpoint;
use Klxm\Routes\Router;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Language\Language;

use function array_key_exists;
use function function_exists;
use function html_entity_decode;
use function htmlspecialchars;
use function http_response_code;
use function is_array;
use function is_string;
use function json_encode;
use function nl2br;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function rawurlencode;
use function Redaxo\Core\View\escape;
use function str_contains;
use function strip_tags;
use function strtolower;
use function trim;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Legal texts in a dialog (optional, from KLXM Studio – Core\LegalDialog): the link „Datenschutzhinweise“ on forms
 * opens the privacy policy above the form instead of on a new page – input is kept.
 *
 * Enable in Project::configure(): `Legal::$config = new LegalConfig(dialog: true)`. Then
 * `GET /_legal/privacy?lang=ID` returns title and cleaned HTML of the privacy page (headings, paragraphs, lists, links –
 * without buttons, images, forms), and LegalDialog::attrs() marks links for assets/js/legal-dialog.js. Without
 * JavaScript, with Ctrl/⌘ or on errors it stays an ordinary link.
 *
 *     <a href="<?= escape(Legal::url('privacy')) ?>"<?= LegalDialog::attrs() ?>>Datenschutzhinweise</a>
 */
final class LegalDialog
{
    private static bool $assets = false;

    /** Attributes for a link to the privacy page – empty if the dialog is off; on the first call also the script */
    public static function attrs(string $type = 'privacy'): string
    {
        if (!Legal::config()->dialog || null === Legal::article($type)) {
            return '';
        }
        $addon = FluidAddon::instance();

        return ' data-legal-src="' . escape(Router::base() . '/_legal/' . $type . '?lang=' . rawurlencode((string) Language::getCurrentId())) . '"'
            . ' data-legal-css="' . escape($addon->getAssetsUrl('css/legal-dialog.css')) . '"';
    }

    /** Script of the dialog – once per page, only if the dialog is on */
    public static function script(): string
    {
        if (self::$assets || !Legal::config()->dialog) {
            return '';
        }
        self::$assets = true;

        return '<script type="module" src="' . escape(FluidAddon::instance()->getAssetsUrl('js/legal-dialog.js')) . '"></script>';
    }

    #[AsEndpoint('/_legal/*')]
    public static function serve(string $path, string $rest): void
    {
        $type = trim($rest, '/');
        $languageId = (int) ($_GET['lang'] ?? 0);
        if (!Legal::config()->dialog || !array_key_exists($type, Legal::KEYS) || !Language::exists($languageId)) {
            http_response_code(404);
            Router::respond('{"ok":false}', 'application/json');
        }
        Language::setCurrentId($languageId);
        $article = Legal::article($type);
        if (null === $article) {
            http_response_code(404);
            Router::respond('{"ok":false}', 'application/json');
        }
        $t = static fn (string $s): string => function_exists('lt') ? lt($s) : $s;
        Router::respond((string) json_encode([
            'ok' => true,
            'title' => $article->name,
            'html' => self::fragment($article),
            'url' => $article->getUrl(),
            'close' => $t('Schließen'),
            'open' => $t('Als Seite öffnen'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json; charset=utf-8', ['Cache-Control: public, max-age=300', 'X-Robots-Tag: noindex']);
    }

    /** Text of the published blocks: headings, intro, body text (cleaned), entries (e.g. FAQ) */
    public static function fragment(Article $article): string
    {
        $rows = Sql::factory()->getArray(
            'SELECT data FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? AND status = 1 ORDER BY content_section_id, priority',
            [$article->id, $article->languageId, ArticleRevision::LIVE],
        );
        $h = '';
        foreach ($rows as $row) {
            $d = BlockModule::stored((string) $row['data'])['values'];
            if ('' !== trim((string) ($d['title'] ?? ''))) {
                $h .= '<h2>' . self::plain((string) $d['title']) . '</h2>';
            }
            foreach (['intro', 'lead', 'text', 'body', 'content'] as $k) {
                if (is_string($d[$k] ?? null)) {
                    $h .= self::text($d[$k]);
                }
            }
            foreach ((array) ($d['items'] ?? []) as $it) {
                if (!is_array($it)) {
                    continue;
                }
                foreach (['title', 'question', 'q', 'label'] as $k) {
                    if ('' !== trim((string) ($it[$k] ?? ''))) {
                        $h .= '<h3>' . self::plain((string) $it[$k]) . '</h3>';
                        break;
                    }
                }
                foreach (['answer', 'a', 'text', 'body'] as $k) {
                    if (is_string($it[$k] ?? null)) {
                        $h .= self::text($it[$k]);
                    }
                }
            }
        }

        return $h;
    }

    private static function plain(string $s): string
    {
        return htmlspecialchars(trim((string) preg_replace('~\*([^*]+)\*~u', '$1', $s)));
    }

    /** Body text: HTML reduced to a few tags without attributes (except href of links), plain text as a paragraph */
    private static function text(string $v): string
    {
        $v = trim($v);
        if ('' === $v) {
            return '';
        }
        if (!str_contains($v, '<')) {
            return '<p>' . nl2br(htmlspecialchars($v), false) . '</p>';
        }
        $v = strip_tags($v, '<h2><h3><h4><p><br><ul><ol><li><a><strong><b><em><i>');
        // Remove attributes – only safe href on links remain
        $v = (string) preg_replace_callback('~<(\w+)(\s[^>]*)?>~', static function (array $m): string {
            if ('a' === strtolower($m[1]) && preg_match('~\shref\s*=\s*"((?:https?:|mailto:|tel:|/|#)[^"]*)"~i', $m[2] ?? '', $h)) {
                return '<a href="' . htmlspecialchars(html_entity_decode($h[1])) . '">';
            }

            return '<' . $m[1] . '>';
        }, $v);

        return $v;
    }
}
