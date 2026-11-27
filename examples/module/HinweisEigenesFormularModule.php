<?php

namespace Project\Module;

use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Form\Field\Textarea;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Response;

use function Redaxo\Core\View\escape;

/**
 * Example: module with its own input() – the module builds the form itself. For this fluid shows the core's form
 * in the sidebar (the same form as in the backend).
 */
#[AsModule('hinweis_eigen', 'Hinweis mit eigenem Formular (Beispiel)')]
final class HinweisEigenesFormularModule extends Module
{
    #[Text('Titel', required: true, maxLength: 120)]
    public string $title = '';

    #[Textarea('Text', rows: 3)]
    public string $text = '';

    public function input(Form $form): string
    {
        return '<p class="rex-field-note">Eigener Aufbau: Hinweis über den Feldern, danach alle Felder.</p>' . $form->rest();
    }

    public function output(): string
    {
        // No style attributes (the backend's Content Security Policy): a class and one <style nonce> per page
        static $css = false;
        $style = $css ? '' : '<style nonce="' . escape(Response::getNonce()) . '">.hinweis-eigen{margin-block:2rem;padding:1.25rem 2rem;border:2px dashed var(--f-line-strong,#94a3b8);border-radius:var(--f-radius,6px)}.hinweis-eigen p{margin:.5rem 0 0}</style>';
        $css = true;

        return $style . '<div class="wrap"><aside class="hinweis-eigen">'
            . '<strong>' . escape($this->title) . '</strong>' . ('' !== $this->text ? '<p>' . nl2br(escape($this->text)) . '</p>' : '')
            . '</aside></div>';
    }
}
