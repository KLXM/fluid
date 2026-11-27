<?php

namespace Klxm\Fluid;

use DateTimeImmutable;
use IntlDateFormatter;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Content\ArticleSliceReference;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\Filesystem\Url;

use function array_key_exists;
use function count;
use function in_array;

/**
 * Versions of a page (model from KLXM Studio – Pages::addRevision), stored in the core history table
 * (rex_article_slice_history, column revision):
 *
 *  - Live version = archive: every publish stores the state that goes live („Veröffentlicht“). If the previous
 *    live version was not yet in the archive (e.g. changed directly in the backend), it is saved first.
 *  - Working version = intermediate states for undo (save, before restore/discard). They were never
 *    published and are cleared on publish.
 *  - Restore writes a state into the draft – it only goes live with „Veröffentlichen“, corrections before that
 *    are possible. The current draft is saved first (nothing is lost).
 *
 * The last KEEP states are kept per page and version.
 */
final class Versions
{
    public const int KEEP = 40;

    public const array TYPES = ['fluid_save' => 'Gespeichert', 'fluid_published' => 'Veröffentlicht', 'fluid_publish' => 'Vor dem Veröffentlichen',
        'fluid_restore' => 'Vor dem Wiederherstellen', 'fluid_discard' => 'Vor dem Verwerfen'];

    // ------------------------------------------------------------------ versions view (like KLXM Studio: timeline, preview, comparison)

    /** Preview of a state on the website: ?fluid_version=now|live|<revision>-<YmdHis> (&fluid_mark=1 highlights changes) */
    public const string PARAM = 'fluid_version';
    /** Saves within this many seconds are merged into one state (typing, several blocks) */
    private const int MERGE = 120;

    /** Columns that make up the content of a block (for comparing states and checking them for equality) */
    private const array META = ['id', 'slice_id', 'article_id', 'language_id', 'revision', 'priority', 'createdate', 'updatedate', 'createuser', 'updateuser', 'history_type', 'history_date', 'history_user'];

    /** @var array{revision: int, date: ?string}|null state shown by the current request */
    public static ?array $preview = null;

    /** @var array<int, string> slice ID → new|changed (highlighting in the preview) */
    public static array $marks = [];

    /**
     * Publish: make the working version live ($copy), archive the state that goes live – and clear the intermediate
     * states of the working version. The previous live version is saved first if it is not yet in the archive.
     *
     * @param callable(): void $copy copies the working version into the live version
     */
    public static function publish(int $articleId, int $languageId, callable $copy): void
    {
        if (!self::archived($articleId, $languageId)) {
            // one second earlier: the history tells states apart only by their timestamp
            self::snapshot($articleId, $languageId, ArticleRevision::LIVE, 'fluid_publish', date(Sql::FORMAT_DATETIME, time() - 1));
        }
        $copy();
        // one state per publish – unless it went live unchanged (then it is already in the archive)
        if (!self::archived($articleId, $languageId)) {
            self::snapshot($articleId, $languageId, ArticleRevision::LIVE, 'fluid_published');
        }
        ArticleSliceHistory::checkTables();
        Sql::factory()->setQuery('DELETE FROM ' . ArticleSliceHistory::getTable() . ' WHERE article_id = ? AND language_id = ? AND revision = ?',
            [$articleId, $languageId, ArticleRevision::WORK]);
    }

    /** Is the current live version already the last state in the archive? (same blocks, order, status, values) */
    private static function archived(int $articleId, int $languageId): bool
    {
        ArticleSliceHistory::checkTables();
        $sql = Sql::factory();
        $table = ArticleSliceHistory::getTable();
        $last = $sql->getArray('SELECT MAX(history_date) AS d FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ?',
            [$articleId, $languageId, ArticleRevision::LIVE])[0]['d'] ?? null;
        if (null === $last) {
            return false;
        }
        $live = $sql->getArray('SELECT module, content_section_id, priority, status, data FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY content_section_id, priority',
            [$articleId, $languageId, ArticleRevision::LIVE]);
        $archive = $sql->getArray('SELECT module, content_section_id, priority, status, data FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? AND history_date = ? AND slice_id > 0 ORDER BY content_section_id, priority',
            [$articleId, $languageId, ArticleRevision::LIVE, $last]);
        $norm = static fn (array $rows): string => md5((string) json_encode(array_map(static fn (array $r): array => [(string) $r['module'], (int) $r['content_section_id'], (int) $r['priority'], (int) $r['status'], BlockModule::decode(null !== $r['data'] ? (string) $r['data'] : null)], $rows)));

        return $norm($live) === $norm($archive);
    }

