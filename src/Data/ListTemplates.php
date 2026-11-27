<?php

namespace Klxm\Fluid\Data;

use Klxm\Fluid\ApiError;
use Klxm\Fluid\BlockModule;
use Klxm\Fluid\Editing;
use Klxm\Fluid\FluidAddon;
use Klxm\Fluid\Form\FieldForm;
use Klxm\Fluid\Form\Stored;
use Klxm\Fluid\Kit;
use Klxm\Fluid\Perm;
use Klxm\Fluid\Routing\Routes;
use Klxm\Fluid\VideoLinks;
use Redaxo\Core\Cache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table as DbTable;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\User;
use Throwable;
use Yakamara\YForm\Field\Media;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_string;
use function Redaxo\Core\View\escape;
use function strlen;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Listen-Vorlagen: gespeicherte Darstellungen (Optionssätze) für Listenblöcke wie die Datenliste – nach dem Vorbild von
 * KLXM Studio (Core\Blocks\ListTemplates, MIT).
 *
 * Eine Vorlage ist ein benannter Satz der Darstellungs-Optionen eines Blocks (Darstellung, Bild-, Text- und Zusatzfeld,
 * Sortierung, Besucher-Filter, Breite …) für eine Tabelle. Der Block behält, was zum Inhalt und zur Adresse gehört
 * (BLOCK_KEYS: Überschrift, Tabelle, Titelfeld, Bedingung, Anzahl, Blättern, Detailansicht, Menü …). Beim Ausgeben legt
 * resolve() die Werte der gewählten Vorlage über die Blockdaten (BlockModule::block()) – Views bekommen normale Daten.
 * **Ohne gewählte Vorlage (Feld „template“ leer, auch alle bestehenden Blöcke) bleibt alles wie bisher**; fehlt eine
 * gewählte Vorlage, gelten die eigenen Werte des Blocks.
 *
 * Quellen:
 *  - Tabelle rex_fluid_list_template (Verwaltung in der Seitenleiste: „Als Vorlage speichern“, Standard, Umbenennen, Löschen);
 *    Vorschläge je Tabelle legt seed() beim ersten Öffnen an.
 *  - Kit: kit.php → 'list_templates' => ['gross' => ['name' => …, 'options' => […], 'tables' => ['rex_…'], 'default' => true]] –
 *    Schlüssel „kit:gross“, nur lesbar.
 *  - Extension Point FLUID_LIST_TEMPLATES (gleiches Format, Params table, block) – Schlüssel „ext:…“.
 * Feldwerte von YForm-Feldern dürfen ohne Tabellen-Präfix stehen („title“ statt „rex_news.title“).
 *
 * Blöcke nehmen teil mit #[AsBlock(listTemplate: true)] (oder einer Liste weiterer Felder, die beim Block bleiben) und einem
 * Feld Field\ListTemplate. Recht zum Anlegen, Ändern und Festlegen des Standards: Perm::DESIGN_FULL.
 */
final class ListTemplates
{
    public const string TABLE = 'rex_fluid_list_template';

    /** Felder, die immer beim Block bleiben: Inhalt, Auswahl der Einträge, Adressen */
    public const array BLOCK_KEYS = ['eyebrow', 'title', 'intro', 'table', 'template', 'title_field', 'filter_field', 'filter_value', 'limit', 'pager',
        'detail', 'ics', 'empty_text', 'menu_entries', 'menu_limit', 'knowledge_style'];

    /** Merker: Vorschläge je „block|tabelle“ schon angelegt (auch wenn danach gelöscht) */
    public const string SEEDED = 'list_templates_seeded';

    /** @var array<string, array<string, array<string, mixed>>> */
    private static array $memo = [];

    public static function install(): void
    {
        DbTable::get(self::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('tkey', 'varchar(64)'))
            ->ensureColumn(new Column('name', 'varchar(120)'))
            ->ensureColumn(new Column('block', 'varchar(100)'))
            ->ensureColumn(new Column('table_name', 'varchar(191)'))
            ->ensureColumn(new Column('options', 'text', true))
            ->ensureColumn(new Column('is_default', 'tinyint(1)', false, '0'))
            ->ensureColumn(new Column('updated', 'datetime', true))
            ->ensure();
    }

