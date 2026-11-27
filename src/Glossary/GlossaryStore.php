<?php

namespace Klxm\Fluid\Glossary;

use Redaxo\Core\Security\User;

/**
 * Data source of the glossary – a small interface so that besides YForm (YFormGlossaryStore, default) another
 * backend is possible later (e.g. Doctrine). Set in Project::configure(): `Glossary::$store = new MeinStore();`.
 *
 * A term is an array: id (int), key (slug), term, short (plain text, at most 240 characters), variants (list<string>),
 * long (bool: there is a detailed explanation), online (bool), language (code or '' = all languages).
 */
interface GlossaryStore
{
    /** Does the glossary exist (table set up)? */
    public function available(): bool;

    /**
     * Terms of the language (code); $all: offline ones too.
     *
     * @return list<array{id: int, key: string, term: string, short: string, variants: list<string>, long: bool, online: bool, language: string}>
     */
    public function terms(string $language, bool $all = false): array;

    /** Detailed explanation of a term as HTML for the detail page (editable in edit mode if possible) */
    public function longHtml(int $id, int $articleId): string;

    /** Create a new term (validated by the data source) – returns the ID, throws \Klxm\Fluid\ApiError on errors @param list<string> $variants */
    public function create(string $term, string $short, array $variants, bool $online, string $language): int;

    /** Add a spelling to a term */
    public function addVariant(int $id, string $variant): void;

    /** May the user create and change terms? */
    public function canEdit(?User $user): bool;

    /** Editing in the management of the data source (null = none) */
    public function editUrl(?int $id = null): ?string;
}