    /** @param string|null $date time of the state (default: now) */
    public static function snapshot(int $articleId, int $languageId, int $revision, string $type, ?string $date = null): void
    {
        ArticleSliceHistory::checkTables();
        $sql = Sql::factory();
        $table = ArticleSliceHistory::getTable();
        $user = Core::getUser()->login ?? Editing::user()->login ?? '';
        // saves in quick succession: replace the own last state instead of creating a new one
        if ('fluid_save' === $type) {
            $last = $sql->getArray('SELECT history_date, history_user, history_type FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY history_date DESC LIMIT 1', [$articleId, $languageId, $revision])[0] ?? null;
            if ($last && 'fluid_save' === $last['history_type'] && $last['history_user'] === $user && time() - strtotime((string) $last['history_date']) < self::MERGE) {
                $sql->setQuery('DELETE FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? AND history_date = ?', [$articleId, $languageId, $revision, $last['history_date']]);
            }
        }
        // the history tells states apart only by their timestamp (second): taken → next free second
        $time = null !== $date ? (int) strtotime($date) : time();
        while ($sql->getArray('SELECT 1 FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? AND history_date = ? LIMIT 1',
            [$articleId, $languageId, $revision, date(Sql::FORMAT_DATETIME, $time)])) {
            ++$time;
        }
        $date = date(Sql::FORMAT_DATETIME, $time);
        $rows = $sql->getArray('SELECT * FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ?', [$articleId, $languageId, $revision]);
        $cols = array_map(static fn ($c) => $c->name, Table::get($table)->getColumns());
        foreach ($rows ?: [['__empty' => true]] as $row) {
            $ins = Sql::factory()->setTable($table);
            if (isset($row['__empty'])) {
                // empty page: marker row so that the state is visible and restorable
                $ins->setValues(array_intersect_key(['article_id' => $articleId, 'language_id' => $languageId, 'revision' => $revision, 'slice_id' => 0, 'module' => '', 'content_section_id' => 1, 'priority' => 0, 'status' => 0,
                    'createdate' => $date, 'updatedate' => $date, 'createuser' => $user, 'updateuser' => $user], array_flip($cols)));
            } else {
                foreach ($row as $k => $v) {
                    if ('id' === $k) {
                        $ins->setValue('slice_id', $v);
                    } elseif (in_array($k, $cols, true)) {
                        $ins->setValue($k, $v);
                    }
                }
            }
            $ins->setValue('history_type', $type)->setValue('history_date', $date)->setValue('history_user', $user)->insert();
        }
        // clean up: only the last KEEP states per page/version
        $dates = array_column($sql->getArray('SELECT DISTINCT history_date FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY history_date DESC', [$articleId, $languageId, $revision]), 'history_date');
        if (count($dates) > self::KEEP) {
            $sql->setQuery('DELETE FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? AND history_date < ?', [$articleId, $languageId, $revision, $dates[self::KEEP - 1]]);
        }
    }

    /** @return list<array{date: string, type: string, label: string, user: string, revision: int, blocks: int}> */
    public static function list(int $articleId, int $languageId): array
    {
        ArticleSliceHistory::checkTables();
        $rows = Sql::factory()->getArray(
            'SELECT history_date, history_type, history_user, revision, SUM(slice_id > 0) AS blocks FROM ' . ArticleSliceHistory::getTable()
            . ' WHERE article_id = ? AND language_id = ? GROUP BY history_date, history_type, history_user, revision ORDER BY history_date DESC LIMIT 60',
            [$articleId, $languageId],
        );

        return array_map(static fn (array $r) => [
            'date' => (string) $r['history_date'], 'type' => (string) $r['history_type'],
            'label' => self::TYPES[$r['history_type']] ?? ('Live: ' . str_replace('_', ' ', (string) $r['history_type'])),
            'user' => (string) $r['history_user'], 'revision' => (int) $r['revision'], 'blocks' => (int) $r['blocks'],
        ], $rows);
    }

