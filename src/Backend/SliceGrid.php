<?php

namespace Klxm\Fluid\Backend;

use Klxm\Fluid\BlockModule;
use Klxm\Fluid\FluidAddon;
use Klxm\Fluid\Layout;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ExtensionPoint\ContentPageModuleSelect;
use Redaxo\Core\Content\ExtensionPoint\SliceMenu;
use Redaxo\Core\Content\ExtensionPoint\SliceOutput;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\View\Asset;
use Redaxo\Core\View\Fragment;

use function array_diff;
use function array_filter;
use function array_map;
use function array_slice;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function in_array;
use function max;
use function min;
use function preg_replace;
use function Redaxo\Core\View\escape;
use function str_contains;
use function str_replace;
use function strip_tags;

/**
 * Backend slice list like the website: the blocks of a layout sit side by side in their columns in the layout
 * panel – each with the core's head and buttons (edit, delete, online, move), so they remain real slices.
 *
 * From the outside, without changing the core:
 *  - SliceOutput (edit mode): panels of the column blocks are collected instead of output in place,
 *    the layout panel contains a placeholder per column (LayoutBlock::output()).
 *  - Fragment core/structure/content/slice_list.php (the addon's fragments/): inserts the panels into the placeholders.
 *  - ContentPageModuleSelect: „Block hinzufügen“ in a column passes the column along (fluid_col) – the form
 *    preselects it (Form\FieldForm). Additionally „+ Block in Spalte n“ at the end of each column.
 *
 * When a column block is edited or created, assets/slice-grid.js opens the form in a dialog above the
 * grid; without the script the columns are stacked (form at full width).
 *
 * @internal
 */
final class SliceGrid
{
    /** Parameter for the column of a new block */
    public const string PARAM = 'fluid_col';

    /** Parameter and cookie for the layout view (grid | tree) */
    public const string VIEW = 'fluid_view';

    /** Parameter: layout whose view is toggled */
    public const string LAYOUT = 'fluid_layout';

    /** @var array<int, array<int, list<string>>> layout ID → column → HTML of the column blocks */
    private static array $cols = [];

    /** @var array<int, true> Layouts in whose columns a block is currently being edited */
    private static array $editing = [];

    private static bool $on = false;

    /** URL of the API function for copy/paste (with CSRF token) – for the script on the slice list */
    private static ?string $opsUrl = null;

