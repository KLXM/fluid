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
 * Geänderte Standardwerte von Blockfeldern: Bestehende Blöcke behalten ihr Aussehen (Vorbild KLXM Studio,
 * Core\Blocks\DefaultChanges).
 *
 * Beim Ausgeben füllt fluid fehlende Feldwerte mit dem Standard der Eigenschaft (FieldForm::withDefaults). Ändert sich ein
 * Standard – oder kommt ein Feld dazu, dessen Standard anders aussieht als bisher –, sähen gespeicherte Blöcke ohne Wert nach
 * einem Update plötzlich anders aus. Darum schreibt diese Umstellung einmal je Installation in alle gespeicherten Blöcke ohne
 * Wert ausdrücklich den bisherigen Zustand; danach gilt der neue Standard nur für neue Blöcke.
 *
 * Je Änderung (CHANGES, Schlüssel = Kennung) läuft die Umstellung genau einmal (Merker in der Konfiguration von fluid,
 * `block_defaults`); sie ist zudem idempotent (vorhandene Werte bleiben, auch null). Läuft bei `console migrate`
 * (FluidAddon::install) und mit `console fluid:upgrade:defaults` (auch `--dry-run`).
 *
 * Orte: Slices (Live- und Arbeitsversion, Spalte `data`), deren Versionen (rex_article_slice_history) und die Felder
 * „Fluid-Blöcke“ aller YForm-Tabellen. Blöcke in Layout-Spalten sind eigene Slices und damit dabei.
 *
 * Eigene Blöcke und Kits melden Änderungen über den Extension Point FLUID_DEFAULT_CHANGES an (Kennung => [Blocktyp =>
 * [Feld => bisheriger Zustand]]); Blocktyp wie in `BlockModule::type()` (`fluid.media_text` → `media_text`).
 */
final class DefaultChanges
{
    public const string CONFIG = 'block_defaults';

    /** Kennung => [Blocktyp => [Feld => bisheriger Zustand]] */
    public const array CHANGES = [
        // beta.9: Text + Bild „Bild ausrichten“ (Standard „Mitlaufen“) – bestehende bleiben mittig
        '2026-11-media-text-valign' => ['media_text' => ['valign' => 'center']],
        // beta.9: Galerie „Größe bei nur einem Bild“ (Standard „Groß“) – bestehende bleiben „wie die Galerie“
        '2026-11-gallery-single-size' => ['gallery' => ['single_size' => 'grid']],
        // beta.9: Scrollytelling Bildformat „Automatisch“ (Standard für neue Blöcke) – bestehende behalten 4:5
        '2026-11-scrolly-ratio' => ['scrolly' => ['ratio' => '4:5']],
    ];

    /** @return array<string, array<string, array<string, mixed>>> alle Änderungen (eigene und angemeldete) */
    public static function changes(): array
    {
        /** @var array<string, array<string, array<string, mixed>>> */
        return (array) Extension::dispatch(new ExtensionPoint('FLUID_DEFAULT_CHANGES', self::CHANGES));
    }

    /**
     * Regeln der übergebenen (Standard: aller) Änderungen zusammengefasst: [typ => [feld => wert]].
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

    /** @return list<string> noch nicht ausgeführte Änderungen dieser Installation */
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
     * Werte EINES Blocks (wie in der Spalte `data`, auch das frühere Format {values, tunes}): fehlende Felder ergänzen.
     *
     * @param array<array-key, mixed> $data
     * @param array<string, mixed> $fields Feld => bisheriger Zustand
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
     * Spalte `data` eines Slices umstellen; null = unverändert.
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
     * Wert eines Feldes „Fluid-Blöcke“ ([{module, data, tunes}, …]) umstellen; null = unverändert.
     *
     * @param array<string, array<string, mixed>> $rules typ => Felder
     * @param array<string, string> $types Modulschlüssel => Blocktyp
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

    /** @return array<string, string> Modulschlüssel der Blöcke => Blocktyp */
    private static function types(): array
    {
        $out = [];
        foreach (BlockModule::all() as $key => $class) {
            $out[$key] = $class::type();
        }

        return $out;
    }

    /**
     * Offene Änderungen ausführen und als erledigt merken.
     *
     * @return list<string> Protokollzeilen
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
                    continue;   // Tabelle fehlt (z. B. Historie noch nicht angelegt)
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
                Cache::delete();   // Inhalte neu aufbauen
            }
        }
        $log[] = sprintf('Blöcke: bisheriges Aussehen festgehalten (%s) – %d Slice(s), %d Version(en), %d Datensatz/Datensätze%s',
            implode(', ', $ids), $count['slices'], $count['history'], $count['records'], $dry ? ' (Probelauf, nichts geschrieben)' : '');

        return $log;
    }

    /** @return list<array{0: string, 1: string}> Tabelle und Feld aller Felder „Fluid-Blöcke“ in YForm-Tabellen */
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
