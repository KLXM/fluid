<?php

namespace Klxm\Fluid;

use Klxm\Routes\Attribute\AsEndpoint;
use Klxm\Routes\Router;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\User;
use Redaxo\Core\Translation\I18n;
use Throwable;

use function count;
use function Redaxo\Core\View\escape;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Presence of the editors (Presence) – fluid integration: articles per language (type „article“, id = „{language}:{article}“).
 * Endpoint /fluid-presence, responses following KLXM Studio's schema (the same script assets/editor/presence.js):
 *
 *   POST {type, id, label?, url?, leave?, peek?}   → {on, me, here: [Person…], last: ?Stand, live: Abo}
 *   GET                                            → {on, me, items: [{type, id, label, url, …Person}], live: Abo}
 *   Person = {user, name, initials, avatar, since, at, self}    Stand = {user, name, at, self} (last saved, from the activity)
 *
 * Only for logged-in backend users with permission for the article; visitors never see anything, the article cache
 * stays untouched. Can be switched off in the addon's configuration (presence = false).
 */
final class PresenceApi
{
    public const string PATH = 'fluid-presence';
    public const string SITE = 'default';
    private const array SAVES = ['save', 'publish', 'restore', 'discard', 'block_add', 'block_delete', 'page_settings'];

    /** @internal Endpoint of the presence */
    #[AsEndpoint('/' . self::PATH, early: true)]
    public static function serve(): void
    {
        self::handle();
    }

    public static function on(): bool
    {
        return (bool) FluidAddon::instance()->getConfig('presence', true);
    }

    public static function endpoint(): string
    {
        return Router::base() . '/' . self::PATH;
    }

    /** Texts for presence.js (formal „Sie“, like the toolbar) */
    public static function texts(): array
    {
        return ['editing1' => I18n::rawMsg('fluid_presence_editing1'), 'editingN' => I18n::rawMsg('fluid_presence_editing_n'), 'last' => I18n::rawMsg('fluid_presence_last'),
            'now' => I18n::rawMsg('fluid_presence_now'), 'min' => I18n::rawMsg('fluid_presence_min'), 'hour' => I18n::rawMsg('fluid_presence_hour'), 'day' => I18n::rawMsg('fluid_presence_day'),
            'conflict' => I18n::rawMsg('fluid_presence_conflict'), 'conflictShort' => I18n::rawMsg('fluid_presence_conflict_short'), 'conflictTitle' => I18n::rawMsg('fluid_presence_conflict_title'),
            'conflictBody' => I18n::rawMsg('fluid_presence_conflict_body'),
            'conflictOk' => I18n::rawMsg('fluid_presence_conflict_ok'), 'cancel' => I18n::rawMsg('fluid_presence_cancel'),
            // edit lock (EditLock)
            'locked' => I18n::rawMsg('fluid_presence_locked'),
            'lockedNotePage' => I18n::rawMsg('fluid_presence_locked_note_page'),
            'take' => I18n::rawMsg('fluid_presence_take'), 'takeAsk' => I18n::rawMsg('fluid_presence_take_ask'),
            'takeBody' => I18n::rawMsg('fluid_presence_take_body'),
            'taken' => I18n::rawMsg('fluid_presence_taken'),
            'takenBody' => I18n::rawMsg('fluid_presence_taken_body'),
            'freeNote' => I18n::rawMsg('fluid_presence_free_note'), 'editNow' => I18n::rawMsg('fluid_presence_edit_now'), 'ok' => I18n::rawMsg('fluid_presence_ok')];
    }

    private static function common(): string
    {
        return ' data-presence-endpoint="' . escape(self::endpoint()) . '" data-presence-live="' . escape(Live::endpoint()) . '"'
            . ' data-presence-texts="' . escape((string) json_encode(self::texts(), JSON_UNESCAPED_UNICODE)) . '"';
    }

