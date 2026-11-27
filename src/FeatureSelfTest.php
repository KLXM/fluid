<?php

namespace Klxm\Fluid;

use InvalidArgumentException;
use Klxm\Fluid\Data\ListTemplates;
use Klxm\Routes\ExtensionPoint\RedirectMatched;
use Klxm\Routes\ExtensionPoint\ShortlinkAdded;
use Klxm\Routes\ExtensionPoint\ShortlinkDeleted;
use Klxm\Routes\ExtensionPoint\ShortlinkPath;
use Klxm\Routes\Redirects;
use Klxm\Routes\Router;
use Klxm\Routes\Routes;
use Klxm\Routes\Shortlinks;
use Redaxo\Core\Content\Article;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\Permission;
use Throwable;

use function array_slice;
use function count;
use function in_array;

use const JSON_UNESCAPED_UNICODE;

/**
 * Self-test of the features from 1.0.0-beta.10 on (`console fluid:selftest`): one method per group, without changing data.
 *
 *  - Permissions: lock and module permission (Lock), section options only with the design permission, role migration (Perm).
 *  - List templates: template fields, applying, unchanged without template, missing template, prefix of the YForm fields,
 *    templates from the extension point.
 *  - Menu: settings „Einträge im Menü“, without a setting the menu stays unchanged.
 *  - Glossary: marking (first occurrence, not in links, buttons, headings, data-glossary="off", idempotent), spellings,
 *    short text.
 *  - Knowledge: chapter tree (numbers, custom numbers, reading order, loops, missing parent chapters), previous/next, sections.
 *
 * @internal
 */
final class FeatureSelfTest
{
    private int $ok = 0;
    /** @var list<string> */
    private array $fails = [];

    /** @return array<string, callable(): array{ok: int, fails: list<string>}> groups for the console */
    public static function groups(): array
    {
        return [
            'Permissions' => static fn () => new self()->run('rights'),
            'List templates' => static fn () => new self()->run('listTemplates'),
            'Menu' => static fn () => new self()->run('menu'),
            'Glossary' => static fn () => new self()->run('glossary'),
            'Knowledge' => static fn () => new self()->run('knowledge'),
            'URLs' => static fn () => new self()->run('addresses'),
            'Short links' => static fn () => new self()->run('shortlinks'),
            'Template head' => static fn () => new self()->run('kitHead'),
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
            $this->fails[] = $label . ': ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' instead of ' . json_encode($want, JSON_UNESCAPED_UNICODE);
        }
    }

    // ------------------------------------------------------------------ Permissions

    private function rights(): void
    {
        $this->eq('Without module permission', Lock::reasonFor(false, false, true), 'perm');
        $this->eq('Locked without permission', Lock::reasonFor(true, true, false), 'lock');
        $this->eq('Locked with permission', Lock::reasonFor(true, true, true), '');
        $this->eq('Unlocked', Lock::reasonFor(true, false, false), '');
        $stored = ['background' => 'muted', 'spaceTop' => 'large', 'lock' => true];
        $input = ['background' => 'dark', 'spaceTop' => 'none', 'anchor' => 'x', 'col' => 2, 'lock' => '0'];
        $this->eq('Without design: section stays, column follows', Lock::tunesFor(false, false, $stored, $input), ['background' => 'muted', 'spaceTop' => 'large', 'col' => 2, 'lock' => true]);
        $this->eq('Design level 1: section yes, lock stays', Lock::tunesFor(true, false, $stored, $input), ['background' => 'dark', 'spaceTop' => 'none', 'anchor' => 'x', 'col' => 2, 'lock' => true]);
        $this->eq('Level 2 unlocks', Lock::tunesFor(true, true, $stored, $input), ['background' => 'dark', 'spaceTop' => 'none', 'anchor' => 'x', 'col' => 2]);
        $this->eq('Level 2 locks', Lock::tunesFor(true, true, [], ['lock' => '1'])['lock'] ?? null, true);
        $this->eq('Copy without permission is unlocked', Lock::forCopy(null, ['lock' => true, 'background' => 'muted']), ['background' => 'muted']);
        $role = '{"general":null,"options":"|moveSlice[]|","modules":"all"}';
        $once = Perm::withDesign($role);
        $this->eq('Role gets both options', null !== $once && str_contains($once, '|moveSlice[]|fluid[design]|fluid[design_full]|'), true);
        $this->eq('Migration idempotent', Perm::withDesign((string) $once), null);
        $this->eq('Empty options', json_decode((string) Perm::withDesign('{"options":null}'), true)['options'] ?? null, '|fluid[design]|fluid[design_full]|');
        $this->eq('Options registered', Permission::has(Perm::DESIGN) && Permission::has(Perm::DESIGN_FULL), true);
    }

