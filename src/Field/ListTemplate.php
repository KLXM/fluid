<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Text as CoreText;
use Redaxo\Core\View\Renderable;

/**
 * Listen-Vorlage eines Listenblocks (Data\ListTemplates): Schlüssel der gewählten Vorlage, leer = eigene Einstellungen.
 * In fluid als Kacheln mit Vorschau je Vorlage der gewählten Tabelle; der Block braucht dazu #[AsBlock(listTemplate: true)].
 *
 * Nur in fluid (eigener Name). In gewöhnlichen Formularen des Cores verhält es sich wie ein Textfeld.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ListTemplate extends CoreText implements FluidField
{
    public function __construct(
        string|Renderable $label,
        string|Renderable|null $note = null,
        ?string $key = null,
    ) {
        parent::__construct($label, $note, false, $key);
    }

    public function fluid(): array
    {
        return ['type' => 'listtemplate'];
    }
}
