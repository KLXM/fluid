<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Textarea as CoreTextarea;
use Redaxo\Core\View\Renderable;

/**
 * Core field (`Textarea`) with the additions of fluid – all additional parameters are optional, without them
 * it behaves exactly like the core field (see docs/entwickler.md, „Felder“).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Textarea extends CoreTextarea implements FluidField
{
    /**
     * @param 'half'|null $width fluid: half width in the form
     * @param list<string>|null $variants fluid: only in these displays of the block
     * @param string|null $placeholder fluid: placeholder in the input field
     * @param bool $search fluid: include in the search index
     */
    public function __construct(
        string|Renderable $label,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        int $rows = 5,
        ?int $maxLength = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
        public readonly ?string $placeholder = null,
        public readonly bool $search = true,
    ) {
        parent::__construct($label, $note, $required, $key, $rows, $maxLength);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants, 'placeholder' => $this->placeholder, 'search' => $this->search ? null : false], static fn ($v) => null !== $v);
    }
}
