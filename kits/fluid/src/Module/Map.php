<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Geo;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Maps\Maps;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.map', new Translatable('fluid_block_map'))]
#[AsFluidBlock(
    icon: 'map-trifold',
    group: new Translatable('fluid_block_group_website'),
    help: new Translatable('fluid_block_map_help'),
    nestable: true,
)]
final class Map extends FluidBlock
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

    #[Choice(
        new Translatable('fluid_block_map_location'),
        required: true,
        choices: ['site' => new Translatable('fluid_block_map_location_site'), 'custom' => new Translatable('fluid_block_map_location_custom')],
    )]
    public string $location = 'site';

    #[Geo(
        new Translatable('fluid_block_map_point'),
        note: new Translatable('fluid_block_map_point_help'),
    )]
    public string $point = '';

    #[Text(
        new Translatable('fluid_block_map_label'),
        placeholder: new Translatable('fluid_block_map_label_placeholder'),
        width: 'half',
    )]
    public string $label = '';

    #[Text(
        new Translatable('fluid_block_map_address'),
        placeholder: new Translatable('fluid_block_map_address_placeholder'),
        width: 'half',
    )]
    public string $address = '';

    #[Choice(
        new Translatable('fluid_block_map_zoom'),
        required: true,
        choices: [
            11 => new Translatable('fluid_block_map_zoom_11'),
            13 => new Translatable('fluid_block_map_zoom_13'),
            15 => new Translatable('fluid_block_map_zoom_15'),
            16 => '16',
            17 => new Translatable('fluid_block_map_zoom_17'),
        ],
        width: 'half',
    )]
    public string $zoom = '15';

    #[Choice(
        new Translatable('fluid_field_height'),
        required: true,
        choices: static function (): array { return Maps::choices('height'); },
        width: 'half',
    )]
    public string $height = 'm';

    #[Choice(
        new Translatable('fluid_block_map_style'),
        choices: static function (): array { return Maps::choices('style'); },
        width: 'half',
    )]
    public string $style = 'liberty';

    #[Checkbox(
        new Translatable('fluid_block_map_click'),
        width: 'half',
    )]
    public bool $click = false;

    #[Checkbox(new Translatable('fluid_block_map_route'))]
    public bool $route = true;
}