    /** Enable in the backend on the content page (FluidAddon::boot) */
    public static function boot(): void
    {
        self::$on = true;
        // View per layout: columns (grid, default) or tree – via a button in the layout panel; the cookie remembers the
        // layouts in tree view
        $view = Request::get(self::VIEW, 'string');
        $layout = Request::get(self::LAYOUT, 'int', 0);
        if (in_array($view, ['grid', 'tree'], true) && $layout > 0) {
            $tree = array_diff(self::treeIds(), [$layout]);
            if ('tree' === $view) {
                $tree[] = $layout;
            }
            $value = implode(',', array_slice(array_values(array_unique($tree)), -200));   // Cookie klein halten
            setcookie(self::VIEW, $value, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true, 'secure' => Request::isHttps()]);
            $_COOKIE[self::VIEW] = $value;
        }
        Fragment::addDirectory(FluidAddon::instance()->getPath('fragments/'));
        Asset::addJsFile(FluidAddon::instance()->getAssetsUrl('slice-grid.js'));   // form in a dialog above the grid
        Extension::register(ContentPageModuleSelect::class, self::moduleSelect(...));
        Extension::register(SliceMenu::NAME, self::compactMenu(...));   // meldet sich als SLICE_MENU an
    }

    /** Raster-Funktionen im Backend aktiv (Inhaltsseite) */
    public static function active(): bool
    {
        return self::$on && Core::isBackend();
    }

    /** Does this layout show its blocks in the grid (view „Spalten“) – otherwise as a tree below */
    public static function showsGrid(ArticleSlice $layout): bool
    {
        return self::active() && !in_array($layout->id, self::treeIds(), true);
    }

    /** @return list<int> Layouts in tree view (cookie) */
    private static function treeIds(): array
    {
        $raw = (string) ($_COOKIE[self::VIEW] ?? '');

        return array_values(array_filter(array_map('intval', explode(',', $raw)), static fn (int $id): bool => $id > 0));
    }

    /** Umschalter „Spalten | Baum“ im Layout-Panel */
    public static function switcher(ArticleSlice $layout): string
    {
        $grid = self::showsGrid($layout);
        $url = static fn (string $v): string => escape(Url::backendPage('content/edit', [
            'article_id' => $layout->articleId, 'language' => $layout->languageId, 'content_section_id' => $layout->contentSectionId, self::VIEW => $v, self::LAYOUT => $layout->id,
        ]) . '#slice' . $layout->id);

        return '<nav class="fluid-be-view" aria-label="Ansicht dieses Layouts">'
            . '<a href="' . $url('grid') . '"' . ($grid ? ' aria-current="true"' : '') . '><i class="rex-icon fa-table-columns" aria-hidden="true"></i> Spalten</a>'
            . '<a href="' . $url('tree') . '"' . (!$grid ? ' aria-current="true"' : '') . '><i class="rex-icon fa-sitemap" aria-hidden="true"></i> Baum</a></nav>';
    }

    /** SliceOutput im Bearbeiten-Modus: Panel eines Spalten-Blocks einsammeln – null = normal ausgeben. */
    public static function collect(SliceOutput $ep): ?string
    {
        if (!self::active()) {
            return null;
        }
        $parent = Layout::parentOf($ep->slice);
        if (null === $parent || !self::showsGrid($parent)) {
            return null;   // not a column block, or layout in tree view
        }
        $editing = in_array(Request::get('function', 'string'), ['edit', 'add'], true) && Request::get('slice_id', 'int') === $ep->slice->id;
        if ($editing) {
            self::$editing[$parent->id] = true;
        }
        $n = Layout::count(BlockModule::of($parent)->context()->data);
        self::$cols[$parent->id][min(max(1, Layout::col($ep->slice)), $n)][] = '<div class="fluid-be-item' . ($editing ? ' is-editing' : '') . '"><ul class="rex-slices fluid-be-list">'
            . (string) $ep->subject . '</ul></div>';

        return '';
    }

    /** Platzhalter einer Spalte im Layout-Panel */
    public static function placeholder(int $layoutId, int $col): string
    {
        return '<!--fluid-grid:' . $layoutId . ':' . $col . '-->';
    }

    /**
     * Grid in the layout panel: columns at their width, per column placeholders for the panels and „+ Block in Spalte n“.
     *
     * @param array<string, mixed> $data values of the layout
     */
    public static function grid(ArticleSlice $layout, array $data): string
    {
        $n = Layout::count($data);
        $units = Layout::units($data);
        $next = self::sliceAfterGroup($layout);
        $h = '';
        for ($i = 1; $i <= $n; ++$i) {
            $h .= '<div class="fluid-be-gcol blocks-be-w' . max(1, min(12, (int) ($units[$i - 1] ?? 6))) . '">'
                . '<span class="blocks-be-gcol__label">Spalte ' . $i . '</span>'
                . self::placeholder($layout->id, $i)
                . self::addButton($layout, $next, $i) . '</div>';
        }

        return '<div class="fluid-be-grid fluid-be-cols' . $n . (isset(self::$editing[$layout->id]) ? ' is-editing' : '') . '" data-fluid-grid="' . $layout->id . '">' . $h . '</div>';
    }

    /** Attribute for the slice list: URL of the API function (copy, cut, paste) */
    public static function opsAttr(): string
    {
        return null !== self::$opsUrl ? ' data-fluid-ops="' . escape(self::$opsUrl) . '"' : '';
    }

    /** Fragment slice_list.php: insert the panels of the column blocks into the placeholders */
    public static function assemble(string $content): string
    {
        foreach (self::$cols as $layoutId => $cols) {
            foreach ($cols as $col => $items) {
                $ph = self::placeholder($layoutId, $col);
                $html = implode('', $items);
                // not found (layout offline/filtered differently): don't lose blocks – append them at the end
                $content = str_contains($content, $ph) ? str_replace($ph, $html, $content) : $content . $html;
            }
            if (isset(self::$editing[$layoutId])) {
                $content = str_replace('data-fluid-grid="' . $layoutId . '"', 'data-fluid-grid="' . $layoutId . '" data-editing', $content);
            }
        }
        self::$cols = [];

        return (string) preg_replace('~<!--fluid-grid:\d+:\d+-->~', '<p class="blocks-be-empty">leer</p>', $content);
    }

    /** „Block hinzufügen“ before a column block: new blocks land in the same column */
    public static function moduleSelect(ContentPageModuleSelect $ep): string
    {
        $select = (string) $ep->subject;
        if ($ep->sliceId <= 0) {
            return $select;
        }
        $slice = ArticleSlice::getArticleSliceById($ep->sliceId, $ep->article->languageId, 0)
            ?? ArticleSlice::getArticleSliceById($ep->sliceId, $ep->article->languageId, 1);   // Arbeitsversion
        if (null === $slice || null === Layout::parentOf($slice)) {
            return $select;
        }

        return str_replace('function=add&amp;', 'function=add&amp;' . self::PARAM . '=' . Layout::col($slice) . '&amp;', $select);
    }

    /**
     * Buttons of a column block as icons (pencil, trash, status) – title and buttons fit into one line of the
     * narrow column. The label remains for screen readers (hidden_label) and as tooltip.
     */
    public static function compactMenu(SliceMenu $ep): void
    {
        $slice = ArticleSlice::getArticleSliceById($ep->sliceId, $ep->languageId, 0) ?? ArticleSlice::getArticleSliceById($ep->sliceId, $ep->languageId, 1);
        if (null === $slice || !Module::exists($slice->moduleKey)) {
            return;
        }
        // duplicate (also without script); assets/slice-grid.js adds copy, cut, paste in the "⋯" menu
        $article = Article::get($slice->articleId, $slice->languageId);
        if (null !== $article && $ep->hasPerm) {
            self::$opsUrl ??= BlockOpsApi::url($article, $slice->contentSectionId);
            $ep->additionalActions[] = [
                'hidden_label' => 'Verdoppeln', 'icon' => 'duplicate',
                'url' => self::$opsUrl . '&fluid_op=duplicate&slice_id=' . $slice->id . '#slice' . $slice->id,
                'attributes' => ['title' => 'Verdoppeln', 'data-fluid-op' => 'duplicate', 'data-slice' => (string) $slice->id],
            ];
        }
        $parent = Layout::parentOf($slice);
        if (null === $parent || !self::showsGrid($parent)) {
            return;
        }
        $icon = static function (array $action, string $name): array {
            if ([] === $action || !isset($action['label'])) {
                return $action;
            }
            $label = (string) $action['label'];
            unset($action['label']);
            $action['hidden_label'] = $label;
            $action['icon'] = $name;
            $action['attributes'] = ['title' => strip_tags($label)] + (array) ($action['attributes'] ?? []);

            return $action;
        };
        $ep->menuEditAction = $icon($ep->menuEditAction, 'edit');
        $ep->menuDeleteAction = $icon($ep->menuDeleteAction, 'delete');
        $ep->menuStatusAction = $icon($ep->menuStatusAction, 1 === $slice->status ? 'online' : 'offline');
    }

    /** Column for a new block form (adding in a column) – or null */
    public static function requestedCol(): ?int
    {
        $col = Request::get(self::PARAM, 'int', 0);

        return self::active() && 'add' === Request::get('function', 'string') && $col > 0 ? $col : null;
    }

    /** „+ Block in Spalte n“: only blocks allowed in columns; inserted after the layout's group */
    private static function addButton(ArticleSlice $layout, int $before, int $col): string
    {
        $items = [];
        $user = Core::getUser();
        foreach (BlockModule::all() as $key => $class) {
            if (!$class::nestable() || ($user && !$user->getComplexPerm('modules')->hasPerm($key))) {
                continue;
            }
            $items[] = ['key' => $key, 'title' => escape($class::label()), 'href' => Url::backendPage('content/edit', [
                'article_id' => $layout->articleId, 'language' => $layout->languageId, 'content_section_id' => $layout->contentSectionId,
                'slice_id' => $before, 'function' => 'add', 'module' => $key, self::PARAM => $col,
            ]) . '#fluid-add'];
        }
        if (!$items) {
            return '';
        }
        usort($items, static fn (array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));
        $fragment = new Fragment();
        $fragment->setVar('block', true);
        $fragment->setVar('button_label', '+ Block in Spalte ' . $col);
        $fragment->setVar('items', $items, false);

        return '<div class="fluid-be-add">' . $fragment->parse('core/structure/content/module_select.php') . '</div>';
    }

    /** First slice after the layout and its column blocks (-1 = end) – new column blocks go before it */
    private static function sliceAfterGroup(ArticleSlice $layout): int
    {
        $children = Layout::children($layout);
        $last = $children ? $children[array_key_last($children)] : $layout;

        return $last->getNextSlice()?->id ?? -1;
    }
}
