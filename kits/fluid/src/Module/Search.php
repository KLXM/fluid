<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Number;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.search', new Translatable('fluid_block_search'))]
#[AsBlock(
    icon: 'magnifying-glass',
    group: new Translatable('fluid_block_group_website'),
    help: new Translatable('fluid_block_search_help'),
    reload: true,
)]
final class Search extends FluidBlock
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

    #[Text(
        new Translatable('fluid_block_search_placeholder'),
        maxLength: 60,
        placeholder: new Translatable('fluid_block_search_placeholder_placeholder'),
        width: 'half',
    )]
    public string $placeholder = '';

    #[Number(
        new Translatable('fluid_block_search_limit'),
        width: 'half',
    )]
    public int $limit = 20;

    #[Text(
        new Translatable('fluid_block_search_empty_text'),
        maxLength: 200,
        placeholder: new Translatable('fluid_block_search_empty_text_placeholder'),
    )]
    public string $emptyText = '';
}