    /**
     * Data for the toolbar: attributes for [data-presence-here] and for overviews (page tree, „Gerade aktiv“).
     *
     * @return array{here: string, list: string, board: string, js: string}|null
     */
    public static function toolbar(Article $article, bool $editing): ?array
    {
        if (!self::on()) {
            return null;
        }
        $a = FluidAddon::instance();
        $file = $a->getPath('assets/editor/presence.js');

        return [
            'here' => ' data-presence-here data-presence-type="article" data-presence-id="' . $article->languageId . ':' . $article->id . '"'
                . ' data-presence-label="' . escape($article->name) . '" data-presence-url="' . escape(Url::article($article->id, $article->languageId, [Editing::PARAM => 1])) . '"'
                . ' data-presence-csrf="' . escape(CsrfToken::factory(Api::CSRF)->getValue()) . '" data-presence-editing="' . ($editing ? '1' : '0') . '"'
                . (null !== EditLock::blockedFor($article) ? ' data-presence-locked="1"' : '') . self::common(),   // Editing lock: view only
            'list' => ' data-presence-list data-presence-type="article" data-presence-prefix="' . $article->languageId . ':"' . self::common(),
            'board' => ' data-presence-board' . self::common(),
            'js' => $a->getAssetsUrl('editor/presence.js') . '?v=' . (is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0'),
        ];
    }

    /** /fluid-presence (router, after PACKAGES_INCLUDED) */
    public static function handle(): never
    {
        $user = Editing::user();
        if (!$user || Editing::isGuest()) {   // Guest access (e.g. addon fluid_demo): guests don't appear in the presence
            self::send(['on' => false, 'error' => I18n::rawMsg('fluid_presence_error_perm')], 403);
        }
        if (!self::on()) {
            self::send(['on' => false, 'items' => []]);
        }
        $me = (string) $user->id;
        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
            $items = array_map(static fn ($e) => ['type' => (string) $e['type'], 'id' => (string) $e['id'], 'label' => (string) ($e['label'] ?? ''), 'url' => (string) ($e['url'] ?? '')] + self::out($e, $me),
                array_values(array_filter(Presence::list(['site' => self::SITE]), static fn ($e) => null !== self::article((string) $e['type'], (string) $e['id'], $user))));
            self::send(['on' => true, 'me' => $me, 'items' => $items, 'live' => Live::token(['presence:' . self::SITE])]);
        }
        // JSON (fetch) or form data (sendBeacon on leaving)
        $in = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'json') ? (array) json_decode((string) file_get_contents('php://input'), true) : $_POST;
        $csrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $in['_csrf'] ?? '');
        $_REQUEST[CsrfToken::PARAM] = $csrf;   // The token is masked on every output – checked via the core
        if ('' === $csrf || !CsrfToken::factory(Api::CSRF)->isValid()) {
            self::send(['on' => true, 'error' => I18n::rawMsg('fluid_presence_error_session')], 419);
        }
        $type = (string) ($in['type'] ?? '');
        $id = (string) ($in['id'] ?? '');
        $article = self::article($type, $id, $user);
        if (!$article) {
            self::send(['on' => true, 'error' => I18n::rawMsg('fluid_presence_error_unknown')], 404);
        }
        $who = ['site' => self::SITE, 'type' => $type, 'id' => $id, 'user' => $me];
        if (!empty($in['leave'])) {
            Presence::leave($who);
            self::send(['on' => true, 'left' => true]);
        }
        // Take over the editing lock (administration): report and move ahead of the previous holder
        $take = !empty($in['take']) && EditLock::on() && EditLock::canTake($user);
        // peek: just look (view mode) – without reporting oneself as „bearbeitet gerade“
        if (empty($in['peek']) || $take) {
            Presence::beat($who, self::person($user) + ['label' => mb_substr((string) ($in['label'] ?? $article->name), 0, 120), 'url' => self::localUrl((string) ($in['url'] ?? ''))]);
        }
        if ($take) {
            EditLock::take($article, $user->id);
        }
        $here = array_map(static fn ($e) => self::out($e, $me), Presence::list(['site' => self::SITE, 'type' => $type, 'id' => $id]));
        self::send(['on' => true, 'me' => $me, 'here' => $here, 'last' => self::last($article, $user), 'lock' => self::lock($article, $user),
            'live' => Live::token(['presence:' . self::SITE])]);
    }

    /**
     * State of the editing lock for the toolbar: {on, by: other holder|null, mine, canTake}.
     *
     * @return array<string, mixed>
     */
    private static function lock(Article $article, User $user): array
    {
        if (!EditLock::on()) {
            return ['on' => false];
        }
        $holder = EditLock::holder($article);
        $mine = null !== $holder && (string) $holder['user'] === (string) $user->id;

        return ['on' => true, 'by' => null !== $holder && !$mine ? self::out($holder, (string) $user->id) : null, 'mine' => $mine, 'canTake' => EditLock::canTake($user)];
    }

    /** Article for type/id if the person may edit it – otherwise null */
    private static function article(string $type, string $id, User $user): ?Article
    {
        if ('article' !== $type || !preg_match('~^(\d+):(\d+)$~', $id, $m)) {
            return null;
        }
        $article = Article::get((int) $m[2], (int) $m[1]);
        $perm = $user->getComplexPerm('structure');

        return $article && $perm instanceof StructurePermission && $perm->hasCategoryPerm($article->categoryId) ? $article : null;
    }

    /** only paths of the same website (link from overviews) */
    private static function localUrl(string $url): string
    {
        return preg_match('~^/(?!/)[^\s"<>]*$~', $url) ? mb_substr($url, 0, 300) : '';
    }

    private static function name(User $u): string
    {
        return (string) ($u->name ?: $u->login);
    }

    private static function person(User $u): array
    {
        preg_match_all('~\pL[\pL\pN]*~u', preg_replace('~\(.*?\)~u', '', self::name($u)) ?? '', $m);   // words without parenthetical suffix
        $parts = $m[0] ?: ['?'];
        $ini = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : ''));

        return ['name' => self::name($u), 'initials' => $ini, 'avatar' => ''];
    }

    private static function out(array $e, string $me): array
    {
        return ['user' => (string) $e['user'], 'name' => (string) ($e['name'] ?? ''), 'initials' => (string) ($e['initials'] ?? ''), 'avatar' => (string) ($e['avatar'] ?? ''),
            'since' => (int) $e['since'], 'at' => (int) $e['at'], 'self' => (string) $e['user'] === $me];
    }

    /** Last saved (activity): name and time – the activity knows names, not user IDs */
    private static function last(Article $article, User $user): ?array
    {
        try {
            $row = Sql::factory()->getArray('SELECT user, created FROM ' . Activity::TABLE . ' WHERE article_id = ? AND action IN (' . implode(',', array_fill(0, count(self::SAVES), '?')) . ') ORDER BY id DESC LIMIT 1',
                [$article->id, ...self::SAVES])[0] ?? null;
        } catch (Throwable) {
            return null;
        }
        if (!$row) {
            return null;
        }

        return ['user' => (string) $row['user'], 'name' => (string) $row['user'], 'at' => (int) strtotime((string) $row['created']), 'self' => (string) $row['user'] === self::name($user)];
    }

    private static function send(array $data, int $status = 200): never
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
