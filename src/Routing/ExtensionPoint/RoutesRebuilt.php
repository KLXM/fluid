<?php

namespace Klxm\Fluid\Routing\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * URLs were recalculated (pages after a structure change, or one source) – e.g. rebuild the search index.
 *
 * @extends ExtensionPoint<null>
 */
final class RoutesRebuilt extends ExtensionPoint
{
    /** @param string|null $source Identifier of the source; null = pages (and all sources) */
    public function __construct(
        public readonly ?string $source = null,
    ) {
        parent::__construct(self::class, null, [], true);
    }
}
