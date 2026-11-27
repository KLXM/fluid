<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Security\User;
use Redaxo\Core\Translation\I18n;

use function array_map;
use function array_merge;
use function array_splice;
use function count;
use function in_array;
use function is_array;
use function is_object;
use function is_string;

/**
 * Dragging, duplicating, copying and pasting blocks (as in KLXM Studio). A layout counts as one unit with its column
 * blocks; copies of a layout take the columns along. The clipboard lives in the browser (localStorage) – so a block
 * can also be pasted on another page. Only what the template and permissions allow is pasted.
 */
final class BlockOps
{
    /** @return list<list<int>> Units in order: [slice, …column blocks for a layout] */
    private static function units(Article $article): array
    {
        $rows = Sql::factory()->getArray(
            'SELECT id, data FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND content_section_id = 1 AND revision = ? ORDER BY priority',
            [$article->id, $article->languageId, FluidAddon::revision()],
        );
        $units = [];
        for ($i = 0, $n = count($rows); $i < $n; ++$i) {
            $id = (int) $rows[$i]['id'];
            $unit = [$id];
            if (Layout::isLayout(ArticleSlice::getArticleSliceById($id, $article->languageId, FluidAddon::revision()))) {
                while ($i + 1 < $n && (int) (BlockModule::stored($rows[$i + 1]['data'])['tunes']['col'] ?? 0) > 0) {
                    $unit[] = (int) $rows[++$i]['id'];
                }
            }
            $units[] = $unit;
        }

        return $units;
    }

    private static function renumber(Article $article, array $units): void
    {
        $prio = 0;
        $upd = Sql::factory();
        foreach (array_merge(...$units) as $id) {
            $upd->setQuery('UPDATE rex_article_slice SET priority = ? WHERE id = ?', [++$prio, $id]);
        }
        ArticleCache::delete($article->id, $article->languageId);
    }

    /**
     * Puts a new slice at exactly this position (the following ones move down). ContentHandler::addSlice() sorts equal
     * priorities by modification date – if blocks are created in the same second, the order would otherwise be random
     * (e.g. a column block behind an unrelated block instead of below its layout).
     */
    public static function place(ArticleSlice $slice, int $priority): void
    {
        $sql = Sql::factory();
        $where = 'article_id = ? AND language_id = ? AND content_section_id = ? AND revision = ?';
        $params = [$slice->articleId, $slice->languageId, $slice->contentSectionId, $slice->revision];
        $ids = array_map(static fn (array $r) => (int) $r['id'], $sql->getArray('SELECT id FROM rex_article_slice WHERE ' . $where . ' AND id <> ? ORDER BY priority, id', [...$params, $slice->id]));
        array_splice($ids, max(0, min(count($ids), $priority - 1)), 0, [$slice->id]);
        foreach ($ids as $i => $id) {
            $sql->setQuery('UPDATE rex_article_slice SET priority = ? WHERE id = ?', [$i + 1, $id]);
        }
    }

    /** Drags a block (top level) before another; $before = 0 → to the end */
    public static function reorder(Article $article, int $sliceId, int $before): void
    {
        $units = self::units($article);
        $from = null;
        foreach ($units as $k => $u) {
            if ($u[0] === $sliceId) {
                $from = $k;
            }
        }
        if (null === $from) {
            throw new ApiError(I18n::rawMsg('fluid_blockops_reorder_sections'));
        }
        $unit = $units[$from];
        array_splice($units, $from, 1);
        $to = count($units);
        foreach ($units as $k => $u) {
            if ($u[0] === $before) {
                $to = $k;
            }
        }
        array_splice($units, $to, 0, [$unit]);
        self::renumber($article, $units);
    }

