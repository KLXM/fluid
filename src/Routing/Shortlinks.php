<?php

namespace Klxm\Fluid\Routing;

use DateTimeImmutable;
use InvalidArgumentException;
use Klxm\Fluid\Routing\ExtensionPoint\ShortlinkAdded;
use Klxm\Fluid\Routing\ExtensionPoint\ShortlinkDeleted;
use Klxm\Fluid\Routing\ExtensionPoint\ShortlinkPath;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;

use function strlen;

/**
 * Short addresses such as /sommerfest or /k7m2p for print, social media and campaigns. They are redirect rules with
 * origin "short" and code 302 – the target can change later and every click is counted.
 *
 * @internal
 */
final class Shortlinks
{
    public const string ORIGIN = 'short';

    /** Without look-alikes (0/o, 1/l/i) – codes are typed from print */
    private const string ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** @return list<Shortlink> shortlinks to this target (e.g. redaxo://5@1), newest first */
    public static function forTarget(string $target): array
    {
        $rows = Sql::factory()->getArray('SELECT * FROM ' . Redirects::TABLE . ' WHERE origin = ? AND target = ? ORDER BY id DESC', [self::ORIGIN, $target]);

        return array_map(Shortlink::fromRow(...), $rows);
    }

    /**
     * @param string $path wished address ("Sommerfest 2027" → /sommerfest-2027); empty = a free random code
     * @throws InvalidArgumentException if the address is taken by a page or another rule
     */
    public static function create(string $path, string $target, ?DateTimeImmutable $expires = null, string $note = ''): Shortlink
    {
        $generated = '' === trim($path, " /\t");
        $path = $generated ? self::freeCode() : '/' . Routes::slugifyPath($path);
        $path = Redirects::normalize((string) Extension::dispatch(new ShortlinkPath($path, $target, $generated)));
        if ('/' === $path || !self::isFree($path)) {
            throw new InvalidArgumentException($path);
        }
        $id = Redirects::save(['source' => $path, 'target' => $target, 'code' => 302, 'note' => $note, 'origin' => self::ORIGIN]);
        Sql::factory()->setQuery('UPDATE ' . Redirects::TABLE . ' SET expires = ? WHERE id = ?', [$expires?->format('Y-m-d 23:59:59'), $id]);

        $link = Shortlink::fromRow(Sql::factory()->getArray('SELECT * FROM ' . Redirects::TABLE . ' WHERE id = ?', [$id])[0]);
        Extension::dispatch(new ShortlinkAdded($link));

        return $link;
    }

    /** Shortlink of this target at this address (ids change in a rolled back demo, addresses do not) */
    public static function find(string $path, string $target): ?Shortlink
    {
        $row = Sql::factory()->getArray('SELECT * FROM ' . Redirects::TABLE . ' WHERE origin = ? AND target = ? AND source_hash = ? AND wildcard = 0 LIMIT 1',
            [self::ORIGIN, $target, sha1(Redirects::key(Redirects::normalize($path)))])[0] ?? null;

        return null !== $row ? Shortlink::fromRow($row) : null;
    }

    /** Deletes a shortlink of this target (only shortlinks, only of this target) */
    public static function delete(int $id, string $target): void
    {
        $row = Sql::factory()->getArray('SELECT * FROM ' . Redirects::TABLE . ' WHERE id = ? AND origin = ? AND target = ?', [$id, self::ORIGIN, $target])[0] ?? null;
        if (null === $row) {
            return;
        }
        Sql::factory()->setQuery('DELETE FROM ' . Redirects::TABLE . ' WHERE id = ?', [$id]);
        Extension::dispatch(new ShortlinkDeleted(Shortlink::fromRow($row)));
    }

    /** Neither a page, an entry nor an existing rule */
    public static function isFree(string $path): bool
    {
        [$lang, $rest] = Routes::splitLanguage($path);
        $rest = Router::config()->format->strip($rest);
        if ('' === $rest || null !== Routes::find($rest, $lang) || null !== Routes::findEntry($rest, $lang)) {
            return false;
        }

        return !Redirects::exists($path);
    }

    private static function freeCode(): string
    {
        for ($length = 5;; ++$length) {
            for ($try = 0; $try < 10; ++$try) {
                $code = '/';
                for ($i = 0; $i < $length; ++$i) {
                    $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
                }
                if (self::isFree($code)) {
                    return $code;
                }
            }
        }
    }
}
