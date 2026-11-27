<?php

namespace Klxm\Fluid\Mcp;

use Klxm\Fluid\FluidAddon;
use Klxm\Fluid\Perm;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Security\User;

use function in_array;
use function is_array;

/**
 * Who is asking – and what may they do? Two kinds:
 *
 * - **Key** (bearer token): acts as a REDAXO user whose permissions apply; scope read/write, optionally publish.
 * - **Public** (without key): only if the admin enables it and only what they release – online pages (live),
 *   search, blog, selected YForm tables. Never drafts, never writing.
 */
final class Access
{
    /** Settings (Backend → fluid → MCP), everything off by default */
    public const array DEFAULTS = [
        'enabled' => false,
        'public' => false,
        'public_pages' => true,
        'public_search' => true,
        'public_blog' => true,
        'public_tables' => [],
        // YForm tables for keys: table name => read|write (missing = no access)
        'tables' => [],
        'instructions' => '',
    ];

    private function __construct(
        public readonly ?User $user,
        public readonly bool $write,
        public readonly bool $publish,
        public readonly ?int $tokenId,
        public readonly string $label,
    ) {}

    /** @return array<string, mixed> */
    public static function config(): array
    {
        $cfg = FluidAddon::instance()->getConfig('mcp', []);

        return (is_array($cfg) ? $cfg : []) + self::DEFAULTS;
    }

    /** @param array{id: int, name: string, scope: string, publish: bool, user: User} $token */
    public static function forToken(array $token): self
    {
        $write = 'write' === $token['scope'];

        return new self($token['user'], $write, $write && $token['publish'] && Perm::can($token['user'], 'version[live_version]'), $token['id'], $token['name']);
    }

    public static function anonymous(): self
    {
        return new self(null, false, false, null, 'public');
    }

    public function isPublic(): bool
    {
        return null === $this->user;
    }

    public function describe(): string
    {
        if ($this->isPublic()) {
            return 'public access: published content only, read only';
        }

        return 'key "' . $this->label . '" for ' . ($this->user->name ?: $this->user->login) . ': '
            . ($this->write ? 'read and change (draft)' . ($this->publish ? ', publish' : ', without publishing') : 'read only');
    }

    /** May read this page? Public: only online; key: the user's structure permission */
    public function canReadPage(Article $a): bool
    {
        if ($this->isPublic()) {
            return (bool) self::config()['public_pages'] && $a->isOnline();
        }

        return $this->canStructure($a);
    }

    public function canWritePage(Article $a): bool
    {
        return $this->write && $this->canStructure($a);
    }

    private function canStructure(Article $a): bool
    {
        if ($this->user->admin) {
            return true;
        }
        $perm = $this->user->getComplexPerm('structure');

        return $perm instanceof StructurePermission && $perm->hasCategoryPerm($a->categoryId);
    }

    public function canModule(string $key): bool
    {
        if (null === $this->user) {
            return false;
        }
        $perm = $this->user->getComplexPerm('modules');

        return $this->user->admin || ($perm instanceof ModulePermission && $perm->hasPerm($key));
    }

    public function can(string $perm): bool
    {
        return null !== $this->user && Perm::can($this->user, $perm);
    }

    /** Access to a YForm table: 'read', 'write' or null */
    public function table(string $name): ?string
    {
        $cfg = self::config();
        if ($this->isPublic()) {
            return in_array($name, (array) $cfg['public_tables'], true) ? 'read' : null;
        }
        $mode = ((array) $cfg['tables'])[$name] ?? null;
        if ('write' === $mode) {
            return $this->write ? 'write' : 'read';
        }

        return 'read' === $mode ? 'read' : null;
    }

    /** @return list<string> lesbare Tabellen */
    public function tables(): array
    {
        $cfg = self::config();
        $names = $this->isPublic() ? (array) $cfg['public_tables'] : array_keys((array) $cfg['tables']);

        return array_values(array_filter(array_map('strval', $names), fn (string $n) => null !== $this->table($n)));
    }
}
