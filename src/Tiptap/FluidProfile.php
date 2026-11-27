<?php

namespace Klxm\Fluid\Tiptap;

use Klxm\Fluid\RichText;
use Klxm\Tiptap\AsProfile;
use Klxm\Tiptap\ProfileDefinition;
use Override;
use Redaxo\Core\Translation\I18n;

use function array_map;

/**
 * Text editor of fluid – in the sidebar and directly on the page (inline mode of TipTap). Same formats as before, so
 * existing texts keep their look: paragraph styles `t-*`, colors `c-*`, checklist `ul.check`, headings h2–h4, marker,
 * sub-/superscript, quote. Links also keep the formats of fluid (`media://`, `detail://`, `route://`).
 *
 * Projects adapt it like any profile: a class with the same name (`tiptap.fluid`) in the project wins.
 */
#[AsProfile('tiptap.fluid', 'fluid', description: 'Texte in fluid – Seitenleiste und direkt auf der Seite.', priority: -10)]
final class FluidProfile extends ProfileDefinition
{
    public const string NAME = 'tiptap.fluid';

    /** Link schemes of fluid, resolved by RichText::linkUrl() */
    public const array LINK_SCHEMES = ['media', 'detail', 'route'];

    #[Override]
    public function config(): array
    {
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
            'blockStyles' => array_map(static fn (string $class): array => ['label' => I18n::rawMsg('fluid_rich_' . $class), 'class' => $class, 'element' => 'p'], RichText::P_CLASSES),
            'inlineStyles' => array_map(static fn (string $color): array => ['label' => I18n::rawMsg('fluid_rich_c_' . $color), 'class' => 'c-' . $color], RichText::COLORS),
            'listStyles' => [['label' => I18n::rawMsg('fluid_rich_check'), 'class' => 'check', 'list' => 'ul']],
            'link' => ['internal' => true, 'media' => true, 'external' => true, 'mailto' => true, 'tel' => true, 'targetBlank' => true, 'schemes' => self::LINK_SCHEMES],
        ];
    }
}
