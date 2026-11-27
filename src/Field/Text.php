<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Text as CoreText;
use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\View\Renderable;

/**
 * Core field (`Text`) with fluid's additions – all extra parameters are optional, without them it behaves exactly
 * like the core field (see docs/entwickler.md, „Felder“).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Text extends CoreText implements FluidField
{
    /**
     * @param 'half'|null $width fluid: half width in the form
     * @param list<string>|null $variants fluid: only in these variants of the block
     * @param string|Translatable|null $placeholder fluid: placeholder in the input
     * @param bool $search fluid: include in the search index
     */
    public function __construct(
        string|Renderable $label,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        ?int $maxLength = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
        public readonly string|Translatable|null $placeholder = null,
        public readonly bool $search = true,
    ) {
        parent::__construct($label, $note, $required, $key, $maxLength);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants, 'placeholder' => null !== $this->placeholder ? Translatable::text($this->placeholder) : null, 'search' => $this->search ? null : false], static fn ($v) => null !== $v);
    }
}
