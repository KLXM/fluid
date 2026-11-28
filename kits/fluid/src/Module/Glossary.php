<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.glossary', new Translatable('fluid_block_glossary'))]
#[AsFluidBlock(
    icon: 'book-open',
    group: new Translatable('fluid_block_group_data'),
    help: new Translatable('fluid_block_glossary_help'),
    requires: 'yform',
)]
final class Glossary extends FluidBlock
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

    #[Checkbox(
        new Translatable('fluid_block_glossary_search'),
        width: 'half',
    )]
    public bool $search = true;

    #[Checkbox(
        new Translatable('fluid_block_glossary_letters'),
        width: 'half',
    )]
    public bool $letters = true;
}
