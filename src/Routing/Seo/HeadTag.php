<?php

namespace Klxm\Fluid\Routing\Seo;

use function implode;
use function in_array;
use function Redaxo\Core\View\escape;

/**
 * A tag in the page head. Tags with the same key replace each other – so an AddOn or the project can override any value
 * of the routing (e.g. og:title) or add its own:
 *
 *     HeadTag::meta('theme-color', '#0a5')
 *     HeadTag::property('og:video', 'https://…')
 *     HeadTag::link('preconnect', 'https://fonts.example.org')
 *     HeadTag::html('matomo', '<script src="…" defer></script>')   only from code, never from editor input
 */
final readonly class HeadTag
{
    /** link types that exist only once per page (key without URL) */
    private const array UNIQUE_LINKS = ['canonical', 'icon', 'apple-touch-icon', 'manifest', 'license', 'author'];

    /** @param array<string, string|int|bool|null> $attrs */
    private function __construct(
        public string $key,
        public string $tag,
        public array $attrs = [],
        public ?string $html = null,
    ) {}

    public static function meta(string $name, string $content): self
    {
        return new self('name:' . $name, 'meta', ['name' => $name, 'content' => $content]);
    }

    public static function property(string $property, string $content): self
    {
        return new self('property:' . $property, 'meta', ['property' => $property, 'content' => $content]);
    }

    /** @param array<string, string|int|bool|null> $attrs weitere Attribute (hreflang, type, title, sizes …) */
    public static function link(string $rel, string $href, array $attrs = []): self
    {
        $key = 'link:' . $rel . (isset($attrs['hreflang']) ? ':' . $attrs['hreflang'] : (in_array($rel, self::UNIQUE_LINKS, true) ? '' : ':' . $href));

        return new self($key, 'link', ['rel' => $rel, 'href' => $href] + $attrs);
    }

    /** Ready-made HTML under its own key – only for code (project, AddOns), not for input */
    public static function html(string $key, string $html): self
    {
        return new self('html:' . $key, 'html', [], $html);
    }

    /** Remove a tag: the same key, empty content (e.g. HeadTag::remove('property:og:image')) */
    public static function remove(string $key): self
    {
        return new self($key, 'remove');
    }

    public function render(): string
    {
        if ('html' === $this->tag) {
            return (string) $this->html;
        }
        if ('remove' === $this->tag) {
            return '';
        }
        $a = [];
        foreach ($this->attrs as $k => $v) {
            if (null === $v || false === $v) {
                continue;
            }
            $a[] = true === $v ? escape($k) : escape($k) . '="' . escape((string) $v) . '"';
        }

        return '<' . $this->tag . ' ' . implode(' ', $a) . '>';
    }
}
