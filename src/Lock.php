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

use function array_key_exists;
use function in_array;

/**
 * Gesperrte Blöcke und Gestaltungsrechte (siehe Perm): Ein gesperrter Block (`lock` in den Abschnitts-Optionen des Slices)
 * bleibt sichtbar und verschiebbar, lässt sich aber nur mit `fluid[design_full]` ändern, online/offline schalten oder löschen.
 * Ohne `fluid[design]` bleiben die Abschnitts-Werte (Hintergrund, Abstände, Sprungmarke, Einblend-Effekt), wie sie sind.
 *
 * Geprüft wird in der API von fluid (Website) und im Backend des Cores (SliceUpdating/SliceDeleting, Menü des Slices).
 */
final class Lock
{
    /** Schlüssel in den Abschnitts-Optionen (tunes) */
    public const string TUNE = 'lock';

    /** Abschnitts-Optionen, die zur Gestaltung (Stufe 1) gehören; `col` (Spalte im Layout) zählt zum Verschieben */
    public const array DESIGN_TUNES = ['background', 'anchor', 'spaceTop', 'spaceBottom', 'reveal'];

    /** @param array<string, mixed> $tunes */
    public static function locked(array $tunes): bool
    {
        return !empty($tunes[self::TUNE]);
    }

    /** Warum ein Benutzer einen Block nicht ändern darf: '' (darf), 'perm' (kein Modulrecht), 'lock' (gesperrt, ohne Recht). */
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

    /** Ändern, Online-Schalter und Löschen nur ohne Hindernis (Modulrecht, Sperre) */
    public static function assert(User $user, ArticleSlice $slice): void
    {
        match (self::reason($user, $slice)) {
            'perm' => throw new ApiError('Keine Berechtigung für diesen Blocktyp.', 403),
            'lock' => throw new ApiError('Dieser Block ist gesperrt – ändern oder löschen dürfen ihn nur Personen mit dem Recht „Blöcke gestalten und sperren“.', 403),
            default => null,
        };
    }

    /**
     * Eingaben der Abschnitts-Optionen nach den Rechten übernehmen: ohne `fluid[design]` gelten die gespeicherten Werte der
     * Gestaltung, ohne `fluid[design_full]` der gespeicherte Zustand der Sperre.
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

    /** Kopien (Verdoppeln, Einfügen) sind nur mit Recht wieder gesperrt @param array<string, mixed> $tunes @return array<string, mixed> */
    public static function forCopy(?User $user, array $tunes): array
    {
        if (null === $user || !Perm::can($user, Perm::DESIGN_FULL)) {
            unset($tunes[self::TUNE]);
        }

        return $tunes;
    }

    /** Backend des Cores: gesperrte Blöcke ohne Recht weder bearbeiten noch löschen (Menü des Slices und Handler) */
    public static function boot(): void
    {
        if (!Core::isBackend()) {
            return;
        }
        $guard = static function (ArticleSlice $slice): void {
            $user = Core::getUser();
            if (null !== $user && null !== BlockModule::classFor($slice->moduleKey) && 'lock' === self::reason($user, $slice)) {
                throw new UserMessageException('Dieser Block ist gesperrt – ändern oder löschen dürfen ihn nur Personen mit dem Recht „Blöcke gestalten und sperren“.');
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
