<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Checkbox;   // this block's class is called Richtext
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\RichText as RichTextField;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.richtext', new Translatable('fluid_block_richtext'))]
#[AsFluidBlock(
    icon: 'paragraph',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_richtext_help'),
    variants: [
        'standard' => new Translatable('fluid_block_richtext_variant_standard'),
        'article' => new Translatable('fluid_block_richtext_variant_article'),
        'columns' => new Translatable('fluid_block_richtext_variant_columns'),
    ],
    nestable: ['standard', 'columns'],
    drags: [
        [
            'field' => 'measure',
            'target' => '.prose:not(.prose--columns)',
            'class' => 'prose--m-{v}',
            'label' => new Translatable('fluid_block_richtext_drag_measure'),
        ],
    ],
)]
final class Richtext extends FluidBlock
{
    #[Text(
        new Translatable('fluid_field_eyebrow'),
        maxLength: 60,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        new Translatable('fluid_field_title'),
        maxLength: 110,
        width: 'half',
    )]
    public string $title = '';

    #[Textarea(
        new Translatable('fluid_field_intro'),
        maxLength: 400,
        rows: 2,
    )]
    public string $intro = '';

    #[RichTextField(new Translatable('fluid_field_text'))]
    public string $text = '';

    #[Choice(
        new Translatable('fluid_block_richtext_measure'),
        required: true,
        choices: ['narrow' => new Translatable('fluid_block_richtext_measure_narrow'), 'normal' => new Translatable('fluid_block_richtext_measure_normal'), 'wide' => new Translatable('fluid_block_richtext_measure_wide'), 'full' => new Translatable('fluid_block_richtext_measure_full')],
        width: 'half',
        variants: ['standard', 'article'],
        note: new Translatable('fluid_block_richtext_measure_help'),
    )]
    public string $measure = 'normal';

    #[Text(
        new Translatable('fluid_block_richtext_meta'),
        maxLength: 120,
        placeholder: new Translatable('fluid_block_richtext_meta_placeholder'),
    )]
    public string $meta = '';

    #[Checkbox(
        new Translatable('fluid_block_richtext_dropcap'),
        width: 'half',
    )]
    public bool $dropcap = false;

    #[Checkbox(
        new Translatable('fluid_block_richtext_toc'),
        width: 'half',
    )]
    public bool $toc = true;

    #[Choice(
        new Translatable('fluid_block_richtext_reveal'),
        required: true,
        choices: ['off' => new Translatable('fluid_block_richtext_reveal_off'), 'block' => new Translatable('fluid_block_richtext_reveal_block'), 'paragraphs' => new Translatable('fluid_block_richtext_reveal_paragraphs')],
        width: 'half',
        note: new Translatable('fluid_block_richtext_reveal_help'),
    )]
    public string $reveal = 'off';

    #[Choice(
        new Translatable('fluid_block_richtext_scrollspy'),
        required: true,
        choices: [
            'off' => new Translatable('fluid_block_richtext_scrollspy_off'),
            'mark' => new Translatable('fluid_block_richtext_scrollspy_mark'),
            'progress' => new Translatable('fluid_block_richtext_scrollspy_progress'),
        ],
        width: 'half',
        variants: ['article'],
        note: new Translatable('fluid_block_richtext_scrollspy_help'),
    )]
    public string $scrollspy = 'off';
}
