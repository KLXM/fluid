<?php

namespace Klxm\Fluid\Routing\Attribute;

use Klxm\Fluid\Routing\RouteSource;

/**
 * Column of a table with a role (slug, title …). An editor (e.g. fluid on the website) can offer editable columns
 * – {@see RouteSource::editable()}.
 */
abstract readonly class MetaColumn
{
    public function __construct(
        public string $column,
        public ?string $label = null,
        public bool $editable = true,
    ) {}

    /** Role: slug, title, description, image, modified */
    abstract public function role(): string;

    abstract public function defaultLabel(): string;

    public function label(): string
    {
        return $this->label ?? $this->defaultLabel();
    }

    /** Kind of input field: text, textarea, media, none */
    public function kind(): string
    {
        return 'text';
    }
}
