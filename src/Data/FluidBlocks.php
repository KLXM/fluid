<?php

namespace Klxm\Fluid\Data;

use Klxm\Fluid\BlockData;
use Override;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Yakamara\YForm\Data\Record;
use Yakamara\YForm\Field\Field;
use Yakamara\YForm\Form\FieldContext;

use function count;
use function is_array;
use function is_string;
use function Redaxo\Core\View\escape;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

// Only with YForm (fluid also runs without)
if (!class_exists(Field::class)) {
    return;
}

/**
 * YForm field „Fluid-Blöcke“: content from the blocks of fluid – in any YForm table.
 *
 *     yield FluidBlocks::new('inhalt')->label('Inhalt');
 *     yield FluidBlocks::new('inhalt')->label('Inhalt')->blocks('richtext', 'media_text', 'video');   // only these blocks
 *
 * Stored as JSON (longtext): [{module, data, tunes}, …]. Edited in the YForm form with the same fields as on the
 * website (media pool, linkmap, text editor). Output: `BlockData::render($record->get('inhalt'))` – the data list's
 * detail view does this automatically. There are no layout columns here (they need slices).
 */
final class FluidBlocks extends Field
{
    /** @var list<string>|null */
    public protected(set) ?array $allowed = null;

    #[Override]
    protected function init(): void
    {
        $this->listed = false;
        $this->filterable = true;   // Search of the list in the backend: text of the blocks
    }

    /** Offer only these blocks (type like „richtext“ or module key like „fluid.richtext“) */
    public function blocks(string ...$types): static
    {
        $this->allowed = $types;

        return $this;
    }

    #[Override]
    protected function columnType(): string
    {
        return 'longtext';
    }

    #[Override]
    public function fromDb(mixed $raw): mixed
    {
        return BlockData::decode(null === $raw ? null : (string) $raw);
    }

    #[Override]
    public function toDb(mixed $value): array
    {
        return [$this->name => BlockData::encode(is_array($value) ? $value : [])];
    }

    #[Override]
    public function fromInput(mixed $input): mixed
    {
        return is_string($input) ? BlockData::decode(json_decode($input, true)) : [];
    }

    #[Override]
    public function textValue(mixed $value): string
    {
        return BlockData::text($value);
    }

    #[Override]
    public function listValue(mixed $value, Record $record): string
    {
        $n = count(BlockData::decode($value));

        return 0 === $n ? '' : escape($n . ' ' . (1 === $n ? 'Block' : 'Blöcke'));
    }

    #[Override]
    public function render(FieldContext $context): string
    {
        $blocks = BlockData::decode($context->value);
        $config = [
            'api' => Url::backendController(['page' => Request::get('page', 'string', 'yform/data')] + BlocksApi::getUrlParams()),
            'catalog' => BlockData::catalog($this->allowed),
            'labels' => array_map(BlockData::label(...), $blocks),
        ];

        return '<div class="fluid-blocks-field" data-fluid-blocks="' . BlockData::attr($config) . '">'
            . '<input type="hidden" ' . $this->controlAttributes($context, ['value' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], false) . '>'
            . '<ol class="fbf-list" data-fbf-list></ol>'
            . '<div class="fbf-add"><div class="rex-input-group"><select class="rex-input" data-fbf-type aria-label="Blocktyp"></select><span class="rex-input-group-actions"><button type="button" class="rex-button" data-variant="field" data-fbf-add>Block hinzufügen</button></span></div></div>'
            . '</div>';
    }
}
