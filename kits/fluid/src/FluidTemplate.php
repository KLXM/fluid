<?php

namespace Klxm\Fluid\Kit\Fluid;

use Klxm\Fluid\BlockModule;
use Klxm\Fluid\Data\MenuEntries;
use Klxm\Fluid\Data\YFormBridge;
use Klxm\Fluid\Kit;
use Klxm\Fluid\Routing\Router;
use Klxm\Fluid\VideoLinks;
use Klxm\NavigationArray\Navigation;
use Klxm\NavigationArray\NavigationItem;
use Override;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\AsTemplate;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\ContentSection;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Language\Language;
use Redaxo\Core\View\HasView;
use Redaxo\Core\View\Renderer;

/**
 * Page frame of fluid: header with menu from the structure, content (blocks), footer – styled by the selected kit.
 * Markup and classes as in the Studio kit so its CSS applies unchanged. A kit can replace the frame with its own
 * `template.view.php` (same signature as FluidTemplate.view.php).
 */
#[AsTemplate('fluid', 'Fluid')]
final class FluidTemplate extends Template
{
    use HasView;

    #[Override]
    public function render(ArticleContent $content): string
    {
        $kit = Kit::active();
        $kit->definition();   // loads functions.php of the kit (and its ancestors)
        if (null !== $own = $kit->file('template.view.php')) {
            return VideoLinks::inject(Renderer::render($own, [$content]));
        }

        // Video links in the lightbox (website setting, off by default): mark links, script only when needed
        return VideoLinks::inject($this->renderView($content));
    }

    #[Override]
    public function isModuleAllowed(ContentSection $section, string $module): bool
    {
        // fluid blocks only those of the active kit; ordinary modules of the project or of addons as usual in REDAXO –
        // editable in the backend and on the website via the sidebar (core form)
        return is_a($module, BlockModule::class, true) ? $module::kit() === Kit::active() : true;
    }

    /**
     * Menu from the structure (online categories, two levels; navigation_array) in the format of the fluid navigation.
     *
     * @return list<array{label: string, href: string, current: bool, active: bool, children: list<array<string, mixed>>}>
     */
    public static function menu(int $depth = 2): array
    {
        // Landing domain: menu limited to the landing page (subpages of the start page, otherwise none)
        $start = 0;
        if ($landing = Router::landing()) {
            if (!$landing->subtree || !Category::get((int) $landing->articleId)) {
                return [];
            }
            $start = (int) $landing->articleId;
        }
        // navigation_array: depth counts the levels below the first
        $map = static function (NavigationItem $i) use (&$map): array {
            return ['label' => $i->name, 'href' => $i->url, 'current' => $i->current, 'active' => $i->active, 'children' => array_map($map, $i->children), 'id' => $i->id];
        };

        // Navigation omits menu items „nicht im Menü“ (meta field of navigation_array, page tree) including subpages
        $menu = array_map($map, new Navigation(start: $start, depth: $depth - 1)->items());

        // Entries in the menu (data list with detail view → „Einträge im Menü zeigen“): virtual sub-items of the overview
        return YFormBridge::available() ? MenuEntries::extend($menu, Language::getCurrentId()) : $menu;
    }
}
