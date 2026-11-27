<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\ReferencesItems;
use Redaxo\Core\Form\Field\Text as CoreText;
use Redaxo\Core\Form\Reference;
use Redaxo\Core\View\Renderable;

/**
 * Link: page of the website (redaxo://ID, linkmap), URL, email, phone or anchor.
 *
 * Only in fluid (own name). In ordinary core forms it behaves like the field it extends.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Link extends CoreText implements FluidField, ReferencesItems
{
    /**
     * @param 'half'|null $width fluid: half width in the form
     * @param list<string>|null $variants fluid: only in these display variants of the block
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
        return array_filter(['type' => 'link', 'width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }

    /** @return list<Reference> linked page (redaxo://ID) */
    public function references(mixed $value): array
    {
        return Links::references($value);
    }
}
