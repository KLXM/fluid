<?php

namespace Klxm\Fluid\Routing\ExtensionPoint;

use Klxm\Fluid\Routing\AddressOptions;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * Slug or address options of a page were changed (after the addresses were rebuilt).
 *
 * @extends ExtensionPoint<null>
 */
final class PageAddressChanged extends ExtensionPoint
{
    /** @param int|null $languageId null = all languages (hiding a level) */
    public function __construct(
        public readonly int $articleId,
        public readonly ?int $languageId,
        public readonly AddressOptions $options,
    ) {
        parent::__construct(self::class, null, readonly: true);
    }
}
