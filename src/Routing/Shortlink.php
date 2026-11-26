<?php

namespace Klxm\Fluid\Routing;

use DateTimeImmutable;

use function is_string;

/**
 * A short address that leads to a page, an entry or another website (a redirect rule with origin "short").
 *
 * @internal
 */
final readonly class Shortlink
{
    public function __construct(
        public int $id,
        public string $path,
        public string $target,
        public int $hits = 0,
        public ?DateTimeImmutable $lastHit = null,
        public ?DateTimeImmutable $expires = null,
        public string $note = '',
    ) {}

    public function isExpired(): bool
    {
        return null !== $this->expires && $this->expires <= new DateTimeImmutable();
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $date = static fn (mixed $v): ?DateTimeImmutable => is_string($v) && '' !== $v ? (new DateTimeImmutable($v)) : null;

        return new self(
            id: (int) $row['id'],
            path: (string) $row['source'],
            target: (string) $row['target'],
            hits: (int) ($row['hits'] ?? 0),
            lastHit: $date($row['last_hit'] ?? null),
            expires: $date($row['expires'] ?? null),
            note: (string) ($row['note'] ?? ''),
        );
    }
}