    /** Bring a state back into the draft (the current draft is saved first) */
    public static function restore(int $articleId, int $languageId, string $date, int $fromRevision): void
    {
        $table = ArticleSliceHistory::getTable();
        $sql = Sql::factory();
        $rows = $sql->getArray('SELECT * FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? AND history_date = ?', [$articleId, $languageId, $fromRevision, $date]);
        if (!$rows) {
            throw new ApiError('Diesen Stand gibt es nicht mehr.');
        }
        $target = FluidAddon::revision();
        self::snapshot($articleId, $languageId, $target, 'fluid_restore');
        $sql->setQuery('DELETE FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ?', [$articleId, $languageId, $target]);
        $cols = array_map(static fn ($c) => $c->name, Table::get('rex_article_slice')->getColumns());
        foreach ($rows as $row) {
            if (0 === (int) $row['slice_id']) {
                continue;   // marker „empty page“
            }
            $ins = Sql::factory()->setTable('rex_article_slice');
            foreach ($cols as $c) {
                if ('id' !== $c && array_key_exists($c, $row)) {
                    $ins->setValue($c, $row[$c]);
                }
            }
            $ins->setValue('revision', $target)->insert();
            if ($slice = ArticleSlice::getArticleSliceById((int) $ins->getLastId(), $languageId, $target)) {
                ArticleSliceReference::update($slice);   // core references (delete protection)
            }
        }
        ArticleCache::delete($articleId, $languageId);
    }

    /** @return array{revision: int, date: ?string}|null */
    public static function parseKey(string $key): ?array
    {
        return match (true) {
            'now' === $key => ['revision' => FluidAddon::revision(), 'date' => null],
            'live' === $key => ['revision' => ArticleRevision::LIVE, 'date' => null],
            1 === preg_match('/^(\d)-(\d{14})$/', $key, $m) && false !== $d = DateTimeImmutable::createFromFormat('YmdHis', $m[2]) => ['revision' => (int) $m[1], 'date' => $d->format(Sql::FORMAT_DATETIME)],
            default => null,
        };
    }

    private static function key(int $revision, string $date): string
    {
        return $revision . '-' . date('YmdHis', (int) strtotime($date));
    }

    /**
     * States of a page, newest first. The first entry is „Jetzt“ (draft or live); older states with the same content
     * as a newer one are dropped.
     *
     * @return list<array{key: string, now: bool, revision: int, date: string, type: string, user: string, blocks: array<int, string>, modules: array<int, string>}>
     */
    public static function states(int $articleId, int $languageId): array
    {
        ArticleSliceHistory::checkTables();
        $sql = Sql::factory();
        $table = ArticleSliceHistory::getTable();
        $sig = static function (array $rows): array {
            $blocks = [];
            $modules = [];
            foreach ($rows as $row) {
                $id = (int) ($row['slice_id'] ?? $row['id']);
                if ($id <= 0) {
                    continue;
                }
                $blocks[$id] = md5((string) json_encode(array_diff_key($row, array_flip(self::META))));
                $modules[$id] = (string) $row['module'];
            }

            return [$blocks, $modules];
        };

        $revision = FluidAddon::revision();
        $rows = $sql->getArray('SELECT * FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY content_section_id, priority', [$articleId, $languageId, $revision]);
        [$blocks, $modules] = $sig($rows);
        $changed = array_filter(array_column($rows, 'updatedate'));
        // restore copies old rows (old updatedate) – „Jetzt“ is at least as new as the newest saved state
        $changed[] = (string) ($sql->getArray('SELECT MAX(history_date) AS d FROM ' . $table . ' WHERE article_id = ? AND language_id = ?', [$articleId, $languageId])[0]['d'] ?? '');
        $changed = array_filter($changed);
        $out = [['key' => 'now', 'now' => true, 'revision' => $revision, 'date' => $changed ? (string) max($changed) : date(Sql::FORMAT_DATETIME), 'type' => 'now', 'user' => '', 'blocks' => $blocks, 'modules' => $modules]];
        // equal = same blocks in the same order (IDs don't count: the live version has its own slice IDs)
        $seen = [md5((string) json_encode(array_values($blocks))) => true];

        $groups = $sql->getArray('SELECT history_date, revision, MAX(history_type) AS history_type, MAX(history_user) AS history_user FROM ' . $table
            . ' WHERE article_id = ? AND language_id = ? GROUP BY history_date, revision ORDER BY history_date DESC LIMIT 60', [$articleId, $languageId]);
        foreach ($groups as $g) {
            $rows = $sql->getArray('SELECT * FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? AND history_date = ? ORDER BY content_section_id, priority',
                [$articleId, $languageId, (int) $g['revision'], $g['history_date']]);
            [$blocks, $modules] = $sig($rows);
            $hash = md5((string) json_encode(array_values($blocks)));
            if (isset($seen[$hash])) {
                continue;   // same content as a newer state
            }
            $seen[$hash] = true;
            $out[] = ['key' => self::key((int) $g['revision'], (string) $g['history_date']), 'now' => false, 'revision' => (int) $g['revision'], 'date' => (string) $g['history_date'],
                'type' => (string) $g['history_type'], 'user' => (string) $g['history_user'], 'blocks' => $blocks, 'modules' => $modules];
        }

        return $out;
    }

