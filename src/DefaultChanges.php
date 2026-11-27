<?php

namespace Klxm\Fluid;

use Redaxo\Core\Cache;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Throwable;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;
use function str_contains;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Changed default values of block fields: existing blocks keep their appearance (model: KLXM Studio,
 * Core\Blocks\DefaultChanges).
 *
 * On output fluid fills missing field values with the property's default (FieldForm::withDefaults). If a default
 * changes – or a field is added whose default looks different than before –, stored blocks without a value would
 * suddenly look different after an update. So this migration writes the previous state explicitly into all stored
 * blocks without a value, once per installation; afterwards the new default applies only to new blocks.
 *
 * Each change (CHANGES, key = identifier) runs exactly once (marker in fluid's configuration, `block_defaults`); it is
 * also idempotent (existing values stay, null too). Runs on `console migrate` (FluidAddon::install) and with
 * `console fluid:upgrade:defaults` (also `--dry-run`).
 *
 * Places: slices (live and working version, column `data`), their versions (rex_article_slice_history) and the fields
 * „Fluid-Blöcke“ of all YForm tables. Blocks in layout columns are separate slices and thus included.
 *
 * Custom blocks and kits register changes via the extension point FLUID_DEFAULT_CHANGES (identifier => [block type =>
 * [field => previous state]]); block type as in `BlockModule::type()` (`fluid.media_text` → `media_text`).
 */
final class DefaultChanges
{
    public const string CONFIG = 'block_defaults';

    /** identifier => [block type => [field => previous state]] */
    public const array CHANGES = [
        // beta.9: text + image „Bild ausrichten“ (default „Mitlaufen“) – existing ones stay centred
        '2026-11-media-text-valign' => ['media_text' => ['valign' => 'center']],
        // beta.9: gallery „Größe bei nur einem Bild“ (default „Groß“) – existing ones stay „wie die Galerie“
        '2026-11-gallery-single-size' => ['gallery' => ['single_size' => 'grid']],
        // beta.9: scrollytelling image format „Automatisch“ (default for new blocks) – existing ones keep 4:5
        '2026-11-scrolly-ratio' => ['scrolly' => ['ratio' => '4:5']],
    ];

    /** @return array<string, array<string, array<string, mixed>>> all changes (own and registered) */
    public static function changes(): array
    {
        /** @var array<string, array<string, array<string, mixed>>> */
        return (array) Extension::dispatch(new ExtensionPoint('FLUID_DEFAULT_CHANGES', self::CHANGES));
    }

    /**
     * Rules of the given (default: all) changes combined: [type => [field => value]].
     *
     * @param list<string>|null $ids
     * @return array<string, array<string, mixed>>
     */
    public static function rules(?array $ids = null): array
    {
        $out = [];
        foreach (self::changes() as $id => $types) {
            if (null !== $ids && !in_array($id, $ids, true)) {
                continue;
            }
            foreach ($types as $type => $fields) {
                $out[$type] = ($out[$type] ?? []) + $fields;
            }
        }

        return $out;
    }

    /** @return list<string> changes not yet run in this installation */
    public static function pending(): array
    {
        return array_values(array_diff(array_keys(self::changes()), self::done()));
    }

    /** @return list<string> */
    private static function done(): array
    {
        return array_values(array_filter((array) FluidAddon::instance()->getConfig(self::CONFIG, []), is_string(...)));
    }

    /**
     * Values of ONE block (as in the column `data`, also the former format {values, tunes}): add missing fields.
     *
     * @param array<array-key, mixed> $data
     * @param array<string, mixed> $fields field => previous state
     * @return array<array-key, mixed>
     */
    public static function patchData(array $data, array $fields, int &$n): array
    {
        if (BlockModule::isLegacy($data)) {
            $data['values'] = self::patchData((array) $data['values'], $fields, $n);

            return $data;
        }
        foreach ($fields as $field => $value) {
            if (!array_key_exists($field, $data)) {
                $data[$field] = $value;
                ++$n;
            }
        }

        return $data;
    }

    /**
     * Migrate the column `data` of a slice; null = unchanged.
     *
     * @param array<string, mixed> $fields
     */
    public static function patchJson(?string $json, array $fields): ?string
    {
        $data = null === $json || '' === $json ? [] : json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        $n = 0;
        $data = self::patchData($data, $fields, $n);

        return $n ? (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    /**
     * Migrate the value of a field „Fluid-Blöcke“ ([{module, data, tunes}, …]); null = unchanged.
     *
     * @param array<string, array<string, mixed>> $rules type => fields
     * @param array<string, string> $types module key => block type
     */
    public static function patchBlockList(?string $json, array $rules, array $types): ?string
    {
        if (null === $json || '' === $json || !str_contains($json, '"module"')) {
            return null;
        }
        $list = json_decode($json, true);
        if (!is_array($list)) {
            return null;
        }
        $n = 0;
        foreach ($list as $i => $item) {
            $type = is_array($item) && is_string($item['module'] ?? null) ? ($types[$item['module']] ?? null) : null;
            if (null !== $type && isset($rules[$type])) {
                $list[$i]['data'] = self::patchData(is_array($item['data'] ?? null) ? $item['data'] : [], $rules[$type], $n);
            }
        }

        return $n ? (string) json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    /** @return array<string, string> module keys of the blocks => block type */
    private static function types(): array
    {
        $out = [];
        foreach (BlockModule::all() as $key => $class) {
            $out[$key] = $class::type();
        }

        return $out;
    }

    /**
     * Run pending changes and mark them as done.
     *
     * @return list<string> log lines
     */
    public static function run(bool $dry = false): array
    {
        $ids = self::pending();
        if (!$ids) {
            return [];
        }
        $rules = self::rules($ids);
        $types = self::types();
        $modules = array_keys(array_filter($types, static fn (string $t) => isset($rules[$t])));
        $count = ['slices' => 0, 'history' => 0, 'records' => 0];
        $log = [];

        if ($modules) {
            foreach (['slices' => 'rex_article_slice', 'history' => ArticleSliceHistory::getTable()] as $what => $table) {
                try {
                    $rows = Sql::factory()->getArray('SELECT id, module, data FROM ' . $table . ' WHERE module IN (' . implode(',', array_fill(0, count($modules), '?')) . ')', $modules);
                } catch (Throwable) {
                    continue;   // Table missing (e.g. history not created yet)
                }
                foreach ($rows as $r) {
                    $json = self::patchJson(is_string($r['data']) ? $r['data'] : null, $rules[$types[(string) $r['module']]]);
                    if (null === $json) {
                        continue;
                    }
                    ++$count[$what];
                    if (!$dry) {
                        Sql::factory()->setQuery('UPDATE ' . $table . ' SET data = ? WHERE id = ?', [$json, (int) $r['id']]);
                    }
                }
            }
            foreach (self::blockFields() as [$table, $field]) {
                try {
                    foreach (Sql::factory()->getArray('SELECT id, `' . $field . '` AS v FROM `' . $table . '`') as $r) {
                        $json = self::patchBlockList(is_string($r['v']) ? $r['v'] : null, $rules, $types);
                        if (null === $json) {
                            continue;
                        }
                        ++$count['records'];
                        if (!$dry) {
                            Sql::factory()->setQuery('UPDATE `' . $table . '` SET `' . $field . '` = ? WHERE id = ?', [$json, (int) $r['id']]);
                        }
                    }
                } catch (Throwable $e) {
                    $log[] = 'Tabelle ' . $table . ': ' . $e->getMessage();
                }
            }
        }
        if (!$dry) {
            FluidAddon::instance()->setConfig(self::CONFIG, array_values(array_unique(array_merge(self::done(), $ids))));
            if ($count['slices'] + $count['history'] + $count['records']) {
                Cache::delete();   // Rebuild content
            }
        }
        $log[] = sprintf('Blöcke: bisheriges Aussehen festgehalten (%s) – %d Slice(s), %d Version(en), %d Datensatz/Datensätze%s',
            implode(', ', $ids), $count['slices'], $count['history'], $count['records'], $dry ? ' (Probelauf, nichts geschrieben)' : '');

        return $log;
    }

    /** @return list<array{0: string, 1: string}> table and field of all fields „Fluid-Blöcke“ in YForm tables */
    private static function blockFields(): array
    {
        if (!Data\YFormBridge::available() || !class_exists(Data\FluidBlocks::class)) {
            return [];
        }
        $out = [];
        foreach (array_keys(Data\YFormBridge::tables()) as $name) {
            $table = Data\YFormBridge::table($name);
            foreach ($table?->getFields() ?? [] as $f) {
                if ($f instanceof Data\FluidBlocks) {
                    $out[] = [$name, $f->name];
                }
            }
        }

        return $out;
    }
}
