<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Text as CoreText;
use Redaxo\Core\View\Renderable;

/**
 * Icon from the kit's set (name, e.g. „sparkle“).
 *
 * Only in fluid (own name). In ordinary core forms it behaves like the field it extends.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Icon extends CoreText implements FluidField
{
    /**
     * @param 'half'|null $width fluid: half width in the form
     * @param list<string>|null $variants fluid: only in these displays of the block
     */
    public function __construct(
        string|Renderable $label,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, $note, $required, $key);
    }

    public function fluid(): array
    {
        return array_filter(['type' => 'icon', 'width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }
}