    /**
     * Difference between two states per block: new, changed, removed (labels of the removed blocks).
     * Matched first by slice ID, then by equal content, then by block type in order – publish and
     * restore create the blocks with new IDs.
     *
     * @param array<int, string> $blocks
     * @param array<int, string> $modules
     * @param array<int, string>|null $base
     * @param array<int, string> $baseModules
     * @return array{marks: array<int, string>, new: int, changed: int, removed: list<string>}
     */
    public static function diff(array $blocks, array $modules, ?array $base, array $baseModules = []): array
    {
        $out = ['marks' => [], 'new' => 0, 'changed' => 0, 'removed' => []];
        if (null === $base) {
            return $out;
        }
        $open = $base;            // blocks of the older state not yet matched
        $pending = [];            // blocks without a partner by ID
        foreach ($blocks as $id => $hash) {
            if (isset($open[$id])) {
                if ($open[$id] !== $hash) {
                    $out['marks'][$id] = 'changed';
                }
                unset($open[$id]);
            } else {
                $pending[$id] = $hash;
            }
        }
        foreach ($pending as $id => $hash) {
            if (false !== $match = array_search($hash, $open, true)) {
                unset($open[$match], $pending[$id]);   // same content, only a new ID
            }
        }
        foreach ($pending as $id => $hash) {
            foreach ($open as $oid => $ohash) {
                if (($baseModules[$oid] ?? '') === ($modules[$id] ?? '')) {
                    $out['marks'][$id] = 'changed';
                    unset($open[$oid], $pending[$id]);
                    break;
                }
            }
        }
        foreach ($pending as $id => $hash) {
            $out['marks'][$id] = 'new';
        }
        foreach (array_keys($open) as $oid) {
            $out['removed'][] = self::moduleLabel($baseModules[$oid] ?? '');
        }
        $counts = array_count_values($out['marks']);
        $out['new'] = $counts['new'] ?? 0;
        $out['changed'] = $counts['changed'] ?? 0;

        return $out;
    }

    private static function moduleLabel(string $key): string
    {
        $m = Module::getClass($key);
        if (null === $m) {
            return $key;
        }

        return is_a($m, BlockModule::class, true) ? $m::label() : $m::name();
    }

    /** Data for the versions view in the editor (assets/editor/versions.js) */
    public static function view(Article $article): array
    {
        $states = self::states($article->id, $article->languageId);
        $url = static fn (string $key, bool $mark = false): string => Url::article($article->id, $article->languageId, [self::PARAM => $key] + ($mark ? ['fluid_mark' => 1] : []));
        $now = time();
        $versions = [];
        foreach ($states as $i => $st) {
            $older = $states[$i + 1] ?? null;
            $d = self::diff($st['blocks'], $st['modules'], $older['blocks'] ?? null, $older['modules'] ?? []);
            $versions[] = [
                'id' => $st['key'], 'now' => $st['now'],
                // version: intermediate state of the draft (cleared on publish) or published state (archive)
                'stage' => $st['now'] ? null : (ArticleRevision::WORK === (int) $st['revision'] ? 'draft' : 'live'),
                ...self::when($st['date'], $now),
                'note' => $st['now'] ? (self::isWorkVersion() && self::hasChanges($article) ? 'Entwurf mit unveröffentlichten Änderungen' : 'Aktueller Stand') : (self::TYPES[$st['type']] ?? 'Gespeichert'),
                'user' => $st['user'],
                'preview' => $url($st['key']), 'preview_mark' => $url($st['key'], true),
                'diff' => ['new' => $d['new'], 'changed' => $d['changed'], 'removed' => $d['removed'], 'first' => null === $older],
            ];
        }
        $hasLive = (bool) Sql::factory()->getArray('SELECT id FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? LIMIT 1', [$article->id, $article->languageId, ArticleRevision::LIVE]);

        return [
            'kind' => 'page', 'title' => $article->name, 'versions' => $versions,
            'live' => $url(self::isWorkVersion() ? 'live' : 'now'), 'has_live' => $hasLive,
            'today' => self::when(date(Sql::FORMAT_DATETIME), $now)['day'],
            'can_restore' => self::isWorkVersion(),
        ];
    }

