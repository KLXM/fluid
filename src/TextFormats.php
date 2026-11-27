<?php

namespace Klxm\Fluid;

use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function preg_split;
use function trim;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Text formats of a kit: classes for rich text that the kit's CSS styles – paragraph and heading styles, text colors,
 * list and link formats. The text editor offers exactly these ({@see Tiptap\FluidProfile}), and the output keeps only
 * these ({@see RichText::html()}).
 *
 * Optional in kit.php under `text`, class => label. A kit inherits its parent kit's formats (`fluid`) and adds to
 * them; `false` removes an inherited one:
 *
 *     'text' => [
 *         'paragraphs' => ['ncss-text-lead' => 'Einleitung'],
 *         'headings' => ['ncss-text-gradient-brand' => 'Farbverlauf'],       // any heading level
 *         'colors' => ['ncss-text-brand' => 'Markenfarbe'],
 *         'lists' => ['ncss-list--dot' => 'Punkte', 'ncss-list--steps' => ['label' => 'Schritte', 'list' => 'ol']],
 *         'links' => ['btn btn--primary' => false, 'ncss-btn ncss-btn--primary' => 'Button'],
 *     ],
 *
 * Lists apply to `ul` and `ol` unless `list` says otherwise.
 */
final readonly class TextFormats
{
    public const array GROUPS = ['paragraphs', 'headings', 'colors', 'lists', 'links'];

    /**
     * @param array<string, string> $paragraphs class => label
     * @param array<string, string> $headings class => label
     * @param array<string, string> $colors class => label
     * @param array<string, array{label: string, list: 'ul'|'ol'|'both'}> $lists class => format
     * @param array<string, string> $links class => label
     */
    public function __construct(
        public array $paragraphs = [],
        public array $headings = [],
        public array $colors = [],
        public array $lists = [],
        public array $links = [],
    ) {}

    /** Formats of the website's kit */
    public static function active(): self
    {
        return self::of(Kit::active());
    }

    public static function of(Kit $kit): self
    {
        /** @var array<string, self> $cache */
        static $cache = [];

        return $cache[$kit->key()] ??= self::fromArray($kit->definition()['text'] ?? []);
    }

    /** From kit.php `text` (already merged with the parent kit): invalid classes and removed entries are skipped */
    public static function fromArray(mixed $text): self
    {
        $text = is_array($text) ? $text : [];
        $groups = [];
        foreach (self::GROUPS as $group) {
            $groups[$group] = [];
            foreach (is_array($text[$group] ?? null) ? $text[$group] : [] as $class => $entry) {
                $class = self::classList((string) $class);
                $label = is_array($entry) ? ($entry['label'] ?? null) : $entry;
                if ('' === $class || !is_string($label) || '' === trim($label)) {
                    continue;
                }
                if ('lists' === $group) {
                    $list = is_array($entry) ? ($entry['list'] ?? 'both') : 'both';
                    $groups[$group][$class] = ['label' => trim($label), 'list' => in_array($list, ['ul', 'ol'], true) ? $list : 'both'];
                } else {
                    $groups[$group][$class] = trim($label);
                }
            }
        }

        /** @var array<string, string> $paragraphs */
        $paragraphs = $groups['paragraphs'];
        /** @var array<string, string> $headings */
        $headings = $groups['headings'];
        /** @var array<string, string> $colors */
        $colors = $groups['colors'];
        /** @var array<string, array{label: string, list: 'ul'|'ol'|'both'}> $lists */
        $lists = $groups['lists'];
        /** @var array<string, string> $links */
        $links = $groups['links'];

        return new self($paragraphs, $headings, $colors, $lists, $links);
    }

    /**
     * Settings for the TipTap profile: block, text, list and link formats.
     *
     * @return array{blockStyles: list<array{label: string, class: string, element: string}>, inlineStyles: list<array{label: string, class: string}>, listStyles: list<array{label: string, class: string, list: string}>, linkStyles: list<array{label: string, class: string}>}
     */
    public function profileConfig(): array
    {
        $block = [];
        foreach ($this->paragraphs as $class => $label) {
            $block[] = ['label' => $label, 'class' => $class, 'element' => 'p'];
        }
        foreach ($this->headings as $class => $label) {
            $block[] = ['label' => $label, 'class' => $class, 'element' => 'heading'];
        }
        $pairs = static function (array $styles): array {
            $out = [];
            foreach ($styles as $class => $label) {
                $out[] = ['label' => $label, 'class' => (string) $class];
            }

            return $out;
        };
        $lists = [];
        foreach ($this->lists as $class => $list) {
            $lists[] = ['label' => $list['label'], 'class' => $class, 'list' => $list['list']];
        }

        return ['blockStyles' => $block, 'inlineStyles' => $pairs($this->colors), 'listStyles' => $lists, 'linkStyles' => $pairs($this->links)];
    }

    /**
     * Classes the output keeps on an element (single class names).
     *
     * @return list<string>
     */
    public function allowed(string $element): array
    {
        $classes = match ($element) {
            'p' => array_keys($this->paragraphs),
            'h2', 'h3', 'h4' => array_keys($this->headings),
            'span' => array_keys($this->colors),
            'ul', 'ol' => array_keys(array_filter($this->lists, static fn (array $l): bool => 'both' === $l['list'] || $element === $l['list'])),
            'a' => array_keys($this->links),
            default => [],
        };

        return array_values(array_unique(array_merge([], ...array_map(static fn (string $c): array => explode(' ', $c), $classes))));
    }

    /** Space-separated CSS class names, empty if one is invalid */
    private static function classList(string $value): string
    {
        $classes = preg_split('~\s+~', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($classes as $class) {
            if (!preg_match('~^-?[_a-zA-Z][_a-zA-Z0-9-]*$~', $class)) {
                return '';
            }
        }

        return implode(' ', $classes);
    }
}
