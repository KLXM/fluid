<?php

namespace Project\Block;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\InlineStyle;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Override;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Form\Field\Textarea;

use function Redaxo\Core\View\escape;

/**
 * Own block in the project – with the core fields (without the additions of fluid) and Link, a field only fluid has.
 * The output uses the helpers of the kit (classes wrap, card, btn) – the kit itself stays unchanged.
 */
#[AsModule('fluid.teaser', 'Teaser (Projekt)')]
#[AsFluidBlock(icon: 'star', group: 'Inhalt', nestable: true, help: 'Beispiel für einen eigenen Block im Projekt.')]
final class Teaser extends FluidBlock
{
    #[Text(
        'Überschrift',
        required: true,
        maxLength: 80,
    )]
    public string $title = '';

    #[Textarea(
        'Text',
        rows: 3,
    )]
    public string $text = '';

    #[Link('Link')]
    public string $link = '';

    #[Override]
    public function output(): string
    {
        // no style attributes (CSP): InlineStyle assigns a class, the rule is in the <style nonce> of the page
        return '<div class="wrap"><div class="card ' . InlineStyle::class('padding:var(--f-s-l)') . '">'
            . '<h2 class="h3"' . $this->edit('title') . '>' . escape($this->title) . '</h2>'
            . '<p' . $this->edit('text', 'lines') . '>' . nl2br(escape($this->text), false) . '</p>'
            . ('' !== trim($this->link) ? '<a class="btn btn--primary" ' . fluid_link_attrs($this->link) . '>Mehr</a>' : '')
            . '</div></div>';
    }
}
