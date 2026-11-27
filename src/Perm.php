<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Security\Permission;
use Redaxo\Core\Security\User;
use Redaxo\Core\Translation\I18n;

use function is_array;

/**
 * Core action permissions (Benutzer → Rollen → „Optionen“) on the website – the same permissions as in the backend:
 * whoever may not delete articles there may not do so on the website either. Admins may do everything.
 *
 * Which blocks someone may insert, edit and delete is governed by the **module permissions** of the role (`modules`) – fluid
 * uses them instead of introducing its own „allowed blocks“: the block picker only shows allowed blocks, blocks of other modules
 * are read-only on the page, the API checks every action.
 *
 * Plus two own options for **design** (role → options):
 *  - `fluid[design]` (level 1): section of a block – background, spacing (also via handle), anchor, reveal effect.
 *  - `fluid[design_full]` (level 2): additionally lock and unlock blocks and change or delete locked blocks (lock).
 * Without level 1 the section values stay as they are. Existing roles get both options once on update
 * (migrateRoles), so nobody loses anything; new roles only have them once they are ticked.
 */
final class Perm
{
    /** Design, level 1: section options of the blocks */
    public const string DESIGN = 'fluid[design]';

    /** Design, level 2: lock/unlock blocks, change and delete locked blocks */
    public const string DESIGN_FULL = 'fluid[design_full]';

    /** Flag in the config: existing roles have received the design options */
    public const string MIGRATED = 'perm_design_roles';

    /** Labels for messages (lang keys) */
    private const array LABEL = [
        'version[live_version]' => 'fluid_perm_live_version',
        'moveSlice[]' => 'fluid_perm_move_slice',
        'publishSlice[]' => 'fluid_perm_publish_slice',
        'copyContent[]' => 'fluid_perm_copy_content',
        'addArticle[]' => 'fluid_perm_add_article',
        'addCategory[]' => 'fluid_perm_add_category',
        'editArticle[]' => 'fluid_perm_edit_article',
        'editCategory[]' => 'fluid_perm_edit_category',
        'moveArticle[]' => 'fluid_perm_move_article',
        'moveCategory[]' => 'fluid_perm_move_category',
        'publishArticle[]' => 'fluid_perm_publish_article',
        'publishCategory[]' => 'fluid_perm_publish_category',
        'deleteArticle[]' => 'fluid_perm_delete_article',
        'deleteCategory[]' => 'fluid_perm_delete_category',
        'article2category[]' => 'fluid_perm_article2category',
        self::DESIGN => 'fluid_perm_design',
        self::DESIGN_FULL => 'fluid_perm_design_full',
    ];

    /** Register the design options in the core role form (boot) */
    public static function register(): void
    {
        Permission::register(self::DESIGN, I18n::rawMsg('fluid_perm_design_register'), Permission::OPTIONS);
        Permission::register(self::DESIGN_FULL, I18n::rawMsg('fluid_perm_design_full_register'), Permission::OPTIONS);
    }

    /**
     * Once on update (FluidAddon::install): all existing roles get both design options – so nothing changes
     * for existing users. Returns the number of changed roles.
     */
    public static function migrateRoles(): int
    {
        $addon = FluidAddon::instance();
        if ($addon->getConfig(self::MIGRATED, false)) {
            return 0;
        }
        $n = 0;
        $sql = Sql::factory();
        foreach ($sql->getArray('SELECT id, perms FROM rex_user_role') as $role) {
            $json = self::withDesign((string) $role['perms']);
            if (null !== $json) {
                Sql::factory()->setQuery('UPDATE rex_user_role SET perms = ? WHERE id = ?', [$json, (int) $role['id']]);
                ++$n;
            }
        }
        $addon->setConfig(self::MIGRATED, true);

        return $n;
    }

    /** Permissions of a role (JSON) with both design options – null if nothing needs to change */
    public static function withDesign(string $json): ?string
    {
        $perms = json_decode($json, true);
        if (!is_array($perms)) {
            return null;
        }
        $have = array_values(array_filter(explode('|', trim((string) ($perms[Permission::OPTIONS] ?? ''), '|'))));
        $add = array_diff([self::DESIGN, self::DESIGN_FULL], $have);
        if (!$add) {
            return null;
        }
        $perms[Permission::OPTIONS] = '|' . implode('|', [...$have, ...$add]) . '|';

        return (string) json_encode($perms);
    }

    /** Core module permission: may the user insert, edit and delete blocks of this module? */
    public static function module(User $user, string $moduleKey): bool
    {
        if ($user->admin) {
            return true;
        }
        return $user->getComplexPerm('modules')->hasPerm($moduleKey);
    }

    public static function can(User $user, string $perm): bool
    {
        return $user->admin || $user->hasPerm($perm);
    }

    public static function require(User $user, string $perm): void
    {
        if (!self::can($user, $perm)) {
            throw new ApiError(I18n::rawMsg('fluid_perm_missing', isset(self::LABEL[$perm]) ? I18n::rawMsg(self::LABEL[$perm]) : $perm));
        }
    }

    /** Permission depending on the kind of page: menu page (category) or page (article), e.g. for($a, 'move') */
    public static function forPage(Article $a, string $action): string
    {
        $isCat = $a->isStartArticle() && null !== Category::get($a->id);

        return $action . ($isCat ? 'Category' : 'Article') . '[]';
    }

    /** For the toolbar: what may this user do? */
    public static function flags(User $user): array
    {
        $out = [];
        foreach (array_keys(self::LABEL) as $p) {
            $out[preg_replace('~\[.*$~', '', $p)] = self::can($user, $p);
        }
        $out['publish'] = $out['version'];
        unset($out['version'], $out['fluid']);
        $out['design'] = self::can($user, self::DESIGN);
        $out['designFull'] = self::can($user, self::DESIGN_FULL);

        return $out;
    }
}