    // ------------------------------------------------------------------ Definition

    /** @param class-string<BlockModule> $module */
    public static function supports(string $module): bool
    {
        return !empty($module::definition()['list_template']);
    }

    /**
     * Namen der Felder, die eine Vorlage festlegt: Darstellung und alle Felder außer BLOCK_KEYS (und weiteren des Blocks).
     *
     * @param class-string<BlockModule> $module
     * @return list<string>
     */
    public static function keys(string $module): array
    {
        $def = $module::definition();
        $keep = [...self::BLOCK_KEYS, ...(is_array($def['list_template'] ?? null) ? $def['list_template'] : [])];
        $out = $module::variants() ? ['variant'] : [];
        foreach ($module::fields() as $f) {
            $n = (string) $f['name'];
            if (!in_array($n, $keep, true) && 'listtemplate' !== ($f['type'] ?? '') && !str_ends_with($n, VideoLinks::SUFFIX)) {
                $out[] = $n;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ Lesen

    /**
     * Vorlagen eines Blocks für eine Tabelle: gespeicherte (Standard zuerst), dann Kit und Extension Point.
     *
     * @param class-string<BlockModule> $module
     * @return array<string, array{key: string, id: ?int, name: string, variant: string, options: array<string, mixed>, default: bool, source: string, locked: bool}>
     */
    public static function forTable(string $module, string $table): array
    {
        $mk = $module::key() . '|' . $table;
        if (isset(self::$memo[$mk])) {
            return self::$memo[$mk];
        }
        $out = [];
        try {
            $rows = Sql::factory()->getArray('SELECT * FROM ' . self::TABLE . ' WHERE block = ? AND table_name = ? ORDER BY is_default DESC, id', [$module::key(), $table]);
        } catch (Throwable) {
            $rows = [];   // vor migrate
        }
        foreach ($rows as $r) {
            $opts = json_decode((string) $r['options'], true);
            $opts = is_array($opts) ? $opts : [];
            $out[(string) $r['tkey']] = ['key' => (string) $r['tkey'], 'id' => (int) $r['id'], 'name' => (string) $r['name'], 'variant' => self::variantOf($module, $opts),
                'options' => $opts, 'default' => (bool) $r['is_default'], 'source' => 'db', 'locked' => false];
        }
        foreach (self::code($module, $table) as $k => $c) {
            $out[$k] = $c;
        }

        return self::$memo[$mk] = $out;
    }

    /**
     * Vorlagen aus dem Kit (kit.php → list_templates) und dem Extension Point FLUID_LIST_TEMPLATES.
     *
     * @param class-string<BlockModule> $module
     * @return array<string, array<string, mixed>>
     */
    public static function code(string $module, string $table): array
    {
        $kit = (array) ($module::kit()->definition()['list_templates'] ?? []);
        /** @var mixed $ext */
        $ext = Extension::dispatch(new ExtensionPoint('FLUID_LIST_TEMPLATES', [], ['table' => $table, 'block' => $module::key()]));
        $out = [];
        foreach (['kit' => $kit, 'ext' => (array) $ext] as $src => $list) {
            foreach ($list as $k => $c) {
                if (!is_array($c) || !preg_match('~^[a-z0-9][a-z0-9_-]{0,47}$~', (string) $k)) {
                    continue;
                }
                if (isset($c['block']) && $c['block'] !== $module::key() && 'fluid.' . $c['block'] !== $module::key()) {
                    continue;
                }
                if (isset($c['tables']) && !in_array($table, (array) $c['tables'], true)) {
                    continue;
                }
                $opts = (array) ($c['options'] ?? []);
                $out[$src . ':' . $k] = ['key' => $src . ':' . $k, 'id' => null, 'name' => (string) ($c['name'] ?? $k), 'variant' => self::variantOf($module, $opts),
                    'options' => $opts, 'default' => !empty($c['default']), 'source' => $src, 'locked' => true];
            }
        }

        return $out;
    }

    /** @param class-string<BlockModule> $module */
    public static function find(string $module, string $table, string $key): ?array
    {
        return '' === $key || '' === $table ? null : (self::forTable($module, $table)[$key] ?? null);
    }

    /** Standard der Tabelle: gespeicherter, sonst der eines Kits/Extension Points, sonst null @param class-string<BlockModule> $module */
    public static function defaultKey(string $module, string $table): ?string
    {
        $code = null;
        foreach (self::forTable($module, $table) as $k => $t) {
            if (!$t['default']) {
                continue;
            }
            if ('db' === $t['source']) {
                return $k;
            }
            $code ??= $k;
        }

        return $code;
    }

    /** @param class-string<BlockModule> $module @param array<string, mixed> $options */
    public static function variantOf(string $module, array $options): string
    {
        $v = (string) ($options['variant'] ?? '');
        $variants = $module::variants();

        return isset($variants[$v]) ? $v : (string) array_key_first($variants);
    }

    // ------------------------------------------------------------------ Anwenden

    /**
     * Blockdaten mit der gewählten Vorlage (BlockModule::block(), Data\Detail); ohne Vorlage unverändert.
     *
     * @param class-string<BlockModule> $module
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function resolve(string $module, array $data): array
    {
        $key = (string) ($data['template'] ?? '');
        if ('' === $key || !self::supports($module)) {
            return $data;
        }
        $table = (string) ($data['table'] ?? '');
        $tpl = self::find($module, $table, $key);

        return null !== $tpl ? self::apply($module, $data, $tpl, $table) : $data;
    }

    /**
     * Werte der Vorlage über die Blockdaten legen; fehlende Optionen = Standard des Feldes (eine Vorlage bestimmt die
     * Darstellung ganz).
     *
     * @param class-string<BlockModule> $module
     * @param array<string, mixed> $data
     * @param array<string, mixed> $tpl
     * @return array<string, mixed>
     */
    public static function apply(string $module, array $data, array $tpl, string $table): array
    {
        $byName = array_column($module::fields(), null, 'name');
        foreach (self::keys($module) as $k) {
            if ('variant' === $k) {
                $data['variant'] = $tpl['variant'];
                continue;
            }
            $f = $byName[$k] ?? [];
            $v = array_key_exists($k, $tpl['options']) ? $tpl['options'][$k] : ($f['default'] ?? ('bool' === ($f['type'] ?? '') ? false : ''));
            // YForm-Felder: ohne Präfix („title“) gilt die Tabelle des Blocks
            if ('yform_field' === ($f['type'] ?? '') && is_string($v) && '' !== $v && !str_contains($v, '.')) {
                $v = $table . '.' . $v;
            }
            $data[$k] = $v;
        }

        return $data;
    }

    /**
     * Optionen aus den Blockdaten (Als Vorlage speichern) – nur die Felder der Vorlage, YForm-Felder ohne Präfix.
     *
     * @param class-string<BlockModule> $module
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function optionsFrom(string $module, array $data, string $table): array
    {
        $data = FieldForm::withDefaults($module::fields(), FieldForm::normalize($module::fields(), $data), $module::variants());
        $byName = array_column($module::fields(), null, 'name');
        $out = [];
        foreach (self::keys($module) as $k) {
            $v = $data[$k] ?? null;
            if ('yform_field' === ($byName[$k]['type'] ?? '') && is_string($v) && str_starts_with($v, $table . '.')) {
                $v = substr($v, strlen($table) + 1);
            }
            $out[$k] = $v;
        }

        return $out;
    }

    // ------------------------------------------------------------------ Schreiben

    /** @param class-string<BlockModule> $module @param array<string, mixed> $options */
    public static function create(string $module, string $table, string $name, array $options, bool $default = false): string
    {
        $name = self::cleanName($name);
        $key = self::uniqueKey($module, $table, $name);
        Sql::factory()->setTable(self::TABLE)->setValues(['tkey' => $key, 'name' => $name, 'block' => $module::key(), 'table_name' => $table,
            'options' => json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'is_default' => 0, 'updated' => date('Y-m-d H:i:s')])->insert();
        self::changed();
        if ($default) {
            self::setDefault($module, $table, $key);
        }

        return $key;
    }

    /** @param class-string<BlockModule> $module @param array<string, mixed>|null $options */
    public static function update(string $module, string $table, string $key, ?string $name, ?array $options): void
    {
        $sql = Sql::factory()->setTable(self::TABLE)->setWhere(['block' => $module::key(), 'table_name' => $table, 'tkey' => $key]);
        if (null !== $name) {
            $sql->setValue('name', self::cleanName($name));
        }
        if (null !== $options) {
            $sql->setValue('options', json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $sql->setValue('updated', date('Y-m-d H:i:s'))->update();
        self::changed();
    }

    /** Standard der Tabelle setzen (null = keiner) @param class-string<BlockModule> $module */
    public static function setDefault(string $module, string $table, ?string $key): void
    {
        Sql::factory()->setQuery('UPDATE ' . self::TABLE . ' SET is_default = IF(tkey = ?, 1, 0) WHERE block = ? AND table_name = ?', [(string) $key, $module::key(), $table]);
        self::changed();
    }

    /** @param class-string<BlockModule> $module */
    public static function delete(string $module, string $table, string $key): void
    {
        Sql::factory()->setQuery('DELETE FROM ' . self::TABLE . ' WHERE block = ? AND table_name = ? AND tkey = ?', [$module::key(), $table, $key]);
        self::changed();
    }

    private static function changed(): void
    {
        self::$memo = [];
        Cache::delete();   // Seiten mit der Vorlage neu ausgeben (Inhalts-Cache der Artikel)
    }

    private static function cleanName(string $name): string
    {
        $name = trim((string) preg_replace('~\s+~u', ' ', strip_tags($name)));

        return '' === $name ? 'Vorlage' : mb_substr($name, 0, 80);
    }

    /** @param class-string<BlockModule> $module */
    private static function uniqueKey(string $module, string $table, string $name): string
    {
        $base = trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower(Routes::slugify($name))), '-') ?: 'vorlage';
        $base = substr($base, 0, 40);
        $key = $base;
        for ($i = 2; isset(self::forTable($module, $table)[$key]); ++$i) {
            $key = $base . '-' . $i;
        }

        return $key;
    }

    // ------------------------------------------------------------------ Vorschläge

    /**
     * Vorschläge für eine Tabelle (je nach Feldern): Karten immer; Liste mit Bild, wenn ein Bildfeld da ist; Tabelle
     * sortierbar; Kompakte Karten. Nur Optionen, die der Block kennt.
     *
     * @return list<array{name: string, options: array<string, mixed>}>
     */
    public static function suggestions(string $table): array
    {
        $t = YFormBridge::table($table);
        if (null === $t) {
            return [];
        }
        $img = YFormBridge::field($t, '', [Media::class]);
        $out = [['name' => 'Karten', 'options' => ['variant' => 'cards', 'size' => 'm']]];
        if (null !== $img) {
            $out[] = ['name' => 'Große Karten mit Bild', 'options' => ['variant' => 'cards', 'size' => 'l', 'image_field' => $img->name]];
            $out[] = ['name' => 'Liste mit Bild', 'options' => ['variant' => 'list', 'image_field' => $img->name]];
        } else {
            $out[] = ['name' => 'Liste', 'options' => ['variant' => 'list']];
        }
        $out[] = ['name' => 'Tabelle sortierbar', 'options' => ['variant' => 'table', 'table_sort' => true]];
        if (Knowledge::is($t)) {   // Wissensbereich: Inhaltsverzeichnis mit Kapitelbaum
            array_unshift($out, ['name' => 'Inhaltsverzeichnis', 'options' => ['variant' => 'toc']]);
        }

        return $out;
    }

    /** Vorschläge einmal je Tabelle anlegen (gelöschte kommen nicht wieder) @param class-string<BlockModule> $module */
    public static function seed(string $module, string $table): int
    {
        if ('' === $table || !self::supports($module) || null === YFormBridge::table($table)) {
            return 0;
        }
        $mk = $module::key() . '|' . $table;
        $addon = FluidAddon::instance();
        $done = (array) $addon->getConfig(self::SEEDED, []);
        if (!empty($done[$mk])) {
            return 0;
        }
        $n = 0;
        try {
            if (0 === (int) (Sql::factory()->getArray('SELECT COUNT(*) AS n FROM ' . self::TABLE . ' WHERE block = ? AND table_name = ?', [$module::key(), $table])[0]['n'] ?? 0)) {
                $keys = self::keys($module);
                foreach (self::suggestions($table) as $s) {
                    self::create($module, $table, $s['name'], array_intersect_key($s['options'], array_flip($keys)));
                    ++$n;
                }
            }
        } catch (Throwable) {
            return 0;   // vor migrate
        }
        $done[$mk] = true;
        $addon->setConfig(self::SEEDED, $done);

        return $n;
    }

    // ------------------------------------------------------------------ Seitenleiste

    /** Vorschaubild einer Darstellung als SVG (nur Formen, Farbe aus currentColor; aria-hidden) */
    public static function preview(string $variant): string
    {
        $r = static fn (float $x, float $y, float $w, float $h, string $c = 'b', int $rx = 2) => '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="' . $rx . '" class="ltpl-svg__' . $c . '"/>';
        $s = '';
        switch ($variant) {
            case 'list':
                foreach ([4, 22, 40] as $y) {
                    $s .= $r(4, $y, 18, 14, 'i') . $r(26, $y + 2, 34, 4, 't') . $r(26, $y + 9, 26, 3);
                }
                break;
            case 'table':
                $s .= $r(4, 6, 56, 7, 'i', 1);
                foreach ([17, 27, 37, 47] as $y) {
                    $s .= $r(4, $y, 18, 4, 't', 1) . $r(26, $y, 16, 4, 'b', 1) . $r(46, $y, 14, 4, 'b', 1);
                }
                break;
            case 'toc':
                foreach ([[4, 'c'], [14, 'b'], [22, 'b'], [32, 'c'], [42, 'b'], [50, 'b']] as [$y, $c]) {
                    $s .= 'c' === $c ? $r(4, $y, 6, 6, 'i', 1) . $r(14, $y + 1, 40, 4, 't', 1) : $r(14, $y + 1, 4, 4, 'i', 1) . $r(22, $y + 1, 34, 3, 'b', 1);
                }
                break;
            default:
                foreach ([4, 23, 42] as $x) {
                    $s .= $r($x, 6, 18, 14, 'i') . $r($x, 24, 16, 4, 't') . $r($x, 31, 18, 3) . $r($x, 36, 12, 3);
                }
                foreach ([4, 23, 42] as $x) {
                    $s .= $r($x, 44, 18, 14, 'i');
                }
        }

        return '<svg class="ltpl-svg" viewBox="0 0 64 62" aria-hidden="true" focusable="false">' . $s . '</svg>';
    }

    /**
     * Feld „Vorlage“ (FieldForm, Typ listtemplate): Kacheln mit Vorschaubild je Vorlage aller Tabellen – form.js zeigt die der
     * gewählten Tabelle –, „Eigene Einstellungen“ zuerst. Mit Recht (Perm::DESIGN_FULL) und auf der Website: Als Vorlage
     * speichern, Standard, Umbenennen, Löschen, Überschreiben.
     *
     * @param class-string<BlockModule> $module
     */
    public static function renderInput(string $module, string $name, string $value, string $table, string $label, string $help, bool $backend): string
    {
        $id = 'ltpl-' . substr(md5($name), 0, 8);
        $user = Editing::user() ?? Core::getUser();
        $manage = !$backend && null !== $user && Perm::can($user, Perm::DESIGN_FULL);
        if ('' !== $table) {
            self::seed($module, $table);   // Vorschläge der gewählten Tabelle beim ersten Öffnen
        }
        $variants = $module::variants();
        $tile = static function (string $key, string $tname, string $variant, string $tbl, bool $isDefault, string $opts, bool $locked) use ($name, $value, $id, $variants, $table): string {
            $rid = $id . '-' . substr(md5($tbl . '|' . $key), 0, 8);
            $checked = $key === $value && ('' === $key || $tbl === $table);

            return '<label class="ltpl-tile" for="' . escape($rid) . '"' . ('' !== $tbl ? ' data-table="' . escape($tbl) . '"' : '') . ($isDefault ? ' data-default' : '') . ($locked ? ' data-locked' : '') . '>'
                . '<input type="radio" id="' . escape($rid) . '" name="' . escape($name) . '" value="' . escape($key) . '"' . ($checked ? ' checked' : '')
                . ('' !== $opts ? ' data-options="' . escape($opts) . '"' : '') . '>'
                . ('' === $key ? '<span class="ltpl-tile__own" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" focusable="false"><path fill="currentColor" d="M4 6h10v2H4V6Zm12 0h4v2h-4V6ZM4 11h4v2H4v-2Zm6 0h10v2H10v-2Zm-6 5h12v2H4v-2Zm14 0h2v2h-2v-2Z"/></svg></span>' : self::preview($variant))
                . '<span class="ltpl-tile__name">' . escape($tname) . '</span>'
                . ('' !== $key ? '<span class="ltpl-tile__meta">' . escape((string) ($variants[$variant] ?? $variant)) . ($isDefault ? ' · Standard' : '') . '</span>' : '')
                . '</label>';
        };
        $h = '<fieldset class="ltpl" data-ltpl data-keys="' . escape((string) json_encode(self::keys($module))) . '"' . ($manage ? ' data-manage' : '') . ' data-block="' . escape($module::key()) . '">'
            . '<legend class="rex-field-label">' . escape($label) . '</legend><div class="ltpl-grid">' . $tile('', 'Eigene Einstellungen', '', '', false, '', false);
        foreach (array_keys(YFormBridge::tables()) as $tbl) {
            $std = self::defaultKey($module, $tbl);
            foreach (self::forTable($module, $tbl) as $k => $tpl) {
                $opts = (string) json_encode(self::apply($module, [], $tpl, $tbl), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $h .= $tile($k, $tpl['name'], $tpl['variant'], $tbl, $k === $std, $opts, $tpl['locked']);
            }
        }
        $h .= '</div>';
        if ('' !== $value && '' !== $table && null === self::find($module, $table, $value)) {
            $h .= '<p class="rex-field-note ltpl-warn">Die gewählte Vorlage gibt es nicht mehr – es gelten die eigenen Einstellungen dieses Blocks.</p>';
        }
        $h .= '<div class="ltpl-actions"><button type="button" class="blocks-btn" data-ltpl-detach hidden>Anpassen (Vorlage lösen)</button>';
        if ($manage) {
            $h .= '<details class="ltpl-save"><summary class="blocks-btn">Als Vorlage speichern …</summary><div class="ltpl-save__row">'
                . '<label class="rex-field-label" for="' . $id . '-name">Name der Vorlage</label>'
                . '<input class="rex-input" type="text" id="' . $id . '-name" maxlength="80" placeholder="z. B. Neueste drei" data-ltpl-name>'
                . '<button type="button" class="blocks-btn" data-ltpl-op="save">Speichern</button></div>'
                . '<p class="rex-field-note">Speichert die Darstellung dieses Blocks (Darstellung, Felder, Sortierung, Filter, Breite) als Vorlage der gewählten Tabelle.</p></details>'
                . '<div class="ltpl-manage" data-ltpl-manage hidden>'
                . '<button type="button" class="blocks-btn" data-ltpl-op="default">Als Standard der Tabelle</button>'
                . '<button type="button" class="blocks-btn" data-ltpl-op="overwrite">Mit diesen Einstellungen überschreiben</button>'
                . '<button type="button" class="blocks-btn" data-ltpl-op="rename">Umbenennen</button>'
                . '<button type="button" class="blocks-btn blocks-btn--danger" data-ltpl-op="delete">Löschen</button></div>';
        }

        return $h . '</div>' . ('' !== $help ? '<p class="rex-field-note">' . escape($help) . '</p>' : '') . '</fieldset>';
    }

    /**
     * API des Editors (Api: Aktion list_template): op = save | overwrite | rename | default | delete. Antwort: neues Feld
     * (html) und der gewählte Schlüssel.
     *
     * @return array<string, mixed>
     */
    public static function api(User $user): array
    {
        if (!Perm::can($user, Perm::DESIGN_FULL)) {
            throw new ApiError('Vorlagen verwalten dürfen nur Personen mit dem Recht „Blöcke gestalten und sperren“.', 403);
        }
        $module = BlockModule::classFor(Request::post('block', 'string', ''));
        if (null === $module || !self::supports($module)) {
            throw new ApiError('Dieser Block kennt keine Vorlagen.');
        }
        $table = Request::post('table', 'string', '');
        if (null === YFormBridge::table($table)) {
            throw new ApiError('Bitte zuerst eine Tabelle wählen.');
        }
        $op = Request::post('op', 'string', '');
        $key = Request::post('key', 'string', '');
        $f = Request::post('f', 'array', []);
        $data = is_array($f[1] ?? null) ? Stored::clean($f[1]) : [];
        $tpl = '' !== $key ? self::find($module, $table, $key) : null;
        if ('save' !== $op && (null === $tpl || 'db' !== $tpl['source'])) {
            throw new ApiError('Diese Vorlage lässt sich hier nicht ändern (Vorlagen des Kits sind fest).');
        }
        $msg = match ($op) {
            'save' => (static function () use ($module, $table, $data, &$key): string {
                $key = self::create($module, $table, Request::post('name', 'string', ''), self::optionsFrom($module, $data, $table));

                return 'Als Vorlage gespeichert.';
            })(),
            'overwrite' => (static function () use ($module, $table, $key, $data): string {
                self::update($module, $table, $key, null, self::optionsFrom($module, $data, $table));

                return 'Vorlage überschrieben – gilt überall, wo sie gewählt ist.';
            })(),
            'rename' => (static function () use ($module, $table, $key): string {
                self::update($module, $table, $key, Request::post('name', 'string', ''), null);

                return 'Vorlage umbenannt.';
            })(),
            'default' => (static function () use ($module, $table, $key, $tpl): string {
                self::setDefault($module, $table, !empty($tpl['default']) ? null : $key);

                return !empty($tpl['default']) ? 'Kein Standard mehr für diese Tabelle.' : 'Standard der Tabelle festgelegt.';
            })(),
            'delete' => (static function () use ($module, $table, &$key): string {
                self::delete($module, $table, $key);
                $key = '';

                return 'Vorlage gelöscht. Blöcke, die sie gewählt hatten, zeigen ihre eigenen Einstellungen.';
            })(),
            default => throw new ApiError('Unbekannte Aktion.'),
        };
        $field = array_values(array_filter($module::fields(), static fn (array $x) => 'listtemplate' === ($x['type'] ?? '')))[0] ?? ['name' => 'template', 'label' => 'Vorlage'];

        return ['html' => self::renderInput($module, Request::post('name_attr', 'string', 'f[1][template]'), $key, $table, (string) ($field['label'] ?? 'Vorlage'), (string) ($field['help'] ?? ''), false),
            'key' => $key, 'message' => $msg];
    }
}
