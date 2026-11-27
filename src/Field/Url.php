<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Url as CoreUrl;
use Redaxo\Core\View\Renderable;

/**
 * Core field (`Url`) with fluid's additions – all additional parameters are optional, without them it behaves
 * exactly like the core field (see docs/entwickler.md, „Felder“).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Url extends CoreUrl implements FluidField
{
    /**
     * @param 'half'|null $width fluid: half width in the form
     * @param list<string>|null $variants fluid: only in these displays of the block
     * @param string|null $placeholder fluid: placeholder in the input field
     */
    public function __construct(
        string|Renderable $label,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        ?int $maxLength = null,
        /** @var non-empty-list<lowercase-string> */
        array $schemes = ['http', 'https'],
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
        public readonly ?string $placeholder = null,
    ) {
        parent::__construct($label, $note, $required, $key, $maxLength, $schemes);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants, 'placeholder' => $this->placeholder], static fn ($v) => null !== $v);
    }
}
