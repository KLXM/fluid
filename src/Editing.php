<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\User;

/**
 * State „Bearbeiten auf der Website“ for the current request.
 *
 * - `user()`: logged-in backend user with permission for the current article (otherwise null) – only then is there a toolbar.
 * - `active()`: edit mode (`?fluid_edit=1`) – blocks then output `data-edit` attributes and the page is
 *   rendered live from the database (working version), not from the cache.
 *
 * Outside the frontend (backend, cache generation, console) both are always off, so the cache never contains edit markup.
 */
final class Editing
{
    public const string PARAM = 'fluid_edit';

    private static ?User $user = null;
    private static bool $resolved = false;
    private static bool $rendering = false;
    private static bool $guest = false;
    private static ?int $guestRevision = null;

    public static function user(): ?User
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        if (!Core::isFrontend()) {
            return null;
        }
        $user = BackendLogin::createUser();
        // guest access (FLUID_GUEST_USER, e.g. AddOn fluid_demo): visitors without login get the permissions of a user.
        // Never with admin permissions. fluid only runs write actions of guests if an AddOn takes them over via
        // FLUID_API_ACTION (e.g. in a transaction that is rolled back) – otherwise Api rejects them.
        if (!$user) {
            $guest = Extension::dispatch(new ExtensionPoint('FLUID_GUEST_USER', null));
            if ($guest instanceof User && !$guest->admin) {
                $user = $guest;
                self::$guest = true;
            }
        }
        if (!$user) {
            return null;
        }
        $article = Article::getCurrent();
        $perm = $user->getComplexPerm('structure');
        if ($article && !($perm instanceof StructurePermission && $perm->hasCategoryPerm($article->categoryId))) {
            return null;
        }

        return self::$user = $user;
    }

    /** Guest access: the person is not logged in but works via FLUID_GUEST_USER (e.g. demo) */
    public static function isGuest(): bool
    {
        self::user();

        return self::$guest;
    }

    /**
     * Revision in which guests work (e.g. live, if an AddOn rolls back all changes in a transaction).
     * If not set, like logged-in users (FluidAddon::revision()).
     */
    public static function guestRevision(?int $revision): void
    {
        self::$guestRevision = $revision;
    }

    /** @internal FluidAddon::revision() */
    public static function revisionForGuest(): ?int
    {
        return self::isGuest() ? self::$guestRevision : null;
    }

    /** Edit mode requested and allowed */
    public static function requested(): bool
    {
        // edit lock: if someone else holds the page, view only – even with ?fluid_edit=1
        return 1 === Request::get(self::PARAM, 'int', 0) && null !== self::user() && null === self::lockedBy();
    }

    /**
     * Edit lock: entry of the person currently holding the current page – null if it is free or held
     * by oneself.
     *
     * @return array<string, mixed>|null
     */
    public static function lockedBy(): ?array
    {
        $article = Article::getCurrent();

        return null !== $article ? EditLock::blockedFor($article) : null;
    }

    /** Edit markup is being output right now (page in edit mode or preview of a block) */
    public static function active(): bool
    {
        return self::$rendering;
    }

    /** Turn edit markup off for the duration of $fn (blocks from data that is not edited on the page) */
    public static function without(callable $fn): mixed
    {
        $old = self::$rendering;
        self::$rendering = false;
        try {
            return $fn();
        } finally {
            self::$rendering = $old;
        }
    }

    /** @internal Turn edit markup on for the duration of $fn (preview of single blocks) */
    public static function render(callable $fn): mixed
    {
        $old = self::$rendering;
        self::$rendering = true;
        try {
            return $fn();
        } finally {
            self::$rendering = $old;
        }
    }

    /** @internal MCP server: actions on behalf of the user of an access key (log, versions) */
    public static function actAs(?User $user): void
    {
        self::$user = $user;
        self::$resolved = true;
    }

    /** @internal set by the addon on start in edit mode */
    public static function start(): void
    {
        self::$rendering = true;
    }
}
