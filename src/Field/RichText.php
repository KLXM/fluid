<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\ReferencesItems;
use Redaxo\Core\Form\Field\Textarea as CoreTextarea;
use Redaxo\Core\Form\Reference;
use Redaxo\Core\View\Renderable;

/**
 * Text with formatting (paragraphs, bold, italic, lists, links). Cleaned HTML is saved.
 *
 * Only in fluid (own name). In ordinary core forms it behaves like the field it extends.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class RichText extends CoreTextarea implements FluidField, ReferencesItems
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
        parent::__construct($label, $note, $required, $key, 8);
    }

    public function fluid(): array
    {
        return array_filter(['type' => 'richtext', 'width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }

    /** @return list<Reference> linked pages (redaxo://ID) */
    public function references(mixed $value): array
    {
        return Links::references($value);
    }
}