    /** Moves a block into the neighbouring column of its layout (← →), to the end of that column */
    public static function column(Article $article, int $sliceId, int $dir): void
    {
        $slice = ArticleSlice::getArticleSliceById($sliceId, $article->languageId, FluidAddon::revision()) ?? throw new ApiError(I18n::rawMsg('fluid_error_block_not_found'));
        $parent = Layout::parentOf($slice) ?? throw new ApiError(I18n::rawMsg('fluid_blockops_no_layout'));
        $n = Layout::count(BlockModule::valuesOf($parent));
        $col = min($n, Layout::col($slice)) + $dir;
        if ($col < 1 || $col > $n) {
            throw new ApiError($dir < 0 ? I18n::rawMsg('fluid_blockops_first_col') : I18n::rawMsg('fluid_blockops_last_col'));
        }
        $tunes = BlockModule::tunesOf($slice);
        $tunes['col'] = $col;
        Sql::factory()->setQuery('UPDATE rex_article_slice SET data = ? WHERE id = ?', [BlockModule::encodeStore(BlockModule::valuesOf($slice), $tunes), $slice->id]);
        // to the end of the new column: in the group behind the last block of that column
        $units = self::units($article);
        foreach ($units as $k => $u) {
            if ($u[0] === $parent->id) {
                $unit = array_values(array_filter($u, static fn (int $id) => $id !== $slice->id));
                $last = 0;
                foreach ($unit as $i => $id) {
                    if ($i > 0 && Layout::col(ArticleSlice::getArticleSliceById($id, $article->languageId, FluidAddon::revision())) <= $col) {
                        $last = $i;
                    }
                }
                array_splice($unit, $last + 1, 0, [$slice->id]);
                $units[$k] = $unit;
            }
        }
        self::renumber($article, $units);
    }

    /** Content of a block for the clipboard (with column blocks for a layout): module, values, tunes, children */
    public static function payload(Article $article, int $sliceId): array
    {
        $slice = ArticleSlice::getArticleSliceById($sliceId, $article->languageId, FluidAddon::revision()) ?? throw new ApiError(I18n::rawMsg('fluid_error_block_not_found'));
        $item = static fn (ArticleSlice $s) => ['module' => $s->moduleKey, 'values' => (object) BlockModule::valuesOf($s), 'tunes' => (object) BlockModule::tunesOf($s)];
        $out = $item($slice);
        if (Layout::isLayout($slice)) {
            $out['children'] = array_map($item, Layout::children($slice));
        }
        $label = self::label($slice->moduleKey);

        return ['clip' => $out, 'label' => $label];
    }

    /** May the block be pasted here? fluid blocks and ordinary core modules – permissions and template */
    private static function check(User $user, string $template, string $key): void
    {
        if (!Module::exists($key)) {
            throw new ApiError(I18n::rawMsg('fluid_error_block_insert_here'));
        }
        $label = self::label($key);
        $perm = $user->getComplexPerm('modules');
        if (!$user->admin && (!$perm instanceof ModulePermission || !$perm->hasPerm($key))) {
            throw new ApiError(I18n::rawMsg('fluid_blockops_block_perm', $label));
        }
        if (!Template::checkModuleAllowed($template, 1, $key)) {
            throw new ApiError(I18n::rawMsg('fluid_blockops_block_template', $label));
        }
    }

    /** Name eines Blocks bzw. Moduls */
    public static function label(string $key): string
    {
        $m = BlockModule::classFor($key);
        if (null !== $m) {
            return $m::label();
        }
        $class = Module::getClass($key);

        return null !== $class ? $class::name() : $key;
    }

    /** Pastes after a block (top level; 0 = at the very top); returns the ID of the new section */
    public static function insert(Article $article, User $user, int $after, array $clip): int
    {
        $template = (string) $article->templateKey;
        $check = static fn (string $key) => self::check($user, $template, $key);
        $items = [$clip];
        foreach ((array) ($clip['children'] ?? []) as $c) {
            if (is_array($c)) {
                $items[] = $c;
            }
        }
        foreach ($items as $it) {
            $check((string) ($it['module'] ?? ''));
        }
        // Position: after the unit of $after
        $units = self::units($article);
        $prio = 1;
        foreach ($units as $u) {
            if (in_array($after, $u, true)) {
                $prio = (int) Sql::factory()->getArray('SELECT MAX(priority) AS p FROM rex_article_slice WHERE id IN (' . implode(',', array_map('intval', $u)) . ')')[0]['p'] + 1;
            }
        }
        $first = 0;
        foreach ($items as $i => $it) {
            [$values, $tunes] = self::clipValues($it);
            $tunes = Lock::forCopy($user, $tunes);   // Copy of locked blocks: locked again only with permission
            if (0 === $i) {
                unset($tunes['col']);   // the pasted section is on the top level
            }
            $key = (string) $it['module'];
            // ordinary modules: values as stored (without fluid's section)
            $data = null !== BlockModule::classFor($key) ? BlockModule::store($values, $tunes) : $values;
            $new = ContentHandler::addSlice($article->id, $article->languageId, 1, $key, $data, $prio + $i, FluidAddon::revision());
            self::place($new, $prio + $i);
            $id = $new->id;
            if (0 === $i) {
                $first = $id;
            }
        }
        ArticleCache::delete($article->id, $article->languageId);

        return $first;
    }

