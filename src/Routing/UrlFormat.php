<?php

namespace Klxm\Fluid\Routing;

use InvalidArgumentException;

use function in_array;
use function preg_match;
use function sprintf;
use function str_ends_with;
use function strlen;
use function strtolower;
use function substr;

/**
 * URL suffix. Paths are always stored without a suffix – the format is added only when generating and removed again
 * when resolving. Switching therefore needs no rebuild: old spellings redirect with 301.
 *
 *     UrlFormat::none()            /leistungen/beratung          (default)
 *     UrlFormat::slash()           /leistungen/beratung/
 *     UrlFormat::html()            /leistungen/beratung.html
 *     UrlFormat::mixed()           /leistungen/  ·  /leistungen/beratung.html   (menu items with „/“, pages with „.html“)
 *     UrlFormat::suffix('.htm')    custom suffix
 *
 * Start pages (/ and /en/) never have a suffix. For entirely custom rules, extend the class (append/strip).
 */
readonly class UrlFormat
{
    /** Suffixes that are always recognized when resolving (for switching from or to .html) */
    public const array KNOWN = ['.html', '.htm'];

    /**
     * @param string $page suffix of pages: '' (none), '/' or '.' + letters/digits
     * @param string|null $category suffix of menu items (start article of a category); null = like pages
     */
    public function __construct(
        public string $page = '',
        public ?string $category = null,
    ) {
        foreach ([$page, $category] as $s) {
            if (null !== $s && !in_array($s, ['', '/'], true) && !preg_match('~^\.[a-z0-9]{1,10}$~', $s)) {
                throw new InvalidArgumentException(sprintf('Ungültige Endung „%s“: erlaubt sind "", "/" oder z. B. ".html".', $s));
            }
        }
    }

    public static function none(): self
    {
        return new self();
    }

    public static function slash(): self
    {
        return new self('/');
    }

    public static function html(): self
    {
        return new self('.html');
    }

    public static function mixed(): self
    {
        return new self('.html', '/');
    }

    public static function suffix(string $suffix): self
    {
        return new self($suffix);
    }

    /** Path (without outer slashes, not empty) with suffix */
    public function append(string $path, bool $category = false): string
    {
        return '' === $path ? '' : $path . ($category ? $this->category ?? $this->page : $this->page);
    }

    /** Path without trailing slash and without a known suffix – key for the lookup */
    public function strip(string $path): string
    {
        $path = rtrim($path, '/');
        $lower = strtolower($path);
        foreach ([$this->page, (string) $this->category, ...self::KNOWN] as $s) {
            if (strlen($s) > 1 && str_ends_with($lower, $s)) {
                return substr($path, 0, -strlen($s));
            }
        }

        return $path;
    }
}
