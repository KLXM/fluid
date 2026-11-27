<?php

namespace Klxm\Fluid\Tiptap;

use Klxm\Fluid\RichText;
use Klxm\Tiptap\AsProfile;
use Klxm\Tiptap\ProfileDefinition;
use Override;
use Redaxo\Core\Translation\Translatable;

/**
 * Text editor of fluid – in the sidebar and directly on the page (inline mode of TipTap). Headings h2–h4, marker,
 * sub-/superscript, quote; paragraph, heading, color, list and link formats come from the kit via {@see KitStyles}
 * (kit `fluid`: `t-*`, `c-*`, `ul.check`, buttons), formats of other addons via their `#[AsTiptapStyles]`. Links also keep the formats of fluid (`media://`, `detail://`, `route://`).
 *
 * Projects adapt it like any profile: a class with the same name (`tiptap.fluid`) in the project wins.
 */
#[AsProfile('tiptap.fluid', 'fluid', description: new Translatable('fluid_tiptap_profile_desc'), priority: -10)]
final class FluidProfile extends ProfileDefinition
{
    public const string NAME = 'tiptap.fluid';

    /** Link schemes of fluid, resolved by RichText::linkUrl() */
    public const array LINK_SCHEMES = ['media', 'detail', 'route'];

    #[Override]
    public function config(): array
    {
        // paragraph, heading, color, list and link formats: KitStyles (kit.php `text`) and other #[AsTiptapStyles]
        return [
            'toolbar' => [
                ['heading', 'blockStyle'],
                ['bold', 'italic', 'highlight', 'inlineStyle'],
                ['link', 'unlink'],
                ['bulletList', 'orderedList', 'listStyle', 'blockquote'],
                ['subscript', 'superscript', 'clearFormatting'],
                ['undo', 'redo'],
                ['source', 'fullscreen'],
            ],
            'headings' => [2, 3, 4],
            'link' => ['internal' => true, 'media' => true, 'external' => true, 'mailto' => true, 'tel' => true, 'targetBlank' => true, 'schemes' => self::LINK_SCHEMES],
        ];
    }
}
