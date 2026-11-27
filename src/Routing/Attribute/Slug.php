<?php

namespace Klxm\Fluid\Routing\Attribute;

use Attribute;

/** Column the address is created from */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Slug extends MetaColumn
{
    /** @param bool $withId append the ID („titel-12“): stable if the title is not unique */
    public function __construct(
        string $column,
        public bool $withId = false,
        ?string $label = null,
        bool $editable = true,
    ) {
        parent::__construct($column, $label, $editable);
    }

    public function role(): string
    {
        return 'slug';
    }

    public function kind(): string
    {
        return 'text';
    }

    public function defaultLabel(): string
    {
        return 'Titel (ergibt die Adresse)';
    }
}
