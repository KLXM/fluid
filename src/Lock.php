<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ExtensionPoint\SliceDeleting;
use Redaxo\Core\Content\ExtensionPoint\SliceMenu;
use Redaxo\Core\Content\ExtensionPoint\SliceUpdating;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Security\User;
use Redaxo\Core\Translation\I18n;

use function array_key_exists;
use function in_array;

/**
 * Locked blocks and design permissions (see Perm): a locked block (`lock` in the slice's section options) stays visible
 * and movable, but can only be changed, switched online/offline or deleted with `fluid[design_full]`.
 * Without `fluid[design]` the section values (background, spacing, anchor, reveal effect) stay as they are.
 *
 * Checked in fluid's API (website) and in the core's backend (SliceUpdating/SliceDeleting, slice menu).
 */
final class Lock
{
    /** Key in the section options (tunes) */
    public const string TUNE = 'lock';

    /** Section options that belong to design (level 1); `col` (column in the layout) counts as moving */
    public const array DESIGN_TUNES = ['background', 'anchor', 'spaceTop', 'spaceBottom', 'reveal'];

    /** @param array<string, mixed> $tunes */
    public static function locked(array $tunes): bool
    {
        return !empty($tunes[self::TUNE]);
    }

    /** Why a user may not change a block: '' (may), 'perm' (no module permission), 'lock' (locked, without permission). */
    public static function reason(?User $user, ArticleSlice $slice): string
    {
        if (null === $user) {
            return 'perm';
        }

        return self::reasonFor(Perm::module($user, $slice->moduleKey), self::locked(BlockModule::tunesOf($slice)), Perm::can($user, Perm::DESIGN_FULL));
    }

    /** Regel ohne Benutzer (Selbsttest): Modulrecht vor Sperre */
    public static function reasonFor(bool $modulePerm, bool $locked, bool $designFull): string
    {
        return !$modulePerm ? 'perm' : ($locked && !$designFull ? 'lock' : '');
    }

    /** Changing, online switch and deleting only without obstacle (module permission, lock) */
    public static function assert(User $user, ArticleSlice $slice): void
    {
        match (self::reason($user, $slice)) {
            'perm' => throw new ApiError(I18n::rawMsg('fluid_error_block_type_perm'), 403),
            'lock' => throw new ApiError(I18n::rawMsg('fluid_lock_locked'), 403),
            default => null,
        };
    }

    /**
     * Applies section option inputs according to permissions: without `fluid[design]` the stored design values apply,
     * without `fluid[design_full]` the stored lock state.
     *
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function tunes(User $user, array $stored, array $input): array
    {
        return self::tunesFor(Perm::can($user, Perm::DESIGN), Perm::can($user, Perm::DESIGN_FULL), $stored, $input);
    }

    /**
     * Regel ohne Benutzer (Selbsttest).
     *
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function tunesFor(bool $design, bool $designFull, array $stored, array $input): array
    {
        $keep = [];
        if (!$design) {
            $keep = self::DESIGN_TUNES;
        }
        if (!$designFull) {
            $keep[] = self::TUNE;
        }
        foreach ($keep as $k) {
            if (array_key_exists($k, $stored)) {
                $input[$k] = $stored[$k];
            } else {
                unset($input[$k]);
            }
        }
        if (isset($input[self::TUNE])) {
            $input[self::TUNE] = in_array($input[self::TUNE], [true, 1, '1', 'on'], true);
            if (!$input[self::TUNE]) {
                unset($input[self::TUNE]);
            }
        }

        return $input;
    }

    /** Copies (duplicating, pasting) are locked again only with permission @param array<string, mixed> $tunes @return array<string, mixed> */
    public static function forCopy(?User $user, array $tunes): array
    {
        if (null === $user || !Perm::can($user, Perm::DESIGN_FULL)) {
            unset($tunes[self::TUNE]);
        }

        return $tunes;
    }

    /** Core backend: locked blocks can neither be edited nor deleted without permission (slice menu and handler) */
    public static function boot(): void
    {
        if (!Core::isBackend()) {
            return;
        }
        $guard = static function (ArticleSlice $slice): void {
            $user = Core::getUser();
            if (null !== $user && null !== BlockModule::classFor($slice->moduleKey) && 'lock' === self::reason($user, $slice)) {
                throw new UserMessageException(I18n::rawMsg('fluid_lock_locked'));
            }
        };
        Extension::register(SliceUpdating::class, static fn (SliceUpdating $ep) => $guard($ep->slice));
        Extension::register(SliceDeleting::class, static fn (SliceDeleting $ep) => $guard($ep->slice));
        Extension::register(SliceMenu::class, static function (SliceMenu $ep): void {
            $user = Core::getUser();
            if (null === $user || null === BlockModule::classFor($ep->moduleKey) || Perm::can($user, Perm::DESIGN_FULL)) {
                return;
            }
            $row = Sql::factory()->getArray('SELECT data FROM rex_article_slice WHERE id = ?', [$ep->sliceId])[0] ?? null;
            if (null === $row || !self::locked(BlockModule::stored((string) $row['data'])['tunes'])) {
                return;
            }
            $ep->menuEditAction = [];
            $ep->menuDeleteAction = [];
            $ep->menuStatusAction = [];
        });
    }
}
