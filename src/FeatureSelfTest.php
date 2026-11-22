<?php

namespace Klxm\Fluid;

use Klxm\Fluid\Data\ListTemplates;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * Selbsttest der Funktionen aus 1.0.0-beta.10 ff. (`console fluid:selftest`): je Gruppe eine Methode, ohne Daten zu ändern.
 *
 *  - Rechte: Sperre und Modulrecht (Lock), Abschnitts-Optionen nur mit Gestaltungsrecht, Migration der Rollen (Perm).
 *  - Listen-Vorlagen: Felder der Vorlage, Anwenden, ohne Vorlage unverändert, fehlende Vorlage, Präfix der YForm-Felder,
 *    Vorlagen aus dem Extension Point.
 */
final class FeatureSelfTest
{
    private int $ok = 0;
    /** @var list<string> */
    private array $fails = [];

    /** @return array<string, callable(): array{ok: int, fails: list<string>}> Gruppen für die Konsole */
    public static function groups(): array
    {
        return [
            'Rechte' => static fn () => (new self())->run('rights'),
            'Listen-Vorlagen' => static fn () => (new self())->run('listTemplates'),
        ];
    }

    /** @return array{ok: int, fails: list<string>} */
    private function run(string $method): array
    {
        try {
            $this->{$method}();
        } catch (\Throwable $e) {
            $this->fails[] = $method . ': ' . $e->getMessage();
        }

        return ['ok' => $this->ok, 'fails' => $this->fails];
    }

    private function eq(string $label, mixed $got, mixed $want): void
    {
        if ($got === $want) {
            ++$this->ok;
        } else {
            $this->fails[] = $label . ': ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' statt ' . json_encode($want, JSON_UNESCAPED_UNICODE);
        }
    }

    // ------------------------------------------------------------------ Rechte

    private function rights(): void
    {
        $this->eq('Ohne Modulrecht', Lock::reasonFor(false, false, true), 'perm');
        $this->eq('Gesperrt ohne Recht', Lock::reasonFor(true, true, false), 'lock');
        $this->eq('Gesperrt mit Recht', Lock::reasonFor(true, true, true), '');
        $this->eq('Frei', Lock::reasonFor(true, false, false), '');
        $stored = ['background' => 'muted', 'spaceTop' => 'large', 'lock' => true];
        $input = ['background' => 'dark', 'spaceTop' => 'none', 'anchor' => 'x', 'col' => 2, 'lock' => '0'];
        $this->eq('Ohne Gestaltung: Abschnitt bleibt, Spalte folgt', Lock::tunesFor(false, false, $stored, $input), ['background' => 'muted', 'spaceTop' => 'large', 'col' => 2, 'lock' => true]);
        $this->eq('Gestaltung Stufe 1: Abschnitt ja, Sperre bleibt', Lock::tunesFor(true, false, $stored, $input), ['background' => 'dark', 'spaceTop' => 'none', 'anchor' => 'x', 'col' => 2, 'lock' => true]);
        $this->eq('Stufe 2 entsperrt', Lock::tunesFor(true, true, $stored, $input), ['background' => 'dark', 'spaceTop' => 'none', 'anchor' => 'x', 'col' => 2]);
        $this->eq('Stufe 2 sperrt', Lock::tunesFor(true, true, [], ['lock' => '1'])['lock'] ?? null, true);
        $this->eq('Kopie ohne Recht entsperrt', Lock::forCopy(null, ['lock' => true, 'background' => 'muted']), ['background' => 'muted']);
        $role = '{"general":null,"options":"|moveSlice[]|","modules":"all"}';
        $once = Perm::withDesign($role);
        $this->eq('Rolle bekommt beide Optionen', null !== $once && str_contains($once, '|moveSlice[]|fluid[design]|fluid[design_full]|'), true);
        $this->eq('Migration idempotent', Perm::withDesign((string) $once), null);
        $this->eq('Leere Optionen', json_decode((string) Perm::withDesign('{"options":null}'), true)['options'] ?? null, '|fluid[design]|fluid[design_full]|');
        $this->eq('Optionen angemeldet', \Redaxo\Core\Security\Permission::has(Perm::DESIGN) && \Redaxo\Core\Security\Permission::has(Perm::DESIGN_FULL), true);
    }

    // ------------------------------------------------------------------ Listen-Vorlagen

    private function listTemplates(): void
    {
        $m = BlockModule::classFor('fluid.datalist');
        if (null === $m) {
            $this->fails[] = 'Block Datenliste fehlt';

            return;
        }
        $keys = ListTemplates::keys($m);
        $this->eq('Darstellung gehört zur Vorlage', \in_array('variant', $keys, true), true);
        $this->eq('Tabelle, Titel, Bedingung und Adressen bleiben beim Block', array_values(array_intersect($keys, ['table', 'title', 'title_field', 'filter_field', 'detail', 'template', 'limit'])), []);
        $data = ['table' => 'rex_x', 'variant' => 'cards', 'size' => 's', 'title' => 'Hallo'];
        $this->eq('Ohne Vorlage unverändert', ListTemplates::resolve($m, $data), $data);
        $this->eq('Fehlende Vorlage: eigene Werte', ListTemplates::resolve($m, $data + ['template' => 'gibt-es-nicht']), $data + ['template' => 'gibt-es-nicht']);
        $tpl = ['variant' => 'table', 'options' => ['table_sort' => true, 'image_field' => 'bild', 'sort_field' => 'rex_x.datum']];
        $out = ListTemplates::apply($m, $data, $tpl, 'rex_x');
        $this->eq('Vorlage legt Darstellung fest', $out['variant'], 'table');
        $this->eq('Option der Vorlage', $out['table_sort'], true);
        $this->eq('Feld ohne Präfix bekommt die Tabelle', $out['image_field'], 'rex_x.bild');
        $this->eq('Feld mit Präfix bleibt', $out['sort_field'], 'rex_x.datum');
        $this->eq('Fehlende Option = Standard des Feldes', $out['size'], 'm');
        $this->eq('Inhalt bleibt beim Block', $out['title'], 'Hallo');
        $opts = ListTemplates::optionsFrom($m, ['variant' => 'list', 'image_field' => 'rex_x.bild', 'title' => 'nicht'], 'rex_x');
        $this->eq('Als Vorlage speichern: Präfix weg, nur Vorlagen-Felder', [$opts['variant'] ?? null, $opts['image_field'] ?? null, isset($opts['title'])], ['list', 'bild', false]);
        // Extension Point: Vorlage einer Erweiterung, nur für ihre Tabelle
        Extension::register('FLUID_LIST_TEMPLATES', static function (ExtensionPoint $ep): array {
            return (array) $ep->subject + ['selbsttest' => ['name' => 'Selbsttest', 'tables' => ['rex_selbsttest'], 'default' => true, 'options' => ['variant' => 'list']]];
        });
        $code = ListTemplates::code($m, 'rex_selbsttest');
        $this->eq('Vorlage aus dem Extension Point', isset($code['ext:selbsttest']) ? [$code['ext:selbsttest']['variant'], $code['ext:selbsttest']['locked']] : null, ['list', true]);
        $this->eq('Nur für ihre Tabelle', isset(ListTemplates::code($m, 'rex_andere')['ext:selbsttest']), false);
        $this->eq('Vorschau je Darstellung', str_contains(ListTemplates::preview('table'), '<svg') && str_contains(ListTemplates::preview('table'), 'aria-hidden="true"'), true);
    }
}