    /**
     * Pastes after a block – also in a layout column: if $after is in a column, the block goes right after it in the
     * same column (only blocks allowed in columns, no layout). Otherwise like insert().
     */
    public static function insertAfter(Article $article, User $user, int $after, array $clip): int
    {
        $target = $after > 0 ? ArticleSlice::getArticleSliceById($after, $article->languageId, FluidAddon::revision()) : null;
        $col = null !== $target && null !== Layout::parentOf($target) ? Layout::col($target) : 0;
        if (0 === $col) {
            return self::insert($article, $user, $after, $clip);
        }
        $key = (string) ($clip['module'] ?? '');
        $m = BlockModule::classFor($key);
        if (null === $m || !empty($clip['children']) || !$m::nestable()) {
            throw new ApiError(I18n::rawMsg('fluid_blockops_no_column'));
        }
        $perm = $user->getComplexPerm('modules');
        if (!$user->admin && (!$perm instanceof ModulePermission || !$perm->hasPerm($key))) {
            throw new ApiError(I18n::rawMsg('fluid_blockops_block_perm', $m::label()));
        }
        if (!Template::checkModuleAllowed((string) $article->templateKey, 1, $key)) {
            throw new ApiError(I18n::rawMsg('fluid_blockops_block_template', $m::label()));
        }
        [$values, $tunes] = self::clipValues($clip);
        $tunes = Lock::forCopy($user, $tunes);
        $tunes['col'] = $col;
        $new = ContentHandler::addSlice($article->id, $article->languageId, $target->contentSectionId, $key, BlockModule::store($values, $tunes), $target->priority + 1, FluidAddon::revision());
        self::place($new, $target->priority + 1);
        ArticleCache::delete($article->id, $article->languageId);

        return $new->id;
    }

    /**
     * Completes cutting: deletes the source block (for a layout including columns) after the copy has been pasted.
     * Only with permission for its page; if it is already gone, nothing happens.
     *
     * @param array{article?: int, language?: int, slice?: int} $cut
     */
    public static function finishCut(User $user, array $cut, int $inserted): void
    {
        $id = (int) ($cut['slice'] ?? 0);
        $source = $id > 0 ? ArticleSlice::getArticleSliceById($id, (int) ($cut['language'] ?? 0), FluidAddon::revision()) : null;
        if (null === $source || $source->id === $inserted) {
            return;
        }
        $article = Article::get($source->articleId, $source->languageId);
        $perm = $user->getComplexPerm('structure');
        if (null === $article || (!$user->admin && !($perm instanceof StructurePermission && $perm->hasCategoryPerm($article->categoryId)))) {
            throw new ApiError(I18n::rawMsg('fluid_blockops_cut_no_perm'));
        }
        if (null !== BlockModule::classFor($source->moduleKey) && '' !== Lock::reason($user, $source)) {
            throw new ApiError(I18n::rawMsg('fluid_blockops_cut_locked'));
        }
        ContentHandler::deleteSlice($source->id);   // Column blocks of a layout: FluidAddon (SliceDeleting)
        ArticleCache::delete($article->id, $article->languageId);
    }

    /**
     * Values of a clipboard entry; older entries (before REDAXO core 9132fc1) carry value1/value2 as JSON.
     *
     * @param array<array-key, mixed> $it
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    public static function clipValues(array $it): array
    {
        // directly from payload() (duplicating) they contain objects, from the browser (JSON) arrays
        $array = static fn (mixed $v): array => is_object($v) ? (array) json_decode((string) json_encode($v), true) : (is_array($v) ? $v : []);
        $values = $it['values'] ?? BlockModule::decode(is_string($it['value1'] ?? null) ? $it['value1'] : null);
        $tunes = $it['tunes'] ?? BlockModule::decode(is_string($it['value2'] ?? null) ? $it['value2'] : null);

        return [$array($values), $array($tunes)];
    }
}
