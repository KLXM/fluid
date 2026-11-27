<?php

namespace Klxm\Fluid\Routing\Attribute;

use Attribute;

/** Column: image for social networks */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Image extends MetaColumn
{
    public function role(): string
    {
        return 'image';
    }

    public function kind(): string
    {
        return 'media';
    }

    public function defaultLabel(): string
    {
        return 'Bild für soziale Netzwerke';
    }
}