    // ------------------------------------------------------------------ List templates

    private function listTemplates(): void
    {
        $m = BlockModule::classFor('fluid.datalist');
        if (null === $m) {
            $this->fails[] = 'Block data list missing';

            return;
        }
        $keys = ListTemplates::keys($m);
        $this->eq('Display belongs to the template', in_array('variant', $keys, true), true);
        $this->eq('Table, title, condition and URLs stay with the block', array_values(array_intersect($keys, ['table', 'title', 'title_field', 'filter_field', 'detail', 'template', 'limit'])), []);
        $data = ['table' => 'rex_x', 'variant' => 'cards', 'size' => 's', 'title' => 'Hallo'];
        $this->eq('Unchanged without template', ListTemplates::resolve($m, $data), $data);
        $this->eq('Missing template: own values', ListTemplates::resolve($m, $data + ['template' => 'gibt-es-nicht']), $data + ['template' => 'gibt-es-nicht']);
        $tpl = ['variant' => 'table', 'options' => ['table_sort' => true, 'image_field' => 'bild', 'sort_field' => 'rex_x.datum']];
        $out = ListTemplates::apply($m, $data, $tpl, 'rex_x');
        $this->eq('Template sets the display', $out['variant'], 'table');
        $this->eq('Template option', $out['table_sort'], true);
        $this->eq('Field without prefix gets the table', $out['image_field'], 'rex_x.bild');
        $this->eq('Field with prefix stays', $out['sort_field'], 'rex_x.datum');
        $this->eq('Missing option = field default', $out['size'], 'm');
        $this->eq('Content stays with the block', $out['title'], 'Hallo');
        $opts = ListTemplates::optionsFrom($m, ['variant' => 'list', 'image_field' => 'rex_x.bild', 'title' => 'nicht'], 'rex_x');
        $this->eq('Save as template: prefix removed, template fields only', [$opts['variant'] ?? null, $opts['image_field'] ?? null, isset($opts['title'])], ['list', 'bild', false]);
        // Extension point: an extension's template, only for its table
        Extension::register('FLUID_LIST_TEMPLATES', static function (ExtensionPoint $ep): array {
            return (array) $ep->subject + ['selbsttest' => ['name' => 'Selbsttest', 'tables' => ['rex_selbsttest'], 'default' => true, 'options' => ['variant' => 'list']]];
        });
        $code = ListTemplates::code($m, 'rex_selbsttest');
        $this->eq('Template from the extension point', isset($code['ext:selbsttest']) ? [$code['ext:selbsttest']['variant'], $code['ext:selbsttest']['locked']] : null, ['list', true]);
        $this->eq('Only for its table', isset(ListTemplates::code($m, 'rex_andere')['ext:selbsttest']), false);
        $this->eq('Preview per display', str_contains(ListTemplates::preview('table'), '<svg') && str_contains(ListTemplates::preview('table'), 'aria-hidden="true"'), true);
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
        $this->eq('Page target with language', Redirects::parseTarget('redaxo://5@2#top'), ['type' => 'page', 'id' => '5', 'source' => '', 'lang' => 2, 'anchor' => '#top']);
        $this->eq('Page target without language', Redirects::parseTarget('redaxo://5'), ['type' => 'page', 'id' => '5', 'source' => '', 'lang' => null, 'anchor' => '']);
        $this->eq('Entry target', Redirects::parseTarget('route://news/12@1'), ['type' => 'entry', 'id' => '12', 'source' => 'news', 'lang' => 1, 'anchor' => '']);
        $this->eq('Start language without prefix', Router::splitLanguage('/a/b/'), [Language::getStartId(), 'a/b']);
        foreach (Language::getAll() as $l) {
            if ($l->id !== Language::getStartId()) {
                $this->eq('Language prefix ' . $l->code, Router::splitLanguage('/' . strtoupper($l->code) . '/a'), [$l->id, 'a']);
            }
        }
        $this->eq('Slug with umlauts and accents', Routes::slugify('Café Müller – Łódź'), 'cafe-mueller-lodz');
        $this->eq('Own address with slash', Routes::slugifyPath(' Angebot / Sommer 2027 /'), 'angebot/sommer-2027');
        $this->eq('Choices in the site panel', substr_count(Seo\Addresses::render(), 'name="ending"'), count(Seo\UrlEnding::cases()));
    }

