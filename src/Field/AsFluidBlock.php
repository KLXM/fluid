<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Translation\Translatable;

/**
 * Block settings on the class – the fields are attributes on the properties (see {@see Field}).
 *
 *     #[AsModule('fluid.hinweis', 'Hinweis')]
 *     #[AsFluidBlock(icon: 'info', group: 'Inhalt', nestable: true, variants: ['info' => 'Information', 'warn' => 'Warnung'])]
 *     final class Hinweis extends FluidBlock { … }
 *
 * Label: name of the module (AsModule), unless `label:` is given. Texts for editors (group, help, label, variants,
 * variant help, labels of drags) are given directly or as translation key (`new Translatable('my_label')`).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsFluidBlock
{
    public function __construct(
        public readonly string $icon = 'square',
        /** Group in the block selection (Kopf, Inhalt, Website, Daten … – see lang keys fluid_block_group_*) */
        public readonly string|Translatable $group = new Translatable('fluid_block_group_content'),
        public readonly string|Translatable|null $help = null,
        public readonly string|Translatable|null $label = null,
        /** @var array<string, string|Translatable> Variants: key => label (first = default) */
        public readonly array $variants = [],
        /** @var array<string, string|Translatable> When to use which variant? (manual) */
        public readonly array $variantHelp = [],
        /** @var bool|list<string> Allowed in a column of the block „Layout“ – entirely or only these variants */
        public readonly bool|array $nestable = false,
        /** @var list<array<string, string|Translatable>> Handles on the page (field, target, class, apply, label) */
        public readonly array $drags = [],
        /** Required addon (e.g. 'yform') – otherwise the block is missing from the selection */
        public readonly ?string $requires = null,
        /** Update part of the page without reloading (paging, filters; Live::region) */
        public readonly bool $reload = false,
        /** Background of new blocks */
        public readonly ?string $background = null,
        /** @var array<string, string>|null Strukturierte Daten (schema.org), z. B. ['type' => 'faq', …] */
        public readonly ?array $jsonld = null,
        /** @var bool|list<string> List templates (Data\ListTemplates): true or list of further fields that stay with the block */
        public readonly bool|array $listTemplate = false,
    ) {}
}
