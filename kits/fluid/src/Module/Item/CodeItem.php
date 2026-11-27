<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Dateien“ im Block Code */
final class CodeItem
{
    #[Text(
        new Translatable('fluid_block_code_item_file'),
        maxLength: 120,
        placeholder: new Translatable('fluid_block_code_item_file_placeholder'),
        width: 'half',
    )]
    public string $file = '';

    #[Choice(
        new Translatable('fluid_block_code_item_lang'),
        required: true,
        choices: [
            'php' => 'PHP',
            'bash' => 'Terminal',
            'env' => '.env',
            'json' => 'JSON',
            'yaml' => 'YAML',
            'html' => 'HTML',
            'css' => 'CSS',
            'js' => 'JavaScript',
            'text' => new Translatable('fluid_field_text'),
        ],
        width: 'half',
    )]
    public string $lang = 'php';

    #[Textarea(
        new Translatable('fluid_block_code'),
        required: true,
        maxLength: 12000,
        rows: 12,
        search: false,
    )]
    public string $code = '';
}
