<?php

namespace Klxm\Fluid\Routing\ExtensionPoint;

use Klxm\Fluid\Routing\Shortlink;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/** @extends ExtensionPoint<null> */
final class ShortlinkDeleted extends ExtensionPoint
{
    public function __construct(
        public readonly Shortlink $shortlink,
    ) {
        parent::__construct(self::class, null, readonly: true);
    }
}
