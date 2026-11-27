<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Data\YFormBridge;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Felder des eigenen Formulars“ im Block Form */
final class FormField
{
    #[Choice(
        new Translatable('fluid_field_kind'),
        required: true,
        choices: static function (): array { return YFormBridge::builderTypes(); },
        width: 'half',
    )]
    public string $type = 'text';

    #[Checkbox(
        new Translatable('fluid_block_form_item_required'),
        width: 'half',
    )]
    public bool $required = false;

    #[Text(
        new Translatable('fluid_block_form_item_label'),
        maxLength: 300,
    )]
    public string $label = '';

    #[Textarea(
        new Translatable('fluid_block_form_item_options'),
        rows: 3,
    )]
    public string $options = '';

    #[Text(
        new Translatable('fluid_block_form_item_placeholder'),
        maxLength: 120,
        width: 'half',
    )]
    public string $placeholder = '';

    #[Text(
        new Translatable('fluid_block_form_item_help'),
        maxLength: 200,
        width: 'half',
    )]
    public string $help = '';

    #[Text(
        new Translatable('fluid_block_form_item_min'),
        note: new Translatable('fluid_block_form_item_min_help'),
        maxLength: 20,
        width: 'half',
    )]
    public string $min = '';

    #[Text(
        new Translatable('fluid_block_form_item_max'),
        note: new Translatable('fluid_block_form_item_max_help'),
        maxLength: 20,
        width: 'half',
    )]
    public string $max = '';

    #[Text(
        new Translatable('fluid_block_form_item_file_types'),
        maxLength: 80,
        width: 'half',
        placeholder: new Translatable('fluid_block_form_item_file_types_placeholder'),
    )]
    public string $fileTypes = '';

    #[Choice(
        new Translatable('fluid_block_form_item_max_size'),
        choices: YFormBridge::UPLOAD_SIZES,
        width: 'half',
    )]
    public string $maxSize = '5M';

    #[Text(
        new Translatable('fluid_block_form_item_name'),
        maxLength: 40,
        placeholder: new Translatable('fluid_block_form_item_name_placeholder'),
        search: false,
    )]
    public string $name = '';
}
