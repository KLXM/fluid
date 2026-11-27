<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Override;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Form\Field\ArticlePicker as CoreArticlePicker;
use Redaxo\Core\Form\ValueType;
use Redaxo\Core\View\Renderable;

use function is_array;
use function is_string;

/**
 * Core field (`ArticlePicker`) with fluid's additions – all extra parameters are optional; without them
 * it behaves exactly like the core field (see docs/entwickler.md, „Felder“).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ArticlePicker extends CoreArticlePicker implements FluidField
{
    /**
     * @param 'half'|null $width fluid: half width in the form
     * @param list<string>|null $variants fluid: only in these display variants of the block
     */
    public function __construct(
        string|Renderable $label,
        bool $multiple = false,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        ?int $categoryId = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, $multiple, $note, $required, $key, $categoryId);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }

    /** Also accept `redaxo://ID` (fluid's linkmap) – otherwise like the core */
    #[Override]
    public function fromRaw(string|array|null $raw, ValueType $type): array|Article|null
    {
        $strip = static fn (mixed $v): mixed => is_string($v) ? (string) preg_replace('~^redaxo://~', '', $v) : $v;

        return parent::fromRaw(is_array($raw) ? array_map($strip, $raw) : $strip($raw), $type);
    }
}
