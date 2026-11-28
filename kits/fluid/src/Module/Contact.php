<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Field\YFormForm;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.contact', new Translatable('fluid_block_contact'))]
#[AsFluidBlock(
    label: new Translatable('fluid_block_contact_label'),
    icon: 'address-book',
    group: new Translatable('fluid_block_group_website'),
    help: new Translatable('fluid_block_contact_help'),
)]
final class Contact extends FluidBlock
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
        new Translatable('fluid_block_contact_show_hours'),
        width: 'half',
    )]
    public bool $showHours = true;

    #[Checkbox(
        new Translatable('fluid_block_contact_show_map'),
        width: 'half',
    )]
    public bool $showMap = true;

    #[YFormForm(
        new Translatable('fluid_block_contact_form'),
        note: new Translatable('fluid_block_contact_form_help'),
    )]
    public string $form = '';

    #[Text(
        new Translatable('fluid_block_contact_form_title'),
        maxLength: 80,
        placeholder: new Translatable('fluid_block_contact_form_title_placeholder'),
    )]
    public string $formTitle = '';

    #[Text(new Translatable('fluid_block_contact_note'))]
    public string $note = '';
}
