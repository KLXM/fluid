<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Closure;
use Redaxo\Core\Form\ChoiceProvider;
use Redaxo\Core\Form\Field\Choice as CoreChoice;
use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\View\Renderable;

/**
 * Core field (`Choice`) with fluid's additions – all extra parameters are optional; without them
 * it behaves exactly like the core field (see docs/entwickler.md, „Felder“).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Choice extends CoreChoice implements FluidField
{
    /**
     * @param 'half'|null $width fluid: half width in the form
     * @param list<string>|null $variants fluid: only in these display variants of the block
     */
    public function __construct(
        string|Renderable $label,
        array|ChoiceProvider|Closure $choices,
        bool $multiple = false,
        bool $expanded = false,
        string|Translatable|null $placeholder = null,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, $choices, $multiple, $expanded, $placeholder, $note, $required, $key);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }
}
