<?php

namespace Klxm\Fluid;

use function in_array;
use function is_a;
use function is_array;
use function is_string;
use function Redaxo\Core\View\escape;

use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Blocks as data – without articles and slices, e.g. in the YForm field „Fluid-Blöcke“ (Data\FluidBlocks).
 *
 * Value: list of ['module' => 'fluid.hero', 'data' => [...], 'tunes' => [...]] (stored as JSON). The same blocks,
 * fields and outputs as on the pages – only without layout columns (they need slices).
 *
 *     echo BlockData::render($record->get('inhalt'));   // in a template or a custom detail view
 */
final class BlockData
{
    /**
     * List of blocks; `uid` = fixed identifier per block (for editing on the website), missing in old data.
     *
     * @return list<array{module: string, data: array<string, mixed>, tunes: array<string, mixed>, uid?: string}>
     */
    public static function decode(mixed $value): array
    {
        $list = is_string($value) ? json_decode($value, true) : $value;
        $out = [];
        foreach (is_array($list) ? $list : [] as $item) {
            if (is_array($item) && is_string($item['module'] ?? null) && self::module($item['module'])) {
                $row = ['module' => $item['module'], 'data' => is_array($item['data'] ?? null) ? $item['data'] : [], 'tunes' => is_array($item['tunes'] ?? null) ? $item['tunes'] : []];
                if (is_string($item['uid'] ?? null) && preg_match('~^[a-z0-9]{6,16}$~', $item['uid'])) {
                    $row['uid'] = $item['uid'];
                }
                $out[] = $row;
            }
        }

        return $out;
    }

    /** Add missing identifiers (duplicates are reassigned) */
    public static function withUids(array $blocks): array
    {
        $seen = [];
        foreach ($blocks as $i => $b) {
            $uid = (string) ($b['uid'] ?? '');
            if ('' === $uid || isset($seen[$uid])) {
                $uid = self::uid();
                $blocks[$i]['uid'] = $uid;
            }
            $seen[$uid] = true;
        }

        return $blocks;
    }

    public static function uid(): string
    {
        return bin2hex(random_bytes(5));
    }

    public static function encode(array $blocks): ?string
    {
        $blocks = self::decode($blocks);

        return [] === $blocks ? null : json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Blocks allowed in data: all available blocks except layout columns.
     *
     * @param list<string>|null $allowed only these types (e.g. ['richtext', 'media_text']) or module keys
     * @return array<string, class-string<BlockModule>> module key => module
     */
    public static function modules(?array $allowed = null): array
    {
        $out = [];
        foreach (BlockModule::all() as $m) {
            if (is_a($m, LayoutBlock::class, true) || !$m::available()) {
                continue;
            }
            if (null !== $allowed && [] !== $allowed && !in_array($m::type(), $allowed, true) && !in_array($m::key(), $allowed, true)) {
                continue;
            }
            $out[$m::key()] = $m;
        }
        uasort($out, static fn (string $a, string $b) => [(string) ($a::definition()['group'] ?? ''), (string) ($a::definition()['label'] ?? '')]
            <=> [(string) ($b::definition()['group'] ?? ''), (string) ($b::definition()['label'] ?? '')]);

        return $out;
    }

    /** @return class-string<BlockModule>|null */
    public static function module(string $key): ?string
    {
        $m = BlockModule::classFor($key);

        return null !== $m && !is_a($m, LayoutBlock::class, true) ? $m : null;
    }

    /** All blocks as sections – without the page's editing markup (the blocks belong to a record) */
    public static function render(mixed $value, string $idPrefix = 'bd', int $articleId = 0): string
    {
        $blocks = self::decode($value);
        if ([] === $blocks) {
            return '';
        }

        return (string) Editing::without(static function () use ($blocks, $idPrefix, $articleId): string {
            $html = '';
            $prev = null;
            foreach ($blocks as $i => $item) {
                $m = self::module($item['module']);
                if (!$m) {
                    continue;
                }
                $b = $m::blockFromData($idPrefix . '-' . ($i + 1), $item['data'], $item['tunes'], $prev, $articleId);
                $html .= $m::renderData($b);
                $prev = $b;
            }

            return $html;
        });
    }

    /** Plain text of all text fields (list in the backend, search, mails) */
    public static function text(mixed $value): string
    {
        $headings = [];
        $text = [];
        foreach (self::decode($value) as $item) {
            if ($m = self::module($item['module'])) {
                Search\Search::collect($m::fields(), $item['data'], $headings, $text);
            }
        }

        return trim(implode(' ', [...$headings, ...$text]));
    }

    /** Short label of a block for lists: „Text – Erste Überschrift …“ */
    public static function label(array $item): string
    {
        $m = self::module((string) ($item['module'] ?? ''));
        if (!$m) {
            return '?';
        }
        $headings = [];
        $text = [];
        Search\Search::collect($m::fields(), (array) ($item['data'] ?? []), $headings, $text);
        $first = $headings[0] ?? $text[0] ?? '';

        return $m::label() . ('' !== $first ? ' – ' . mb_strimwidth($first, 0, 60, '…') : '');
    }

    /** @internal Choice for the interface: [key => [label, group, help]] */
    public static function catalog(?array $allowed = null): array
    {
        $out = [];
        foreach (self::modules($allowed) as $key => $m) {
            $d = $m::definition();
            $out[$key] = ['label' => (string) ($d['label'] ?? $m::name()), 'group' => (string) ($d['group'] ?? ''), 'help' => (string) ($d['help'] ?? '')];
        }

        return $out;
    }

    /** @internal */
    public static function attr(mixed $v): string
    {
        return escape(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
    }
}
