<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Data\YFormBridge;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;

/** Eintrag von „Felder des eigenen Formulars“ im Block Form */
final class FormField
{
    #[Choice(
        'Art',
        required: true,
        choices: static function (): array { return YFormBridge::builderTypes(); },
        width: 'half',
    )]
    public string $type = 'text';

    #[Checkbox(
        'Pflichtfeld',
        width: 'half',
    )]
    public bool $required = false;

    #[Text(
        'Bezeichnung (bei „Neuer Schritt“: Titel, bei Hinweis: Text)',
        maxLength: 300,
    )]
    public string $label = '';

    #[Textarea(
        'Auswahl: eine Möglichkeit je Zeile',
        rows: 3,
    )]
    public string $options = '';

    #[Text(
        'Platzhalter (optional)',
        maxLength: 120,
        width: 'half',
    )]
    public string $placeholder = '';

    #[Text(
        'Hilfetext (optional)',
        maxLength: 200,
        width: 'half',
    )]
    public string $help = '';

    #[Text(
        'Mindestens (optional)',
        note: 'Text: Zeichen, Zahl: Wert, Datum: z. B. 2026-01-01 oder today',
        maxLength: 20,
        width: 'half',
    )]
    public string $min = '';

    #[Text(
        'Höchstens (optional)',
        note: 'Text: Zeichen, Zahl: Wert, Datum: z. B. +1 year',
        maxLength: 20,
        width: 'half',
    )]
    public string $max = '';

    #[Text(
        'Datei: erlaubte Typen',
        maxLength: 80,
        width: 'half',
        placeholder: 'pdf, jpg, png, docx …',
    )]
    public string $fileTypes = '';

    #[Choice(
        'Datei: höchstens',
        choices: YFormBridge::UPLOAD_SIZES,
        width: 'half',
    )]
    public string $maxSize = '5M';

    #[Text(
        'Feldname (optional, für eine YForm-Tabelle)',
        maxLength: 40,
        placeholder: 'automatisch aus der Bezeichnung',
        search: false,
    )]
    public string $name = '';
}
