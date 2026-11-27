<?php

namespace Klxm\Fluid\Routing;

/** A domain of the website (see Domains) */
final readonly class Domain
{
    public function __construct(
        public int $id,
        public string $host,
        public DomainRole $role,
        public ?int $articleId = null,
        public bool $subtree = false,
        public bool $canonicalOwn = true,
    ) {}
}
