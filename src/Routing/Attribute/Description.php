<?php

namespace Klxm\Fluid\Routing\Attribute;

use Attribute;

/** Column: description for search engines */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Description extends MetaColumn
{
    public function role(): string
    {
        return 'description';
    }

    public function kind(): string
    {
        return 'textarea';
    }

    public function defaultLabel(): string
    {
        return 'Beschreibung für Suchmaschinen';
    }
}
