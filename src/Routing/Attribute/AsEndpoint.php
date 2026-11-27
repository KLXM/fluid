<?php

namespace Klxm\Fluid\Routing\Attribute;

use Attribute;

/**
 * Custom URL answered before the pages – on a static method
 * `fn (string $path, string $rest): void` that ends the request itself (otherwise it continues with the pages).
 *
 *     #[AsEndpoint('/feed.json')]            exactly this path
 *     #[AsEndpoint('/proxy/*')]              prefix, the rest comes as $rest
 *     #[AsEndpoint('/mcp', param: 'mcp')]    path or parameter ?mcp=…
 *     #[AsEndpoint('/live', early: true)]    even before the redirect of alias domains (services like SSE)
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsEndpoint
{
    public function __construct(
        public string $path,
        public bool $early = false,
        public ?string $param = null,
    ) {}
}
