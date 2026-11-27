<?php

namespace Klxm\Fluid\Routing;

/**
 * Routing settings, set by the project (like the core's policies) – in Project::configure():
 *
 *     Router::$config = new RoutingConfig(format: UrlFormat::html());
 *
 * Without them the defaults apply (URLs without suffix, cache on).
 */
final readonly class RoutingConfig
{
    /**
     * @param UrlFormat $format Suffix of the URLs (none, „/“, „.html“, mixed, custom); any other spelling redirects with 301
     * @param bool $cache Keep the URLs of pages and sources cached as a PHP file (otherwise from the database per request)
     * @param string $redirectBy Header X-Redirect-By on redirects ('' = none)
     * @param bool $enabled false = routing stays out entirely (e.g. when yrewrite handles the URLs)
     */
    public function __construct(
        public UrlFormat $format = new UrlFormat(),
        public bool $cache = true,
        public string $redirectBy = 'REDAXO',
        public bool $enabled = true,
    ) {}
}
