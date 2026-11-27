<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\DatePicker as CoreDatePicker;
use Redaxo\Core\View\Renderable;

/**
 * Core field (`DatePicker`) with fluid's additions – all additional parameters are optional, without them it behaves
 * exactly like the core field (see docs/entwickler.md, „Felder“).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class DatePicker extends CoreDatePicker implements FluidField
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
        ?string $min = null,
        ?string $max = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, $note, $required, $key, $min, $max);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }
}
