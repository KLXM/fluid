<?php

namespace Klxm\Fluid\Routing;

/**
 * Field of an entry that an editor may offer (fluid shows them in the page settings of the detail page).
 * Saved via the source ({@see RouteSource::save()}).
 */
final readonly class EditableField
{
    /**
     * @param string $role slug, title, description, image
     * @param string $kind text, textarea, media
     */
    public function __construct(
        public string $role,
        public string $name,
        public string $label,
        public string $kind,
        public string $value,
    ) {}
}
