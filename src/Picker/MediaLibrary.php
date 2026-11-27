<?php

namespace Klxm\Fluid\Picker;

use Klxm\ImageEditor\ImageEditor;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\MediaPool\MediaPoolPermission;
use Redaxo\Core\Security\User;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Pager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function in_array;

/**
 * Media picker on the website (FrontendPicker): browse and upload categories and files of the media pool.
 * The media pool's permissions apply; in the backend the core's media pool handles this (rex.pick).
 *
 * @internal
 */
final class MediaLibrary
{
    public const int PER_PAGE = 48;
    public const array IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

    /**
     * Media of a category (0 = without category, the media pool's top level) and all categories with permissions.
     *
     * @param list<string> $types allowed extensions (empty = all)
     * @return array{categories: list<array{id: int, name: string, parent: int}>, category: int, items: list<array<string, mixed>>, more: bool, upload: bool}
     */
    public static function list(User $user, int $category, string $term, array $types, int $page): array
    {
        $perm = self::perm($user);
        if (0 !== $category && !$perm->hasCategoryPerm($category)) {
            $category = 0;
        }
        $allowed = $perm->hasCategoryPerm($category ?: null);
        // search across all categories only with permissions for all, otherwise in the selected one
        $everywhere = '' !== $term && $perm->hasAll();

        $filter = $everywhere ? [] : ['category_id' => $category ?: null];
        if ('' !== $term) {
            $filter['term'] = $term;
        }
        if ($types) {
            $filter['types'] = $types;
        }

        $pager = new Pager(self::PER_PAGE);
        $pager->setPage(max(0, $page));
        $items = $allowed || $everywhere ? array_map(self::item(...), MediaHandler::getList($filter, [], $pager)) : [];

        return [
            'categories' => self::categories($perm),
            'category' => $category,
            'items' => $items,
            'more' => [] !== $items && $pager->getCursor() + self::PER_PAGE < $pager->getRowCount(),
            'upload' => $allowed,
        ];
    }

    /**
     * @param list<string> $types allowed extensions (empty = all the media pool permits)
     * @return array<string, mixed>
     */
    public static function upload(User $user, int $category, string $title, array $types, mixed $file): array
    {
        if (!self::perm($user)->hasCategoryPerm($category ?: null)) {
            throw new PickerError(I18n::rawMsg('fluid_picker_media_category_perm'), 403);
        }
        if (!$file instanceof UploadedFile) {
            throw new PickerError(I18n::rawMsg('fluid_picker_no_file'));
        }

        return self::item(MediaHandler::add(['file' => $file, 'category_id' => $category ?: null, 'title' => $title], types: $types));
    }

    /** Preview image; with the addon image_editor the edited version */
    public static function thumb(string $file): string
    {
        return class_exists(ImageEditor::class)
            ? ImageEditor::url($file, 320)
            : MediaManager::getUrl('rex_media_small', $file);
    }

    /** @return array<string, mixed> */
    private static function item(Media $media): array
    {
        $ext = strtolower($media->getExtension());
        $image = in_array($ext, self::IMAGE_TYPES, true);

        return [
            'file' => $media->fileName,
            'title' => $media->title,
            'ext' => $ext,
            'image' => $image,
            'thumb' => $image ? self::thumb($media->fileName) : null,
            'size' => $media->getFormattedSize(),
            'width' => $media->width,
            'height' => $media->height,
        ];
    }

    private static function perm(User $user): MediaPoolPermission
    {
        $perm = $user->getComplexPerm('media');
        if (!$perm instanceof MediaPoolPermission || !$perm->hasMediaPerm()) {
            throw new PickerError(I18n::rawMsg('fluid_picker_media_perm'), 403);
        }

        return $perm;
    }

    /**
     * Categories with permissions in the media pool's order; `parent`: next parent category with permissions
     * (0 = top level), so navigation also works with permissions for subcategories only.
     *
     * @return list<array{id: int, name: string, parent: int}>
     */
    private static function categories(MediaPoolPermission $perm): array
    {
        $out = [];
        $walk = static function (array $cats, int $parent) use (&$walk, &$out, $perm): void {
            foreach ($cats as $cat) {
                /** @var MediaCategory $cat */
                $ok = $perm->hasCategoryPerm($cat->id);
                if ($ok) {
                    $out[] = ['id' => $cat->id, 'name' => $cat->name, 'parent' => $parent];
                }
                $walk($cat->getChildren(), $ok ? $cat->id : $parent);
            }
        };
        $walk(MediaCategory::getRootCategories(), 0);

        return $out;
    }
}
