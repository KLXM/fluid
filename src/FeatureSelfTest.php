<?php

namespace Klxm\Fluid;

use InvalidArgumentException;
use Klxm\Fluid\Data\ListTemplates;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\Permission;
use Throwable;

use function array_slice;
use function count;
use function in_array;

use const JSON_UNESCAPED_UNICODE;

/**
 * Selbsttest der Funktionen aus 1.0.0-beta.10 ff. (`console fluid:selftest`): je Gruppe eine Methode, ohne Daten zu ändern.
 *
 *  - Rechte: Sperre und Modulrecht (Lock), Abschnitts-Optionen nur mit Gestaltungsrecht, Migration der Rollen (Perm).
 *  - Listen-Vorlagen: Felder der Vorlage, Anwenden, ohne Vorlage unverändert, fehlende Vorlage, Präfix der YForm-Felder,
 *    Vorlagen aus dem Extension Point.
 *  - Menü: Einstellungen „Einträge im Menü“, ohne Einstellung bleibt das Menü unverändert.
 *  - Glossar: Markieren (erstes Vorkommen, nicht in Links, Knöpfen, Überschriften, data-glossary="off", idempotent), Schreibweisen,
 *    Kurztext.
 *  - Wissen: Kapitelbaum (Nummern, eigene Nummern, Lesereihenfolge, Schleifen, fehlende Oberkapitel), Vor/Zurück, Abschnitte.
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
            'Rechte' => static fn () => new self()->run('rights'),
            'Listen-Vorlagen' => static fn () => new self()->run('listTemplates'),
            'Menü' => static fn () => new self()->run('menu'),
            'Glossar' => static fn () => new self()->run('glossary'),
            'Wissen' => static fn () => new self()->run('knowledge'),
            'Adressen' => static fn () => new self()->run('addresses'),
        ];
    }

    /** @return array{ok: int, fails: list<string>} */
    private function run(string $method): array
    {
        try {
            $this->{$method}();
        } catch (Throwable $e) {
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
        $this->eq('Optionen angemeldet', Permission::has(Perm::DESIGN) && Permission::has(Perm::DESIGN_FULL), true);
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
        $this->eq('Darstellung gehört zur Vorlage', in_array('variant', $keys, true), true);
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

    // ------------------------------------------------------------------ Addresses

    private function addresses(): void
    {
        $mixed = Seo\UrlEnding::Mixed->format();
        $this->eq('Ending mixed', [$mixed->append('a', true), $mixed->append('a/b')], ['a/', 'a/b.html']);
        $this->eq('Custom ending', Seo\UrlEnding::Custom->format('.htm')->append('a'), 'a.htm');
        $this->eq('Invalid custom ending', (static function (): bool {
            try {
                Seo\UrlEnding::Custom->format('htm!');
            } catch (InvalidArgumentException) {
                return true;
            }

            return false;
        })(), true);
        $this->eq('Page target with language', Routing\Redirects::parseTarget('redaxo://5@2#top'), ['type' => 'page', 'id' => '5', 'source' => '', 'lang' => 2, 'anchor' => '#top']);
        $this->eq('Page target without language', Routing\Redirects::parseTarget('redaxo://5'), ['type' => 'page', 'id' => '5', 'source' => '', 'lang' => null, 'anchor' => '']);
        $this->eq('Entry target', Routing\Redirects::parseTarget('route://news/12@1'), ['type' => 'entry', 'id' => '12', 'source' => 'news', 'lang' => 1, 'anchor' => '']);
        $this->eq('Start language without prefix', Routing\Routes::splitLanguage('/a/b/'), [Language::getStartId(), 'a/b']);
        foreach (Language::getAll() as $l) {
            if ($l->id !== Language::getStartId()) {
                $this->eq('Language prefix ' . $l->code, Routing\Routes::splitLanguage('/' . strtoupper($l->code) . '/a'), [$l->id, 'a']);
            }
        }
        $this->eq('Slug with umlauts and accents', Routing\Routes::slugify('Café Müller – Łódź'), 'cafe-mueller-lodz');
        $this->eq('Own address with slash', Routing\Routes::slugifyPath(' Angebot / Sommer 2027 /'), 'angebot/sommer-2027');
        $this->eq('Choices in the site panel', substr_count(Seo\Addresses::render(), 'name="ending"'), count(Seo\UrlEnding::cases()));
    }

    // ------------------------------------------------------------------ Menü

    private function menu(): void
    {
        $this->eq('Standard aus', Data\MenuEntries::config(null), ['mode' => '', 'limit' => 12]);
        $this->eq('Bereinigung', Data\MenuEntries::config(['menu_entries' => 'quatsch', 'menu_limit' => 999]), ['mode' => '', 'limit' => 50]);
        $this->eq('Zwei Ebenen', Data\MenuEntries::config(['menu_entries' => 'two', 'menu_limit' => '5']), ['mode' => 'two', 'limit' => 5]);
        $this->eq('Ohne Einstellung unverändert', Data\MenuEntries::extend([['id' => 0, 'label' => 'A', 'href' => '/a', 'current' => false, 'active' => false, 'children' => []]], 1),
            [['id' => 0, 'label' => 'A', 'href' => '/a', 'current' => false, 'active' => false, 'children' => []]]);
    }

    // ------------------------------------------------------------------ Glossar

    private function glossary(): void
    {
        $terms = [['id' => 1, 'key' => 'spf', 'term' => 'SPF', 'short' => 'Absender-Prüfung.', 'variants' => [], 'url' => '/glossar/spf'],
            ['id' => 2, 'key' => 'barrierefreiheit', 'term' => 'Barrierefreiheit', 'short' => 'Zugang für alle.', 'variants' => ['barrierefrei'], 'url' => null]];
        $a = new Glossary\Annotator($terms, ['lang' => 'de']);
        $html = '<body><main><h2>SPF erklärt</h2><p>Mit SPF und SPF.</p><p><a href="/x">SPF</a> und <button>Barrierefreiheit</button></p><p>Barrierefreiheit hilft.</p></main></body>';
        $out = $a->annotate($html);
        $this->eq('Zwei Begriffe markiert', $a->count, 2);
        $this->eq('Nicht in Überschriften', str_contains($out, '<h2>SPF erklärt</h2>'), true);
        $this->eq('Nicht in Links und Knöpfen', str_contains($out, '<a href="/x">SPF</a>') && str_contains($out, '<button>Barrierefreiheit</button>'), true);
        $this->eq('Nur das erste Vorkommen', substr_count($out, 'class="gl-term"'), 2);
        $this->eq('Popover mit Link zur Detailseite', str_contains($out, 'popovertarget="gl-1"') && str_contains($out, 'href="/glossar/spf"'), true);
        $this->eq('Zweimal anwenden ändert nichts', new Glossary\Annotator($terms, ['lang' => 'de'])->annotate($out), $out);
        $this->eq('Ausnahme data-glossary="off"', new Glossary\Annotator($terms)->annotate('<body><main><p data-glossary="off">SPF</p></main></body>'), '<body><main><p data-glossary="off">SPF</p></main></body>');
        $this->eq('Schreibweisen', Glossary\Glossary::splitVariants("CDN, CDNs\nContent Delivery Network;CDN"), ['CDN', 'CDNs', 'Content Delivery Network']);
        $this->eq('Kurztext gekürzt', mb_strlen(Glossary\Glossary::short(str_repeat('Wort ', 80))) <= Glossary\Glossary::SHORT_MAX, true);
    }

    // ------------------------------------------------------------------ Wissen

    private function knowledge(): void
    {
        $rows = [
            ['id' => 1, 'parent' => 0, 'title' => 'Einleitung', 'number' => '', 'sort' => 1, 'area' => ''],
            ['id' => 2, 'parent' => 0, 'title' => 'Praxis', 'number' => '', 'sort' => 2, 'area' => ''],
            ['id' => 3, 'parent' => 2, 'title' => 'B-Teil', 'number' => '', 'sort' => 0, 'area' => ''],
            ['id' => 4, 'parent' => 2, 'title' => 'A-Teil', 'number' => '', 'sort' => 0, 'area' => ''],
            ['id' => 5, 'parent' => 4, 'title' => 'Tief', 'number' => '', 'sort' => 0, 'area' => ''],
            ['id' => 6, 'parent' => 0, 'title' => 'Anhang', 'number' => 'A', 'sort' => 3, 'area' => ''],
            ['id' => 7, 'parent' => 6, 'title' => 'Liste', 'number' => '', 'sort' => 0, 'area' => ''],
            ['id' => 8, 'parent' => 9, 'title' => 'Schleife 1', 'number' => '', 'sort' => 9, 'area' => ''],
            ['id' => 9, 'parent' => 8, 'title' => 'Schleife 2', 'number' => '', 'sort' => 9, 'area' => ''],
            ['id' => 10, 'parent' => 99, 'title' => 'Ohne Oberkapitel', 'number' => '', 'sort' => 8, 'area' => ''],
        ];
        $tree = Data\Knowledge::build($rows);
        $num = static fn (int $id) => $tree['nodes'][$id]['number'];
        $this->eq('Nummern automatisch', [$num(1), $num(2), $num(4), $num(3), $num(5)], ['1', '2', '2.1', '2.2', '2.1.1']);
        $this->eq('Nummer überschreibbar, Kinder folgen', [$num(6), $num(7)], ['A', 'A.1']);
        $this->eq('Lesereihenfolge', array_slice($tree['order'], 0, 5), [1, 2, 4, 5, 3]);
        $this->eq('Fehlendes Oberkapitel = oberste Ebene', in_array(10, $tree['roots'], true), true);
        $this->eq('Schleife bricht nicht', count($tree['order']), 10);
        $this->eq('Oberkapitel', array_column(Data\Knowledge::ancestors($tree, 5), 'id'), [2, 4]);
        [$prev, $next] = Data\Knowledge::neighbours($tree, 4);
        $this->eq('Vor/Zurück', [$prev['id'] ?? null, $next['id'] ?? null], [2, 5]);
        [$html, $secs] = Data\Knowledge::sections('<h2>Erster Teil</h2><p>x</p><h2 id="da">Zweiter</h2>');
        $this->eq('Abschnitte mit Anker', [$secs, str_contains($html, 'id="kn-s-1"')], [[['id' => 'kn-s-1', 'title' => 'Erster Teil'], ['id' => 'da', 'title' => 'Zweiter']], true]);
    }
}
