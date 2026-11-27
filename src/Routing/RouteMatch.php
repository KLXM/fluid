<?php

namespace Klxm\Fluid\Routing;

use Klxm\Fluid\Routing\Seo\PageMeta;

/**
 * Result of resolving an address that is not a page: which page is shown, with which parameters – and
 * optionally the canonical address (if the request differs, the router redirects with 301), meta data and language versions.
 *
 * Stored entries of the sources get it from the router; dynamic resolvers (Router::resolver) return it.
 */
final readonly class RouteMatch
{
    /**
     * @param array<string, scalar> $params parameters for the page (request and $_GET)
     * @param string|null $url canonical address (relative like Router::url or absolute); null = no check
     * @param bool $cacheRedirect cache the 301 in the browser – not for addresses that can change (title)
     * @param array<int, string> $alternates language ID => address of the same thing in other languages (hreflang)
     */
    public function __construct(
        public int $articleId,
        public int $languageId,
        public array $params = [],
        public ?string $url = null,
        public bool $cacheRedirect = true,
        public ?PageMeta $meta = null,
        public array $alternates = [],
        public ?RouteEntry $entry = null,
    ) {}
}
