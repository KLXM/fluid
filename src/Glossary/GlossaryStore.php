<?php

namespace Klxm\Fluid\Glossary;

use Redaxo\Core\Security\User;

/**
 * Datenquelle des Glossars – eine kleine Schnittstelle, damit neben YForm (YFormGlossaryStore, Standard) später ein anderes
 * Backend möglich ist (z. B. Doctrine). Setzen in Project::configure(): `Glossary::$store = new MeinStore();`
 *
 * Ein Begriff ist ein Array: id (int), key (Slug), term, short (Klartext, höchstens 240 Zeichen), variants (list<string>),
 * long (bool: es gibt eine ausführliche Erklärung), online (bool), language (Code oder '' = alle Sprachen).
 */
interface GlossaryStore
{
    /** Gibt es das Glossar (Tabelle eingerichtet)? */
    public function available(): bool;

    /**
     * Begriffe der Sprache (Code); $all: auch offline
     *
     * @return list<array{id: int, key: string, term: string, short: string, variants: list<string>, long: bool, online: bool, language: string}>
     */
    public function terms(string $language, bool $all = false): array;

    /** Ausführliche Erklärung eines Begriffs als HTML für die Detailseite (im Bearbeiten-Modus gern bearbeitbar) */
    public function longHtml(int $id, int $articleId): string;

    /** Neuen Begriff anlegen (Prüfung der Datenquelle) – liefert die ID, wirft bei Fehlern \Klxm\Fluid\ApiError @param list<string> $variants */
    public function create(string $term, string $short, array $variants, bool $online, string $language): int;

    /** Schreibweise zu einem Begriff hinzufügen */
    public function addVariant(int $id, string $variant): void;

    /** Darf der Benutzer Begriffe anlegen und ändern? */
    public function canEdit(?User $user): bool;

    /** Bearbeiten in der Verwaltung der Datenquelle (null = keine) */
    public function editUrl(?int $id = null): ?string;
}
