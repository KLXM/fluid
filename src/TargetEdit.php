<?php

namespace Klxm\Fluid;

use Klxm\Fluid\Data\Detail;
use Klxm\Fluid\Data\YFormBridge;
use Klxm\Fluid\Routing\Router;
use Klxm\Fluid\Routing\Routes;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Language\Language;
use Throwable;

use function array_key_exists;
use function parse_url;
use function preg_match;
use function preg_replace;
use function rawurldecode;
use function Redaxo\Core\View\escape;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

use const PHP_URL_PATH;

/**
 * „Ziel bearbeiten“: small action on cards and tiles pointing to another page or a record (from KLXM Studio).
 * Only in edit mode and only with permission – visitors get nothing (no query, no markup).
 *
 *     <?= $b->targetEdit($it['link'], $it['title']) ?>   as the card's first child
 *
 * References as in the field „Link“: `redaxo://12` (also `#anchor`) or a URL of this website („/leistungen/marke“,
 * „https://eigene-domain/…“, detail pages of a data list „/termine/titel-5“). External URLs, mailto:, tel:, anchors
 * on the page and special values („phone“, „email“) yield nothing.
 *
 *  - Page (permission for its category): opens it in edit mode.
 *  - Record (edit permission for the YForm table): opens YForm's form in the overlay; the page stays open and
 *    re-renders the block after saving. With Ctrl/⌘ or without script: the form in the backend.
 *
 * @internal
 */
final class TargetEdit
{
    /** @var array<string, array{kind: string, title: string, href: string}|null> */
    private static array $memo = [];

    /** The action as HTML – empty for visitors, without permission and without internal target */
    public static function html(?string $ref, ?string $label = null): string
    {
        if (null === $ref || '' === trim($ref) || !Editing::active() || Editing::isGuest()) {
            return '';
        }
        $target = self::target($ref);
        if (null === $target) {
            return '';
        }
        $title = trim(str_replace('*', '', (string) $label)) ?: $target['title'];
        $page = 'page' === $target['kind'];
        $aria = ($page ? 'Seite „' : 'Datensatz „') . $title . '“ bearbeiten';

        return '<a class="cms-target-edit' . ($page ? '' : ' fdl-edit') . '" href="' . escape($target['href']) . '" data-cms-target="' . $target['kind'] . '"'
            . ($page ? '' : ' target="_blank" rel="noopener"')
            . ' aria-label="' . escape($aria) . '" title="' . escape($aria) . '" contenteditable="false">'
            . ($page
                ? '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><rect x="1.8" y="2.6" width="12.4" height="10.8" rx="1.8" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M1.8 5.8h12.4" stroke="currentColor" stroke-width="1.4"/></svg>'
                : '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><ellipse cx="8" cy="3.6" rx="5.2" ry="2" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M2.8 3.6v8.8c0 1.1 2.3 2 5.2 2s5.2-.9 5.2-2V3.6M2.8 8c0 1.1 2.3 2 5.2 2s5.2-.9 5.2-2" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>')
            . '<span>' . ($page ? 'Seite' : 'Datensatz') . '</span></a>';
    }

    /**
     * Target including permission check – null without permission or without internal target.
     *
     * @return array{kind: 'page'|'entry', title: string, href: string}|null
     */
    public static function target(string $ref): ?array
    {
        $ref = trim($ref);
        if (array_key_exists($ref, self::$memo)) {
            return self::$memo[$ref];
        }
        try {
            return self::$memo[$ref] = self::lookup($ref);
        } catch (Throwable) {
            return self::$memo[$ref] = null;
        }
    }

    /** @return array{kind: 'page'|'entry', title: string, href: string}|null */
    private static function lookup(string $ref): ?array
    {
        $user = Editing::user();
        if (null === $user || '' === $ref || '#' === $ref[0]) {
            return null;
        }
        if (preg_match('~^(?:redaxo://|page:)?(\d+)(?:#[\w-]+)?$~', $ref, $m)) {
            return self::page(Article::get((int) $m[1]));
        }
        $path = self::localPath($ref);
        if (null === $path) {
            return null;
        }
        [$path, $languageId] = self::language($path);
        if ('' === $path) {
            return self::page(Article::get(Article::getSiteStartArticleId(), $languageId));
        }
        $path = Router::config()->format->strip($path);
        $id = Routes::find($path, $languageId);
        if (null !== $id) {
            return self::page(Article::get($id, $languageId));
        }
        // Detailseite einer Datenliste: /{seite}/{titel}-{id}
        $match = Detail::match($path, $languageId);
        if (null === $match) {
            return null;
        }
        [$pageId, $entryId] = $match;
        $config = Detail::config($pageId, $languageId);
        $table = null !== $config ? YFormBridge::table((string) $config['table']) : null;
        $href = null !== $table ? YFormBridge::editUrl($table, $entryId) : null;   // checks the edit permission
        if (null === $href) {
            return null;
        }

        return ['kind' => 'entry', 'title' => Detail::title($config, $entryId) ?? '#' . $entryId, 'href' => $href];
    }

    /** @return array{kind: 'page', title: string, href: string}|null */
    private static function page(?Article $article): ?array
    {
        $user = Editing::user();
        if (null === $article || null === $user) {
            return null;
        }
        $perm = $user->getComplexPerm('structure');
        if (!$user->admin && !($perm instanceof StructurePermission && $perm->hasCategoryPerm($article->categoryId))) {
            return null;
        }
        $url = $article->getUrl();

        return ['kind' => 'page', 'title' => $article->name, 'href' => $url . (str_contains($url, '?') ? '&' : '?') . Editing::PARAM . '=1'];
    }

    /** Path of a URL of this website (without base, query, anchor) – null for external URLs and special values */
    private static function localPath(string $href): ?string
    {
        if (preg_match('~^(mailto|tel|sms|javascript|data):~i', $href)) {
            return null;
        }
        $u = parse_url($href);
        if (false === $u) {
            return null;
        }
        if (isset($u['host'])) {
            $own = strtolower((string) preg_replace('~:\d+$~', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
            if ('' === $own || strtolower($u['host']) !== $own) {
                return null;
            }
        } elseif (!isset($u['path']) || !str_starts_with($u['path'], '/') || str_starts_with($href, '//')) {
            return null;   // relative Angaben, Sonderwerte des Kits
        }
        $path = (string) ($u['path'] ?? '/');
        $base = (string) parse_url(Router::base(), PHP_URL_PATH);
        if ('' !== $base && '/' !== $base && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }

        return trim(rawurldecode($path), '/');
    }

    /**
     * Splits off the language prefix („en/leistungen“ → [„leistungen“, ID of en]); without prefix the current language.
     *
     * @return array{string, int}
     */
    private static function language(string $path): array
    {
        foreach (Language::getAll() as $language) {
            $prefix = trim(Routes::prefix($language->id), '/');
            if ('' !== $prefix && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
                return [trim(substr($path, strlen($prefix)), '/'), $language->id];
            }
        }

        return [$path, Language::getCurrentId()];
    }
}
