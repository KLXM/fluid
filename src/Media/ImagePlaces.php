<?php

namespace Klxm\Fluid\Media;

use function array_key_exists;
use function array_pop;
use function count;
use function explode;
use function is_array;
use function is_string;
use function str_starts_with;

/**
 * Maps the images of a block to their field paths (`image`, `items.2.image`) and keeps the per-use settings
 * ({@see ImagePlace}) in the block data under `_img`.
 *
 * Rendering a block calls enter()/leave(); img() asks take() for the next occurrence of a file.
 *
 * @internal
 */
final class ImagePlaces
{
    public const string KEY = '_img';

    /** @var list<array{data: array<array-key, mixed>, used: array<string, int>}> */
    private static array $stack = [];

    /** @param array<array-key, mixed> $data */
    public static function enter(array $data): void
    {
        self::$stack[] = ['data' => $data, 'used' => []];
    }

    public static function leave(): void
    {
        array_pop(self::$stack);
    }

    /**
     * Path and settings of the next occurrence of the file in the block being rendered.
     *
     * @return array{path: string, place: ?ImagePlace}|null
     */
    public static function take(string $file): ?array
    {
        $top = count(self::$stack) - 1;
        if ($top < 0 || '' === $file) {
            return null;
        }
        $n = self::$stack[$top]['used'][$file] ?? 0;
        $path = self::paths(self::$stack[$top]['data'], $file)[$n] ?? null;
        if (null === $path) {
            return null;
        }
        self::$stack[$top]['used'][$file] = $n + 1;

        return ['path' => $path, 'place' => self::of(self::$stack[$top]['data'], $path, $file)];
    }

    /** Settings of the next occurrence without consuming it (e.g. to choose the frame's aspect ratio first) */
    public static function peek(string $file): ?ImagePlace
    {
        $top = count(self::$stack) - 1;
        if ($top < 0 || '' === $file) {
            return null;
        }
        $path = self::paths(self::$stack[$top]['data'], $file)[self::$stack[$top]['used'][$file] ?? 0] ?? null;

        return null === $path ? null : self::of(self::$stack[$top]['data'], $path, $file);
    }

    /** @param array<array-key, mixed> $data */
    public static function of(array $data, string $path, string $file): ?ImagePlace
    {
        $all = $data[self::KEY] ?? null;
        $stored = is_array($all) ? ($all[$path] ?? null) : null;
        if (!is_array($stored) || ($stored['file'] ?? '') !== $file) {
            return null;
        }

        return ImagePlace::fromArray($stored, $file);
    }

    /**
     * Paths of all fields whose value is exactly this file name, in data order.
     *
     * @param array<array-key, mixed> $data
     * @return list<string>
     */
    public static function paths(array $data, string $file, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $key = (string) $key;
            if ('' === $prefix && (str_starts_with($key, '_') || 'widths' === $key)) {
                continue;
            }
            $path = '' === $prefix ? $key : $prefix . '.' . $key;
            if ($value === $file) {
                $out[] = $path;
            } elseif (is_array($value)) {
                foreach (self::paths($value, $file, $path) as $sub) {
                    $out[] = $sub;
                }
            }
        }

        return $out;
    }

    /**
     * Applies editor input for a path; without a valid difference from the defaults the setting is removed.
     *
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public static function apply(array $data, string $path, mixed $input): array
    {
        $file = self::valueAt($data, $path);
        $all = is_array($data[self::KEY] ?? null) ? $data[self::KEY] : [];
        $place = is_string($file) ? ImagePlace::fromArray($input, $file) : null;
        if (null === $place) {
            unset($all[$path]);
        } else {
            $all[$path] = $place->toArray();
        }

        return self::store($data, $all);
    }

    /**
     * After saving the whole form: keeps the settings of $old whose image is still at its path.
     *
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $old
     * @return array<array-key, mixed>
     */
    public static function keep(array $data, array $old): array
    {
        return self::store($data, is_array($old[self::KEY] ?? null) ? $old[self::KEY] : []);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $all
     * @return array<array-key, mixed>
     */
    private static function store(array $data, array $all): array
    {
        $kept = [];
        foreach ($all as $path => $stored) {
            $file = self::valueAt($data, (string) $path);
            $place = is_string($file) && is_array($stored) && ($stored['file'] ?? '') === $file ? ImagePlace::fromArray($stored, $file) : null;
            if (null !== $place) {
                $kept[(string) $path] = $place->toArray();
            }
        }
        unset($data[self::KEY]);
        if ($kept) {
            $data[self::KEY] = $kept;
        }

        return $data;
    }

    /** @param array<array-key, mixed> $data */
    private static function valueAt(array $data, string $path): mixed
    {
        $value = $data;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