    private static function hasChanges(Article $article): bool
    {
        $sig = static fn (int $rev): string => md5((string) json_encode(array_map(
            static fn (array $r) => array_diff_key($r, array_flip(self::META)),
            Sql::factory()->getArray('SELECT * FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY content_section_id, priority', [$article->id, $article->languageId, $rev]),
        )));

        return $sig(ArticleRevision::WORK) !== $sig(ArticleRevision::LIVE);
    }

    /** Time values of a state: day (timeline group), time, ago, at, ts, hm (clock), dm (calendar sheet) */
    private static function when(string $date, int $now): array
    {
        $ts = strtotime($date) ?: $now;
        $fmt = static fn (string $pattern): string => class_exists(IntlDateFormatter::class)
            ? (string) new IntlDateFormatter('de_DE', IntlDateFormatter::NONE, IntlDateFormatter::NONE, date_default_timezone_get(), null, $pattern)->format($ts)
            : date('d.m.Y', $ts);
        $diff = $now - $ts;
        $ago = match (true) {
            $diff < 60 => 'gerade eben',
            $diff < 3600 => 'vor ' . intdiv($diff, 60) . ' Min.',
            $diff < 86400 => 'vor ' . intdiv($diff, 3600) . ' Std.',
            $diff < 172800 => 'gestern',
            default => 'vor ' . intdiv($diff, 86400) . ' Tagen',
        };

        return ['label' => $ago, 'day' => $fmt('d. MMMM y'), 'time' => date('H:i', $ts), 'ago' => $ago, 'at' => $fmt('d. MMMM y') . ', ' . date('H:i', $ts) . ' Uhr',
            'ts' => $ts, 'hm' => [(int) date('G', $ts), (int) date('i', $ts)], 'dm' => [(int) date('j', $ts), rtrim($fmt('MMM'), '.')]];
    }

    /**
     * Query of the blocks for the preview of an older state: the rows from the history instead of rex_article_slice
     * (slice_id as id, so that anchors and highlighting match the blocks).
     */
    public static function sliceQuery(string $query): string
    {
        if (null === self::$preview || null === self::$preview['date']) {
            return $query;
        }
        $sql = Sql::factory();
        $history = array_map(static fn ($c) => $c->name, Table::get(ArticleSliceHistory::getTable())->getColumns());
        $cols = [];
        foreach (Table::get('rex_article_slice')->getColumns() as $c) {
            if ('id' !== $c->name && in_array($c->name, $history, true)) {
                $cols[] = $sql->escapeIdentifier($c->name);
            }
        }
        $derived = '(SELECT slice_id AS id, ' . implode(', ', $cols) . ' FROM ' . $sql->escapeIdentifier(ArticleSliceHistory::getTable())
            . ' WHERE slice_id > 0 AND history_date = ' . $sql->escape(self::$preview['date']) . ') AS rex_article_slice';

        return (string) preg_replace('/FROM\s+rex_article_slice\b/', 'FROM ' . $derived, $query, 1);
    }

    /** Highlighting: data-vdiff on the outermost element of a block */
    public static function markSlice(string $html, int $sliceId): string
    {
        if (!isset(self::$marks[$sliceId])) {
            return $html;
        }

        return (string) preg_replace('~^(\s*<[a-zA-Z][a-zA-Z0-9-]*)(?=[\s>/])~', '$1 data-vdiff="' . self::$marks[$sliceId] . '"', $html, 1);
    }

    /** Highlights for the preview: difference between the state and the next older one */
    public static function prepareMarks(int $articleId, int $languageId, string $key): void
    {
        $states = self::states($articleId, $languageId);
        foreach ($states as $i => $st) {
            if ($st['key'] === $key && isset($states[$i + 1])) {
                self::$marks = self::diff($st['blocks'], $st['modules'], $states[$i + 1]['blocks'], $states[$i + 1]['modules'])['marks'];
            }
        }
    }

    public static function isWorkVersion(): bool
    {
        return ArticleRevision::LIVE !== FluidAddon::revision();
    }
}
