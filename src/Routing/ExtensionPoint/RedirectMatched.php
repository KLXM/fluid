<?php

namespace Klxm\Fluid\Routing\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * A redirect rule or shortlink matched the request; extensions return another target address (e.g. with campaign
 * parameters), an empty string to not redirect, or null to keep it. Not dispatched for 410 (gone).
 *
 * @extends ExtensionPoint<string>
 */
final class RedirectMatched extends ExtensionPoint
{
    /** @param array<string, mixed> $rule row of rex_fluid_redirect (origin "short" = shortlink) */
    public function __construct(
        string $location,
        public readonly array $rule,
        public readonly int $code,
    ) {
        parent::__construct(self::class, $location);
    }
}
