<?php

namespace Klxm\Fluid\Data;

use Override;
use Redaxo\Core\Language\Language;
use Yakamara\YForm\Field\Choice;

// Only with YForm (fluid also runs without it)
if (!class_exists(Choice::class)) {
    return;
}

/**
 * YForm field „Sprache“ (modeled on KLXM Studio: entry with language): fluid's data list shows only entries of the
 * current language – and those without language („Alle Sprachen“, also all entries from before the field).
 *
 *     yield FluidLanguage::new('lang');
 *
 * The language code is stored (de, en …). Translating means: creating the entry in the other language.
 */
final class FluidLanguage extends Choice
{
    #[Override]
    protected function init(): void
    {
        parent::init();
        $this->label('Sprache');
        $this->default('');
        $this->filterable();   // list search in the backend: filter by language
        $this->options(static function (): array {
            $out = ['' => 'Alle Sprachen'];
            foreach (Language::getAll() as $l) {
                $out[$l->code] = $l->name;
            }

            return $out;
        });
    }
}
