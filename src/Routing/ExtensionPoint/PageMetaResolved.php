<?php

namespace Klxm\Fluid\Routing\ExtensionPoint;

use Klxm\Fluid\Routing\RouteMatch;
use Klxm\Fluid\Routing\Seo\PageMeta;
use Redaxo\Core\Content\Article;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * Final meta data shortly before output – last changes, own tags, JSON-LD. Change the object in the subject directly
 * ($ep->subject->description = …) or return a new one.
 *
 * @extends ExtensionPoint<PageMeta>
 */
final class PageMetaResolved extends ExtensionPoint
{
    /** @param RouteMatch|null $match entry of a source or resolver if the address is not a page */
    public function __construct(
        PageMeta $meta,
        public readonly Article $article,
        public readonly ?RouteMatch $match = null,
    ) {
        parent::__construct(self::class, $meta);
    }
}
