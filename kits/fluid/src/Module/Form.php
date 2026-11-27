<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Field\YFormForm;
use Klxm\Fluid\Field\YFormTable;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\FormField;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.form', new Translatable('fluid_block_form'))]
#[AsBlock(
    icon: 'envelope-simple',
    group: new Translatable('fluid_block_group_data'),
    help: new Translatable('fluid_block_form_help'),
    requires: 'yform',
    variants: ['single' => new Translatable('fluid_block_form_variant_single'), 'split' => new Translatable('fluid_block_form_variant_split')],
    nestable: true,
)]
final class Form extends FluidBlock
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

    #[YFormForm(
        new Translatable('fluid_block_form_form'),
        note: new Translatable('fluid_block_form_form_help'),
    )]
    public string $form = '';

    #[Collection(
        new Translatable('fluid_block_form_fields'),
        item: FormField::class,
        itemLabel: new Translatable('fluid_block_form_item'),
        titleField: 'label',
        note: new Translatable('fluid_block_form_fields_help'),
    )]
    public array $fields = [
        ['type' => 'text', 'label' => 'Name', 'required' => true],
        ['type' => 'email', 'label' => 'E-Mail', 'required' => true],
        ['type' => 'textarea', 'label' => 'Nachricht', 'required' => true],
        [
            'type' => 'checkbox',
            'label' => 'Ich bin mit der Verarbeitung meiner Angaben zur Beantwortung einverstanden.',
            'name' => 'datenschutz',
            'required' => true,
        ],
    ];

    #[Choice(
        new Translatable('fluid_block_form_mode'),
        required: true,
        choices: [
            'save_mail' => new Translatable('fluid_block_form_mode_save_mail'),
            'save' => new Translatable('fluid_block_form_mode_save'),
            'mail' => new Translatable('fluid_block_form_mode_mail'),
        ],
        width: 'half',
    )]
    public string $mode = 'save_mail';

    #[Text(
        new Translatable('fluid_block_form_mail_to'),
        maxLength: 300,
        placeholder: 'info@example.org',
        width: 'half',
    )]
    public string $mailTo = '';

    #[Text(
        new Translatable('fluid_block_form_mail_subject'),
        maxLength: 150,
        placeholder: new Translatable('fluid_block_form_mail_subject_placeholder'),
        width: 'half',
    )]
    public string $mailSubject = '';

    #[YFormTable(
        new Translatable('fluid_block_form_target'),
        width: 'half',
        note: new Translatable('fluid_block_form_target_help'),
    )]
    public string $target = '';

    #[Checkbox(
        new Translatable('fluid_block_form_confirm'),
        width: 'half',
    )]
    public bool $confirm = false;

    #[Checkbox(
        new Translatable('fluid_block_form_confirm_copy'),
        width: 'half',
    )]
    public bool $confirmCopy = false;

    #[Text(
        new Translatable('fluid_block_form_confirm_subject'),
        maxLength: 150,
        placeholder: new Translatable('fluid_block_form_confirm_subject_placeholder'),
    )]
    public string $confirmSubject = '';

    #[Textarea(
        new Translatable('fluid_block_form_confirm_text'),
        rows: 3,
        placeholder: new Translatable('fluid_block_form_confirm_text_placeholder'),
    )]
    public string $confirmText = '';

    #[Text(
        new Translatable('fluid_block_form_submit'),
        maxLength: 40,
        placeholder: new Translatable('fluid_block_form_submit_placeholder'),
        width: 'half',
    )]
    public string $submit = '';

    #[Text(
        new Translatable('fluid_block_form_form_name'),
        maxLength: 80,
        placeholder: new Translatable('fluid_block_form_form_name_placeholder'),
        width: 'half',
    )]
    public string $formName = '';

    #[Textarea(
        new Translatable('fluid_block_form_success'),
        maxLength: 600,
        rows: 2,
        placeholder: new Translatable('fluid_block_form_success_placeholder'),
    )]
    public string $success = '';

    #[Link(new Translatable('fluid_block_form_redirect'))]
    public string $redirect = '';

    #[Text(
        new Translatable('fluid_block_form_first_step'),
        maxLength: 80,
    )]
    public string $firstStep = '';

    #[Textarea(
        new Translatable('fluid_block_form_note'),
        maxLength: 400,
        rows: 2,
        note: new Translatable('fluid_block_form_note_help'),
    )]
    public string $note = '';
}
