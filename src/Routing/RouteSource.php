<?php

namespace Klxm\Fluid\Routing;

use Klxm\Fluid\Routing\Attribute\AsRouteSource;
use Klxm\Fluid\Routing\Seo\PageMeta;
use LogicException;
use ReflectionClass;

use function sprintf;

/**
 * Source of custom addresses: entries (records, products, events …) that get their own address below a
 * page. The router stores the paths (one indexed query per request, cache, unique slugs, 301 on changed
 * address), adds them to the sitemap (with hreflang) and sets canonical, hreflang and meta data of the entry.
 *
 * Marked with #[AsRouteSource], the core finds it by itself (class discovery); otherwise Sources::add(new …).
 * For tables attributes suffice ({@see TableSource}); for anything else write entries() yourself:
 *
 *     #[AsRouteSource('events')]
 *     final class EventRoutes extends RouteSource
 *     {
 *         public function entries(): iterable
 *         {
 *             foreach (Event::all() as $e) {
 *                 yield new RouteEntry('events', (string) $e->id, $e->languageId, articleId: 7, slug: $e->title, params: ['event' => $e->id]);
 *             }
 *         }
 *     }
 *
 * After changes: Routes::rebuildSource('events') (all) or Routes::put($entry) / Routes::remove('events', $id).
 * Creating an address: Router::urlFor('events', $id).
 */
abstract class RouteSource
{
    private ?AsRouteSource $attribute = null;

    /**
     * All entries – one per language. The source of RouteEntry must be key().
     *
     * @return iterable<RouteEntry>
     */
    abstract public function entries(): iterable;

    public function key(): string
    {
        return $this->attribute()->key;
    }

    public function label(): string
    {
        return $this->attribute()->label ?? $this->key();
    }

    /** Does the entry still exist (visible)? Otherwise 404 – even if the stored address hasn't been recalculated yet */
    public function exists(RouteEntry $entry): bool
    {
        return true;
    }

    /** Meta data of the entry (title, description, image …); null = those of the page */
    public function meta(RouteEntry $entry): ?PageMeta
    {
        return null;
    }

    /**
     * Fields an editor may offer (fluid: page settings of the detail page). Empty = read-only.
     *
     * @return list<EditableField>
     */
    public function editable(RouteEntry $entry): array
    {
        return [];
    }

    /**
     * Saves the values of the editable fields (name => value); then recalculates the address.
     *
     * @param array<string, string> $values
     */
    public function save(RouteEntry $entry, array $values): void
    {
        throw new LogicException(sprintf('Die Quelle „%s“ lässt sich nicht bearbeiten.', $this->key()));
    }

    private function attribute(): AsRouteSource
    {
        if (null === $this->attribute) {
            $attrs = new ReflectionClass($this)->getAttributes(AsRouteSource::class);
            if (!$attrs) {
                throw new LogicException(sprintf('%s braucht #[AsRouteSource] oder eine eigene key()-Methode.', static::class));
            }
            $this->attribute = $attrs[0]->newInstance();
        }

        return $this->attribute;
    }
}