    // ------------------------------------------------------------------ KitHead (own templates)

    private function kitHead(): void
    {
        $marker = KitHead::render();
        $html = '<html><head>' . $marker . '</head><body>' . $marker . '</body></html>';
        $out = KitHead::replace($html);
        $this->eq('Marker replaced once (first one)', [substr_count($out, $marker), str_contains($out, '</head><body>' . $marker)], [1, true]);
        $attrs = KitHead::htmlAttributes();
        $this->eq('html attributes', [str_starts_with($attrs, 'class="no-js '), str_contains($attrs, 'data-fluid-classes="')], [true, true]);
        $this->eq('Native kit class', Kit::get('native')?->htmlClass() ?? 'kit-native', 'kit-native');
    }

    // ------------------------------------------------------------------ Shortlinks (with their extension points)

    private function shortlinks(): void
    {
        $home = Article::getSiteStartArticleId();
        $target = 'redaxo://' . $home . '@' . Language::getStartId();
        $events = [];
        $suppress = false;
        Extension::register(ShortlinkPath::class, static fn (ShortlinkPath $ep): string => '/selftest' . $ep->subject);
        Extension::register(ShortlinkAdded::class, static function (ShortlinkAdded $ep) use (&$events): void {
            $events[] = 'added ' . $ep->shortlink->path;
        });
        Extension::register(ShortlinkDeleted::class, static function (ShortlinkDeleted $ep) use (&$events): void {
            $events[] = 'deleted ' . $ep->shortlink->path;
        });
        Extension::register(RedirectMatched::class, static function (RedirectMatched $ep) use (&$suppress): ?string {
            return $suppress && 'short' === $ep->rule['origin'] ? '' : null;
        });
        $link = Shortlinks::create('Probe Link', $target);
        try {
            $this->eq('Path from the extension point', $link->path, '/selftest/probe-link');
            $this->eq('Taken address rejected', (static function () use ($target): bool {
                try {
                    Shortlinks::create('probe-link', $target);
                } catch (InvalidArgumentException) {
                    return true;
                }

                return false;
            })(), true);
            $match = Redirects::match($link->path);
            $this->eq('Leads to the page with 302', [$match['code'] ?? null, $match['location'] ?? null], [302, Url::article($home)]);
            $suppress = true;
            $this->eq('RedirectMatched can suppress', Redirects::match($link->path), null);
        } finally {
            Shortlinks::delete($link->id, $target);
        }
        $this->eq('Events', $events, ['added /selftest/probe-link', 'deleted /selftest/probe-link']);
        $this->eq('Deleted', Redirects::exists($link->path), false);
    }

    // ------------------------------------------------------------------ Menu

