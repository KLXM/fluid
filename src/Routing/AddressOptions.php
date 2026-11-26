<?php

namespace Klxm\Fluid\Routing;

/**
 * How the address of a page is built.
 *
 * @internal
 */
final readonly class AddressOptions
{
    /**
     * @param bool $ownPath the slug is the complete address, without the parent pages
     * @param bool $hidden the page is left out of the addresses of its subpages
     */
    public function __construct(
        public bool $ownPath = false,
        public bool $hidden = false,
    ) {}
}
