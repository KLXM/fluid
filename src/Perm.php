<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Security\Permission;
use Redaxo\Core\Security\User;

/**
 * Aktionsrechte des Cores (Benutzer → Rollen → „Optionen“) auf der Website – dieselben Rechte wie im Backend:
 * wer dort keine Artikel löschen darf, darf es auch auf der Website nicht. Admins dürfen alles.
 *
 * Welche Blöcke jemand einfügen, bearbeiten und löschen darf, regeln die **Modulrechte** der Rolle (`modules`) – fluid
 * nutzt sie, statt eigene „erlaubte Blöcke“ einzuführen: Die Blockauswahl zeigt nur erlaubte Blöcke, Blöcke anderer Module
 * sind auf der Seite schreibgeschützt, die API prüft jede Aktion.
 *
 * Dazu zwei eigene Optionen für die **Gestaltung** (Rolle → Optionen):
 *  - `fluid[design]` (Stufe 1): Abschnitt eines Blocks – Hintergrund, Abstände (auch per Greifer), Sprungmarke, Einblend-Effekt.
 *  - `fluid[design_full]` (Stufe 2): zusätzlich Blöcke sperren und entsperren und gesperrte Blöcke ändern oder löschen (Lock).
 * Ohne Stufe 1 bleiben die Abschnitts-Werte, wie sie sind. Bestehende Rollen bekommen beide Optionen einmal beim Update
 * (migrateRoles), damit niemand etwas verliert; neue Rollen haben sie erst, wenn man sie anhakt.
 */
final class Perm
{
    /** Gestaltung, Stufe 1: Abschnitts-Optionen der Blöcke */
    public const string DESIGN = 'fluid[design]';

    /** Gestaltung, Stufe 2: Blöcke sperren/entsperren, gesperrte Blöcke ändern und löschen */
    public const string DESIGN_FULL = 'fluid[design_full]';

    /** Merker in der Konfiguration: bestehende Rollen haben die Optionen der Gestaltung bekommen */
    public const string MIGRATED = 'perm_design_roles';

    /** Bezeichnungen für Meldungen */
    private const array LABEL = [
        'version[live_version]' => 'Veröffentlichen (Liveversion)',
        'moveSlice[]' => 'Blöcke verschieben',
        'publishSlice[]' => 'Blöcke online/offline schalten',
        'copyContent[]' => 'Inhalte kopieren',
        'addArticle[]' => 'Artikel anlegen',
        'addCategory[]' => 'Kategorien anlegen',
        'editArticle[]' => 'Artikel bearbeiten',
        'editCategory[]' => 'Kategorien bearbeiten',
        'moveArticle[]' => 'Artikel verschieben',
        'moveCategory[]' => 'Kategorien verschieben',
        'publishArticle[]' => 'Artikel online/offline schalten',
        'publishCategory[]' => 'Kategorien online/offline schalten',
        'deleteArticle[]' => 'Artikel löschen',
        'deleteCategory[]' => 'Kategorien löschen',
        'article2category[]' => 'Artikel ↔ Kategorie umwandeln',
        self::DESIGN => 'Blöcke gestalten (Abschnitt)',
        self::DESIGN_FULL => 'Blöcke gestalten und sperren',
    ];

    /** Optionen der Gestaltung im Rollen-Formular des Cores anmelden (boot) */
    public static function register(): void
    {
        Permission::register(self::DESIGN, 'fluid: Blöcke gestalten – Abschnitt (Hintergrund, Abstände, Sprungmarke, Einblend-Effekt)', Permission::OPTIONS);
        Permission::register(self::DESIGN_FULL, 'fluid: Blöcke gestalten und sperren – gesperrte Blöcke ändern und löschen', Permission::OPTIONS);
    }

    /**
     * Einmal beim Update (FluidAddon::install): alle vorhandenen Rollen bekommen beide Optionen der Gestaltung – so ändert
     * sich für bestehende Benutzer nichts. Liefert die Zahl der geänderten Rollen.
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

    /** Rechte einer Rolle (JSON) mit beiden Optionen der Gestaltung – null, wenn nichts zu ändern ist */
    public static function withDesign(string $json): ?string
    {
        $perms = json_decode($json, true);
        if (!\is_array($perms)) {
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

    /** Modulrecht des Cores: darf der Benutzer Blöcke dieses Moduls einfügen, bearbeiten und löschen? */
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
            throw new ApiError('Dafür fehlt Ihnen das Recht „' . (self::LABEL[$perm] ?? $perm) . '“.');
        }
    }

    /** Recht je nach Art der Seite: Menüseite (Kategorie) oder Seite (Artikel), z. B. for($a, 'move') */
    public static function forPage(Article $a, string $action): string
    {
        $isCat = $a->isStartArticle() && null !== Category::get($a->id);

        return $action . ($isCat ? 'Category' : 'Article') . '[]';
    }

    /** Für die Leiste: was darf dieser Benutzer? */
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
