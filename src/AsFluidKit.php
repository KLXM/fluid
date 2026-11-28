<?php

namespace Klxm\Fluid;

use Attribute;

/**
 * Registers a kit with fluid (like `#[AsModule]` for modules): in the project (`src/`) or in its own Composer package.
 *
 *     #[AsFluidKit('nature', 'Nature')]
 *     final class NatureKit extends Kit {}
 *
 * A kit inherits from the kit `parent` (default: `fluid`) and only brings what it changes: CSS, individual views, the
 * template, design options or blocks. Everything else comes from the parent kit. `bin/console fluid:kit:create` creates one.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsFluidKit
{
    /**
     * @param non-empty-string $key Key of the kit (lowercase letters, digits, `-`), e.g. `nature`
     * @param non-empty-string $label Name in the kit picker
     * @param string|null $parent Parent kit; `null` = `fluid` (only the kit `fluid` itself has none)
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $parent = null,
    ) {}
}
