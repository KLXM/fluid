<?php

namespace Klxm\Fluid\Routing\Attribute;

use Attribute;
use Klxm\Fluid\Routing\RouteSource;

/**
 * Marks a source of custom addresses ({@see RouteSource}) – found via the core's
 * class discovery, like #[AsModule] or #[AsMetaSchema].
 *
 *     #[AsRouteSource('news', label: 'News')]
 *     #[FromTable('rex_news', page: 12, where: 'status = 1')]
 *     #[Slug('title', withId: true)]
 *     #[Title('title')] #[Description('teaser')] #[Image('image')] #[Modified('updatedate')]
 *     final class NewsRoutes extends TableSource {}
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsRouteSource
{
    /** @param non-empty-string $key identifier of the source (lowercase letters, digits, "-", "_", ":"), e.g. 'news' */
    public function __construct(
        public string $key,
        public ?string $label = null,
    ) {}
}
