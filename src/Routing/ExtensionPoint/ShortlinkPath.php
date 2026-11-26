<?php

namespace Klxm\Fluid\Routing\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * Address of a new shortlink before it is checked and saved; extensions return another one, e.g. with a prefix
 * ("/go/sommerfest") or their own code scheme. A taken address is still rejected afterwards.
 *
 * @extends ExtensionPoint<string>
 */
final class ShortlinkPath extends ExtensionPoint
{
    /** @param bool $generated the address is a random code (no wish was given) */
    public function __construct(
        string $path,
        public readonly string $target,
        public readonly bool $generated,
    ) {
        parent::__construct(self::class, $path);
    }
}
