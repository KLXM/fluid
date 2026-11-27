<?php

namespace Klxm\Fluid\Console;

use Klxm\Fluid\BlockModule;
use Redaxo\Core\Cache;
use Redaxo\Core\Console\AbstractCommand;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ArticleSliceReference;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_filter;
use function array_keys;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function preg_match;
use function str_starts_with;

/**
 * Converts blocks of an older REDAXO 6 database to the core slice format from 9132fc1/fa458f9 on.
 *
 * The core doesn't migrate development databases itself: the fixed columns value1–20, media1–10 … no longer exist,
 * the module values are stored as JSON in `data`, and `ctype_id` is now `content_section_id`. `migrate` creates the new
 * columns empty. This command renames (or copies, if `migrate` already ran), creates `data` and fills it
 * from the old columns. Can be run repeatedly; the old columns stay until --drop-old removes them.
 *
 * Also: blocks in the former fluid format `{values: {…}, tunes: {…}}` → core storage (each field under its
 * key, plus `tunes`) – so the core fills the block properties itself.
 *
 * @internal
 */
#[AsCommand(name: 'fluid:upgrade:slices', description: 'Moves blocks of older REDAXO 6 databases into the data column (before or after migrate)')]
final class UpgradeSlicesCommand extends AbstractCommand
{
    private const array TABLES = ['rex_article_slice', 'rex_article_slice_history'];

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Remove the old columns (ctype_id, value1–20, media…, link…) afterwards')] bool $dropOld = false,
    ): int {
        foreach (self::TABLES as $name) {
            $table = Table::get($name);
            if (!$table->exists()) {
                continue;
            }
            $io->section($name);

            // ctype_id → content_section_id (if migrate already ran, both exist: copy the values)
            if ($table->hasColumn('ctype_id')) {
                if ($table->hasColumn('content_section_id')) {
                    Sql::factory()->setQuery('UPDATE ' . $name . ' SET content_section_id = ctype_id WHERE content_section_id = 0');
                    // the core no longer fills ctype_id: as a required field without default every new slice would fail
                    $table->ensureColumn(Column::smallint('ctype_id', unsigned: true, nullable: true))->alter();
                    $io->text('content_section_id copied from ctype_id, ctype_id optional');
                } else {
                    $table->renameColumn('ctype_id', 'content_section_id')->alter();
                    $io->text('ctype_id → content_section_id');
                }
            }
            if (!$table->hasColumn('data')) {
                $table->ensureColumn(Column::json('data', nullable: true))->alter();
                $io->text('Column data created');
            }

            $count = $table->hasColumn('value1') ? self::fill($name) : 0;
            $io->text($count . ' slices migrated');
            $io->text(self::flatten($name) . ' blocks switched to the core storage');

            if ($dropOld) {
                $table = Table::get($name);
                $old = array_filter(array_keys($table->getColumns()), static fn (string $c) => 'ctype_id' === $c || 1 === preg_match('~^(value|media|medialist|link|linklist)\d+$~', $c));
                foreach ($old as $c) {
                    $table->removeColumn($c);
                }
                if ($old) {
                    $table->alter();
                    $io->text(count($old) . ' old columns removed');
                }
            }
        }
        Cache::delete();   // rebuild content and classes
        if (Table::get('rex_article_slice_reference')->exists()) {
            $io->text(ArticleSliceReference::rebuild() . ' references (media, pages) rebuilt');
        }
        $io->success('Done. Then (if not done yet) run `bin/console migrate`.');

        return Command::SUCCESS;
    }

    /** Fills slices without data from value1/value2 */
    private static function fill(string $table): int
    {
        $blocks = array_keys(BlockModule::all());
        $rows = Sql::factory()->getArray('SELECT id, module, value1, value2 FROM ' . $table . ' WHERE data IS NULL');
        $count = 0;
        foreach ($rows as $r) {
            $module = (string) $r['module'];
            $data = match (true) {
                in_array($module, $blocks, true), str_starts_with($module, 'fluid.') => BlockModule::store(self::json($r['value1']), self::json($r['value2'])),
                'yform_form' === $module => ['form' => (string) $r['value1']],
                default => [],
            };
            if ([] === $data) {
                continue;
            }
            Sql::factory()->setQuery('UPDATE ' . $table . ' SET data = ? WHERE id = ?', [ArticleSlice::encodeData($data), (int) $r['id']]);
            ++$count;
        }

        return $count;
    }

    /** Blocks in the format {values, tunes} → each field under its key (BlockModule::store) */
    private static function flatten(string $table): int
    {
        $count = 0;
        foreach (Sql::factory()->getArray('SELECT id, data FROM ' . $table . " WHERE data IS NOT NULL AND module LIKE '%.%'") as $r) {
            $data = self::json($r['data']);
            if (!BlockModule::isLegacy($data)) {
                continue;
            }
            $tunes = is_array($data['tunes'] ?? null) ? $data['tunes'] : [];
            Sql::factory()->setQuery('UPDATE ' . $table . ' SET data = ? WHERE id = ?', [ArticleSlice::encodeData(BlockModule::store($data['values'], $tunes)), (int) $r['id']]);
            ++$count;
        }

        return $count;
    }

    /** @return array<string, mixed> */
    private static function json(mixed $value): array
    {
        $v = is_string($value) && '' !== $value ? json_decode($value, true) : null;

        return is_array($v) ? $v : [];
    }
}
