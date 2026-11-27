<?php

namespace Project\Block;

use DateTimeImmutable;
use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\DatePicker;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\InlineStyle;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Override;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\MediaPool\Media;

use function Redaxo\Core\View\escape;

/**
 * The example module „Hinweis“ (examples/module/HinweisModule.php) as a fluid block. Compared to the module:
 *
 *  1. extends FluidBlock instead of Module, the key starts with „fluid.“, #[AsBlock] for the block picker;
 *  2. the fields remain those of the core – here the fluid ones of the same name, which also know `width` (half width in the
 *     sidebar); plus Link, a field only fluid has;
 *  3. output() stays output(): `$this->edit('feld')` makes a field editable directly on the page.
 */
#[AsModule('fluid.hinweis', 'Hinweis')]
#[AsBlock(icon: 'info', group: 'Inhalt', nestable: true, help: 'Kurzer Hinweis mit Art, Bild und Link.')]
final class Hinweis extends FluidBlock
{
    #[Text(
        'Überschrift',
        required: true,
        maxLength: 120,
    )]
    public string $headline = '';

    #[Textarea(
        'Text',
        rows: 4,
    )]
    public string $text = '';

    #[Choice(
        'Art',
        choices: ['info' => 'Information', 'warn' => 'Warnung', 'ok' => 'Erledigt'],
        width: 'half',
    )]
    public string $kind = 'info';

    #[Checkbox(
        'Hervorheben',
        width: 'half',
    )]
    public bool $strong = false;

    #[DatePicker(
        'Gültig bis',
        width: 'half',
    )]
    public ?DateTimeImmutable $until = null;

    #[Link(
        'Mehr dazu',
        width: 'half',
    )]
    public string $link = '';

    #[MediaPicker(
        'Bild',
        preview: true,
    )]
    public ?Media $image = null;

    #[Override]
    public function output(): string
    {
        $color = ['warn' => '#dc2626', 'ok' => '#16a34a'][$this->kind] ?? 'var(--f-a)';
        // no style attributes (CSP): InlineStyle assigns a class, the rule is in the <style nonce> of the page
        $style = 'padding:var(--f-s-l);border-left:6px solid ' . $color . ($this->strong ? ';box-shadow:0 0 0 2px ' . $color : '');
        $html = '<div class="wrap"><aside class="card ' . InlineStyle::class($style) . '">'
            . '<h2 class="h3"' . $this->edit('headline') . '>' . escape($this->headline) . '</h2>'
            . '<p' . $this->edit('text', 'lines') . '>' . nl2br(escape($this->text), false) . '</p>';
        if ($this->until) {
            $html .= '<p class="muted">Gültig bis ' . escape($this->until->format('d.m.Y')) . '</p>';
        }
        if ($this->image) {
            $html .= img($this->image->fileName, '24rem');
        }
        if ('' !== trim($this->link)) {
            $html .= '<p><a ' . fluid_link_attrs($this->link) . '>Mehr dazu →</a></p>';
        }

        return $html . '</aside></div>';
    }
}
