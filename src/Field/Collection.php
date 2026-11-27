<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Collection as CoreCollection;
use Redaxo\Core\View\Renderable;

/**
 * Core field (`Collection`) with fluid's additions – all extra parameters are optional; without them
 * it behaves exactly like the core field (see docs/entwickler.md, „Felder“).
 *
 * Each entry is an object of the class `item` with its own fields (properties with field attributes). Stored is
 * a list of values per field key – on the website fluid edits it like a repeater group.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Collection extends CoreCollection implements FluidField
{
    /**
     * @param string|null $itemLabel fluid: label of an entry („+ Karte“)
     * @param string|null $titleField fluid: field (key) shown as the title of a collapsed entry
     * @param 'half'|null $width fluid: half width in the form
     * @param list<string>|null $variants fluid: only in these display variants of the block
     */
    public function __construct(
        string|Renderable $label,
        /** @var class-string */
        string $item,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        ?int $min = null,
        ?int $max = null,
        public readonly ?string $itemLabel = null,
        public readonly ?string $titleField = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, $item, $note, $required, $key, $min, $max);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants, 'item_label' => $this->itemLabel, 'title_field' => $this->titleField], static fn ($v) => null !== $v);
    }
}
