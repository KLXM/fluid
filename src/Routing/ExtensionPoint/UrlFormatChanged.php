<?php

namespace Klxm\Fluid\Routing\ExtensionPoint;

use Klxm\Fluid\Routing\UrlFormat;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * The ending of all addresses was changed in an interface (not dispatched for Router::$config in code).
 *
 * @extends ExtensionPoint<null>
 */
final class UrlFormatChanged extends ExtensionPoint
{
    public function __construct(
        public readonly UrlFormat $format,
    ) {
        parent::__construct(self::class, null, readonly: true);
    }
}
