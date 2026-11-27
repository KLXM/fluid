<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Email as CoreEmail;
use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\View\Renderable;

/**
 * Core field (`Email`) with the fluid additions – all additional parameters are optional, without them
 * it behaves exactly like the core field (see docs/entwickler.md, „Felder“).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Email extends CoreEmail implements FluidField
{
    /**
     * @param 'half'|null $width fluid: half width in the form
     * @param list<string>|null $variants fluid: only in these displays of the block
     * @param string|Translatable|null $placeholder fluid: placeholder in the input field
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
    ) {
        parent::__construct($label, $note, $required, $key, $maxLength);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants, 'placeholder' => null !== $this->placeholder ? Translatable::text($this->placeholder) : null], static fn ($v) => null !== $v);
    }
}
