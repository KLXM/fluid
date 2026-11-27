<?php

namespace Klxm\Fluid\Mcp;

use Klxm\Fluid\Activity;
use Klxm\Fluid\ApiError;
use Klxm\Fluid\BlockData;
use Klxm\Fluid\Blog\Blog;
use Klxm\Fluid\Data\FluidBlocks;
use Klxm\Fluid\Data\YFormBridge;
use Klxm\Fluid\Search\Search;
use Klxm\Fluid\Settings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;
use ReflectionClass;
use Yakamara\YForm\Data\Record;
use Yakamara\YForm\Table\Table;
use Yakamara\YForm\Validation\ValidationException;

use function array_key_exists;
use function is_array;

/**
 * Tools of the MCP server. Each tool states whether it writes (write) and whether it may be public (public).
 * The list depends on the access: public only what is released, key „read“ without writing tools.
 */
final class Tools
{
    private readonly Content $content;

    public function __construct(
        private readonly Access $access,
    ) {
        $this->content = new Content($access);
    }

    /** @return array<string, array<string, mixed>> name => definition (with run) */
    public function all(): array
    {
        $cfg = Access::config();
        $str = static fn (string $d) => ['type' => 'string', 'description' => $d];
        $int = static fn (string $d) => ['type' => 'integer', 'description' => $d];
        $bool = static fn (string $d) => ['type' => 'boolean', 'description' => $d];
        $obj = static fn (string $d) => ['type' => 'object', 'description' => $d, 'additionalProperties' => true];
        $lang = $str('Language (code, e.g. de, en) – default: start language');
        $page = ['description' => 'Page: ID, path (e.g. /contact) or "home"', 'type' => ['integer', 'string']];
        $schema = static fn (array $props, array $required = []) => ['type' => 'object', 'properties' => (object) $props] + ($required ? ['required' => $required] : []);
        $ro = ['readOnlyHint' => true, 'openWorldHint' => false];
        $rw = ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false];
        $del = ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false];
        $public = $this->access->isPublic();
        $c = $this->content;

        $t = [];
        $add = function (string $name, string $title, string $desc, array $input, array $ann, bool $write, bool $allowPublic, callable $run) use (&$t, $public): void {
            if (($write && !$this->access->write) || ($public && !$allowPublic)) {
                return;
            }
            $t[$name] = ['name' => $name, 'title' => $title, 'description' => $desc, 'inputSchema' => $input, 'annotations' => $ann + ['title' => $title], 'run' => $run];
        };

        $add('site_overview', 'Website overview', 'Name, address, languages, blogs, data tables and what this access may do. Start here.',
            $schema([]), $ro, false, true, fn () => $this->overview());

        $add('list_pages', 'Page tree', 'All pages as a tree (menu items with subpages)' . ($public ? ', published only.' : ' – including offline pages.'),
            $schema(['lang' => $lang]), $ro, false, (bool) $cfg['public_pages'], static fn (array $a) => ['pages' => $c->tree($c->language($a['lang'] ?? null))]);

        $add('get_page', 'Read page', 'Page with all blocks (type, fields as JSON). ' . ($public ? 'Published version.' : 'Default: draft – version "live" shows the published version.'),
            $schema(['page' => $page, 'lang' => $lang] + ($public ? [] : ['version' => ['type' => 'string', 'enum' => ['draft', 'live'], 'description' => 'draft (default) or live']]), ['page']),
            $ro, false, (bool) $cfg['public_pages'], static function (array $a) use ($c) {
                $art = $c->article($a['page'] ?? null, $c->language($a['lang'] ?? null));

                return $c->pageInfo($art) + ['blocks' => $c->blocks($art, 'live' === ($a['version'] ?? 'draft'))];
            });

        $add('search', 'Search', 'Full-text search across the published pages (like the website search).',
            $schema(['query' => $str('Search term'), 'lang' => $lang, 'limit' => $int('At most this many hits (default 10, max. 50)')], ['query']),
            $ro, false, (bool) $cfg['public_search'], static function (array $a) use ($c) {
                $res = Search::search((string) ($a['query'] ?? ''), $c->language($a['lang'] ?? null), (int) ($a['limit'] ?? 10));
                foreach ($res['hits'] as &$h) {
                    $h['url'] = Content::abs($h['url']);
                    // Website highlighting (<mark>) as **…** – tools read text
                    $h['title'] = html_entity_decode(strip_tags(str_replace(['<mark>', '</mark>'], '**', $h['title'])));
                    $h['excerpt'] = html_entity_decode(strip_tags(str_replace(['<mark>', '</mark>'], '**', $h['excerpt'])));
                }
                unset($h);

                return $res;
            });

        $add('list_posts', 'Blog posts', 'Posts of a blog (newest first) with date, author, tags. Blogs are listed in site_overview.',
            $schema(['blog' => $int('ID of the blog'), 'lang' => $lang, 'limit' => $int('Number (default 20)')], ['blog']),
            $ro, false, (bool) $cfg['public_blog'], fn (array $a) => $this->posts((int) ($a['blog'] ?? 0), $c->language($a['lang'] ?? null), (int) ($a['limit'] ?? 20)));

        $add('list_block_types', 'Block types', 'Block types with their fields (name, type, required, options). Read before add_block/update_block.',
            $schema(['type' => $str('Only this block type')]), $ro, false, false, static fn (array $a) => ['block_types' => $c->blockTypes(isset($a['type']) ? (string) $a['type'] : null)]);

        if (YFormBridge::available() && [] !== $this->access->tables()) {
            $tableProp = ['type' => 'string', 'enum' => $this->access->tables(), 'description' => 'Table'];
            $add('list_entries', 'Entries of a data table', 'Entries of an enabled YForm table, paginated. Fields as readable text.',
                $schema(['table' => $tableProp, 'page' => $int('Page (from 1)'), 'per_page' => $int('Entries per page (default 20, max. 100)')], ['table']),
                $ro, false, true, fn (array $a) => $this->entries((string) ($a['table'] ?? ''), (int) ($a['page'] ?? 1), (int) ($a['per_page'] ?? 20)));
            $add('get_entry', 'Read entry', 'One entry of an enabled YForm table.',
                $schema(['table' => $tableProp, 'id' => $int('ID of the entry')], ['table', 'id']),
                $ro, false, true, fn (array $a) => $this->entry((string) ($a['table'] ?? ''), (int) ($a['id'] ?? 0)));
            $add('save_entry', 'Create or change entry', 'Creates an entry (without id) or changes it (with id). values = field name → value; YForm validates the input.',
                $schema(['table' => $tableProp, 'id' => $int('ID to change, empty = new'), 'values' => $obj('Field name → value')], ['table', 'values']),
                $rw, true, false, fn (array $a) => $this->saveEntry((string) ($a['table'] ?? ''), (int) ($a['id'] ?? 0), is_array($a['values'] ?? null) ? $a['values'] : []));
        }

        $add('update_block', 'Change block', 'Change fields of a block (in the draft). data = only the fields to change; replace=true replaces all fields.',
            $schema(['page' => $page, 'block' => $int('ID of the block (from get_page)'), 'data' => $obj('Field name → value'), 'replace' => $bool('Replace all fields instead of merging'), 'lang' => $lang], ['page', 'block', 'data']),
            $rw, true, false, static function (array $a) use ($c) {
                $art = $c->article($a['page'] ?? null, $c->language($a['lang'] ?? null));

                return $c->updateBlock($art, (int) ($a['block'] ?? 0), is_array($a['data'] ?? null) ? $a['data'] : [], (bool) ($a['replace'] ?? false));
            });

        $add('add_block', 'Insert block', 'Insert a new block (in the draft). after = block ID it follows (0 = at the top, omit = at the end).',
            $schema(['page' => $page, 'type' => $str('Block type (key from list_block_types)'), 'data' => $obj('Field name → value'), 'after' => $int('Block ID; 0 = top'), 'lang' => $lang], ['page', 'type']),
            $rw, true, false, static function (array $a) use ($c) {
                $art = $c->article($a['page'] ?? null, $c->language($a['lang'] ?? null));

                return $c->addBlock($art, (string) ($a['type'] ?? ''), is_array($a['data'] ?? null) ? $a['data'] : [], isset($a['after']) ? (int) $a['after'] : -1);
            });

        $add('move_block', 'Move block', 'Place a section before another block (before = block ID, 0 = at the end). Not for blocks in layout columns.',
            $schema(['page' => $page, 'block' => $int('ID of the block'), 'before' => $int('before this block; 0 = at the end'), 'lang' => $lang], ['page', 'block', 'before']),
            $rw, true, false, static function (array $a) use ($c) {
                $art = $c->article($a['page'] ?? null, $c->language($a['lang'] ?? null));

                return $c->moveBlock($art, (int) ($a['block'] ?? 0), (int) ($a['before'] ?? 0));
            });

        $add('set_block_online', 'Switch block on/off', 'Switch a block online or offline (in the draft).',
            $schema(['page' => $page, 'block' => $int('ID of the block'), 'online' => $bool('true = online'), 'lang' => $lang], ['page', 'block', 'online']),
            $rw, true, false, static function (array $a) use ($c) {
                $art = $c->article($a['page'] ?? null, $c->language($a['lang'] ?? null));

                return $c->setOnline($art, (int) ($a['block'] ?? 0), (bool) ($a['online'] ?? true));
            });

        $add('delete_block', 'Delete block', 'Delete a block (in the draft; a layout including its columns). The previous state remains as a version.',
            $schema(['page' => $page, 'block' => $int('ID of the block'), 'confirm' => $bool('Must be true'), 'lang' => $lang], ['page', 'block', 'confirm']),
            $del, true, false, static function (array $a) use ($c) {
                if (true !== ($a['confirm'] ?? false)) {
                    throw new ApiError('Set confirm: true to delete.');
                }
                $art = $c->article($a['page'] ?? null, $c->language($a['lang'] ?? null));

                return $c->deleteBlock($art, (int) ($a['block'] ?? 0));
            });

        $add('create_page', 'Create page', 'New page (offline). parent = ID of a menu item (0 = top level); menu=true creates a menu item. In a blog = new post.',
            $schema(['name' => $str('Title'), 'parent' => $int('Menu item (category ID), 0 = top level'), 'menu' => $bool('As menu item'), 'template' => $int('ID of a page template (optional)'), 'lang' => $lang], ['name']),
            $rw, true, false, static fn (array $a) => $c->createPage((string) ($a['name'] ?? ''), (int) ($a['parent'] ?? 0), (bool) ($a['menu'] ?? false), $c->language($a['lang'] ?? null), (int) ($a['template'] ?? 0)));

        if ($this->access->publish) {
            $add('publish_page', 'Publish page', 'Publish the draft of a page (live). Agree with the human first.',
                $schema(['page' => $page, 'lang' => $lang], ['page']), $rw, true, false, static function (array $a) use ($c) {
                    return $c->publish($c->article($a['page'] ?? null, $c->language($a['lang'] ?? null)));
                });
        }

        return $t;
    }

    /** @return array<string, mixed> */
    private function overview(): array
    {
        $cfg = Access::config();
        $langs = [];
        foreach (Language::getAll() as $l) {
            $langs[] = ['code' => $l->code, 'name' => $l->name, 'start' => $l->id === Language::getStartId()];
        }
        $blogs = [];
        if (!$this->access->isPublic() || $cfg['public_blog']) {
            foreach (Blog::all() as $id => $name) {
                $blogs[] = ['id' => $id, 'name' => $name];
            }
        }

        return array_filter([
            'name' => (string) (Settings::get('org_name') ?: Core::getProject()->instanceName),
            'url' => Url::absoluteBase(),
            'home' => Article::getSiteStartArticleId(),
            'languages' => $langs,
            'blogs' => $blogs,
            'tables' => $this->access->tables(),
            'access' => $this->access->describe(),
            'notes' => $this->access->isPublic() ? null : 'Changes go into the draft (versions are kept). Rich text as HTML without images; images via media fields.',
        ], static fn ($v) => null !== $v && [] !== $v);
    }

    /** @return array<string, mixed> */
    private function posts(int $blog, int $lang, int $limit): array
    {
        if (!Blog::isBlog($blog)) {
            throw new ApiError('No blog with this ID (see site_overview).');
        }
        $start = Article::get($blog, $lang);
        if (null === $start || !$this->access->canReadPage($start)) {
            throw new ApiError('Blog not found.');
        }
        $res = Blog::posts($blog, ['limit' => max(1, min(100, $limit)), 'language' => $lang, 'drafts' => !$this->access->isPublic()]);
        $out = [];
        foreach ($res['posts'] as $p) {
            if (!$this->access->canReadPage($p->article)) {
                continue;
            }
            $out[] = ['id' => $p->article->id, 'title' => $p->article->name, 'url' => Content::abs($p->url), 'online' => $p->article->isOnline(),
                'date' => $p->date->format('Y-m-d'), 'author' => $p->author, 'tags' => $p->tags, 'excerpt' => $p->excerpt];
        }

        return ['blog' => $blog, 'total' => $res['total'], 'posts' => $out];
    }

    private function table(string $name, string $need): Table
    {
        $mode = $this->access->table($name);
        if (null === $mode || ('write' === $need && 'write' !== $mode)) {
            throw new ApiError('Table "' . $name . '" is not enabled ' . ('write' === $need ? 'for changes ' : '') . 'for this access.');
        }

        return YFormBridge::table($name) ?? throw new ApiError('Table "' . $name . '" does not exist.');
    }

    /** @return array<string, mixed> */
    private function record(Table $table, Record $r): array
    {
        $out = ['id' => (int) $r->get('id'), 'label' => $table->recordLabel($r)];
        foreach (YFormBridge::detailFields($table, true) as $f) {
            if ($f instanceof FluidBlocks) {
                $out[$f->name] = BlockData::text($r->get($f->name));
                continue;
            }
            $out[$f->name] = YFormBridge::text($r, $f);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function entries(string $name, int $page, int $perPage): array
    {
        $table = $this->table($name, 'read');
        $res = YFormBridge::page($table, [], max(1, $page), max(1, min(100, $perPage)));

        return ['table' => $name, 'label' => $table->label(), 'fields' => array_map(static fn ($f) => ['name' => $f->name, 'label' => $f->translatedLabel(), 'type' => new ReflectionClass($f)->getShortName()], YFormBridge::detailFields($table, true)),
            'total' => $res['total'], 'page' => $res['page'], 'pages' => $res['pages'], 'entries' => array_map(fn ($r) => $this->record($table, $r), $res['rows'])];
    }

    /** @return array<string, mixed> */
    private function entry(string $name, int $id): array
    {
        $table = $this->table($name, 'read');
        $r = $table::find($id) ?? throw new ApiError('Entry ' . $id . ' does not exist.');

        return $this->record($table, $r);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function saveEntry(string $name, int $id, array $values): array
    {
        $table = $this->table($name, 'write');
        $r = $id > 0 ? ($table::find($id) ?? throw new ApiError('Entry ' . $id . ' does not exist.')) : $table->newRecord();
        $known = [];
        foreach (YFormBridge::detailFields($table, false) as $f) {
            if (array_key_exists($f->name, $values)) {
                $r->set($f->name, $f->fromInput($values[$f->name]));
                $known[] = $f->name;
            }
        }
        if ([] === $known) {
            throw new ApiError('No known fields in "values" (fields via list_entries).');
        }
        try {
            $r->save();
        } catch (ValidationException $e) {
            throw new ApiError('Invalid input: ' . implode(' ', $e->errors->all()));
        }
        Activity::log('save', null, $table->label() . ' #' . $r->get('id') . ' (MCP)');

        return ['table' => $name, 'id' => (int) $r->get('id'), 'created' => 0 === $id, 'fields' => $known];
    }
}