    private function menu(): void
    {
        $this->eq('Off by default', Data\MenuEntries::config(null), ['mode' => '', 'limit' => 12]);
        $this->eq('Sanitising', Data\MenuEntries::config(['menu_entries' => 'quatsch', 'menu_limit' => 999]), ['mode' => '', 'limit' => 50]);
        $this->eq('Two levels', Data\MenuEntries::config(['menu_entries' => 'two', 'menu_limit' => '5']), ['mode' => 'two', 'limit' => 5]);
        $this->eq('Unchanged without setting', Data\MenuEntries::extend([['id' => 0, 'label' => 'A', 'href' => '/a', 'current' => false, 'active' => false, 'children' => []]], 1),
            [['id' => 0, 'label' => 'A', 'href' => '/a', 'current' => false, 'active' => false, 'children' => []]]);
    }

    // ------------------------------------------------------------------ Glossary

    private function glossary(): void
    {
        $terms = [['id' => 1, 'key' => 'spf', 'term' => 'SPF', 'short' => 'Absender-Prüfung.', 'variants' => [], 'url' => '/glossar/spf'],
            ['id' => 2, 'key' => 'barrierefreiheit', 'term' => 'Barrierefreiheit', 'short' => 'Zugang für alle.', 'variants' => ['barrierefrei'], 'url' => null]];
        $a = new Glossary\Annotator($terms, ['lang' => 'de']);
        $html = '<body><main><h2>SPF erklärt</h2><p>Mit SPF und SPF.</p><p><a href="/x">SPF</a> und <button>Barrierefreiheit</button></p><p>Barrierefreiheit hilft.</p></main></body>';
        $out = $a->annotate($html);
        $this->eq('Two terms marked', $a->count, 2);
        $this->eq('Not in headings', str_contains($out, '<h2>SPF erklärt</h2>'), true);
        $this->eq('Not in links and buttons', str_contains($out, '<a href="/x">SPF</a>') && str_contains($out, '<button>Barrierefreiheit</button>'), true);
        $this->eq('Only the first occurrence', substr_count($out, 'class="gl-term"'), 2);
        $this->eq('Popover with link to the detail page', str_contains($out, 'popovertarget="gl-1"') && str_contains($out, 'href="/glossar/spf"'), true);
        $this->eq('Applying twice changes nothing', new Glossary\Annotator($terms, ['lang' => 'de'])->annotate($out), $out);
        $this->eq('Opt-out data-glossary="off"', new Glossary\Annotator($terms)->annotate('<body><main><p data-glossary="off">SPF</p></main></body>'), '<body><main><p data-glossary="off">SPF</p></main></body>');
        $this->eq('Spellings', Glossary\Glossary::splitVariants("CDN, CDNs\nContent Delivery Network;CDN"), ['CDN', 'CDNs', 'Content Delivery Network']);
        $this->eq('Short text truncated', mb_strlen(Glossary\Glossary::short(str_repeat('Wort ', 80))) <= Glossary\Glossary::SHORT_MAX, true);
    }

    // ------------------------------------------------------------------ Knowledge

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
        $this->eq('Automatic numbers', [$num(1), $num(2), $num(4), $num(3), $num(5)], ['1', '2', '2.1', '2.2', '2.1.1']);
        $this->eq('Number can be overridden, children follow', [$num(6), $num(7)], ['A', 'A.1']);
        $this->eq('Reading order', array_slice($tree['order'], 0, 5), [1, 2, 4, 5, 3]);
        $this->eq('Missing parent chapter = top level', in_array(10, $tree['roots'], true), true);
        $this->eq('Loop does not break', count($tree['order']), 10);
        $this->eq('Parent chapters', array_column(Data\Knowledge::ancestors($tree, 5), 'id'), [2, 4]);
        [$prev, $next] = Data\Knowledge::neighbours($tree, 4);
        $this->eq('Previous/next', [$prev['id'] ?? null, $next['id'] ?? null], [2, 5]);
        [$html, $secs] = Data\Knowledge::sections('<h2>Erster Teil</h2><p>x</p><h2 id="da">Zweiter</h2>');
        $this->eq('Sections with anchors', [$secs, str_contains($html, 'id="kn-s-1"')], [[['id' => 'kn-s-1', 'title' => 'Erster Teil'], ['id' => 'da', 'title' => 'Zweiter']], true]);
    }
}
