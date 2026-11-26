<?php

namespace Klxm\Fluid\Media;

use function array_filter;
use function implode;
use function is_array;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function preg_match;
use function round;
use function rtrim;
use function sprintf;
use function str_starts_with;

/**
 * Settings of an image at one place (per use in a block, not on the media file): effect, fit and crop.
 * They only apply while the same file is at that place ($file).
 *
 * @internal
 */
final readonly class ImagePlace
{
    /** Background for ImageFit::Contain: blurred version of the image */
    public const string BACKGROUND_BLUR = 'blur';
    /** Background for ImageFit::Contain: accent color of the design */
    public const string BACKGROUND_ACCENT = 'accent';

    /**
     * @param int $saturation percent, 0–200
     * @param int $brightness percent, 50–150
     * @param int $contrast percent, 50–150
     * @param string $background `blur`, `accent`, `#rrggbb` or empty (ImageFit::Contain only)
     * @param int|null $x center of the crop in percent
     * @param int|null $y center of the crop in percent
     * @param int $zoom percent, 100–300, (frames with a fixed aspect ratio only)
     */
    public function __construct(
        public string $file,
        public ?ImageEffect $effect = null,
        public int $saturation = 100,
        public int $brightness = 100,
        public int $contrast = 100,
        public ImageFit $fit = ImageFit::Cover,
        public string $background = '',
        public ?int $x = null,
        public ?int $y = null,
        public int $zoom = 100,
    ) {}

    /** From stored data or editor input – null if nothing differs from the defaults */
    public static function fromArray(mixed $data, string $file): ?self
    {
        if (!is_array($data) || '' === $file) {
            return null;
        }
        $fit = ImageFit::tryFrom(is_string($data['fit'] ?? null) ? $data['fit'] : '') ?? ImageFit::Cover;
        $background = is_string($data['bg'] ?? null) ? $data['bg'] : '';
        if (ImageFit::Contain !== $fit || !self::isBackground($background)) {
            $background = '';
        }
        $x = self::int($data['x'] ?? null, 0, 100);
        $y = self::int($data['y'] ?? null, 0, 100);
        if (null === $x || null === $y) {
            $x = $y = null;
        }
        $place = new self(
            file: $file,
            effect: ImageEffect::tryFrom(is_string($data['fx'] ?? null) ? $data['fx'] : ''),
            saturation: self::int($data['s'] ?? null, 0, 200) ?? 100,
            brightness: self::int($data['b'] ?? null, 50, 150) ?? 100,
            contrast: self::int($data['c'] ?? null, 50, 150) ?? 100,
            fit: $fit,
            background: $background,
            x: $x,
            y: $y,
            zoom: null !== $x ? self::int($data['z'] ?? null, 100, 300) ?? 100 : 100,
        );

        return $place->isDefault() ? null : $place;
    }

    /** @return array<string, int|string> stored form (differences from the defaults only) */
    public function toArray(): array
    {
        $out = ['file' => $this->file];
        if (null !== $this->effect) {
            $out['fx'] = $this->effect->value;
        }
        foreach (['s' => $this->saturation, 'b' => $this->brightness, 'c' => $this->contrast] as $key => $value) {
            if (100 !== $value) {
                $out[$key] = $value;
            }
        }
        if (ImageFit::Cover !== $this->fit) {
            $out['fit'] = $this->fit->value;
        }
        if ('' !== $this->background) {
            $out['bg'] = $this->background;
        }
        if (null !== $this->x && null !== $this->y) {
            $out['x'] = $this->x;
            $out['y'] = $this->y;
            if (100 !== $this->zoom) {
                $out['z'] = $this->zoom;
            }
        }

        return $out;
    }

    /** With a crop the whole image is loaded instead of the media file's crop for the aspect ratio */
    public function hasCrop(): bool
    {
        return null !== $this->x;
    }

    /**
     * CSS declarations for the <img> (rendered as a class by InlineStyle).
     *
     * @param string $blurUrl small version of the image as blurred background
     */
    public function declarations(string $blurUrl = ''): string
    {
        $css = [];
        $filters = [];
        if (null !== $this->effect && ImageEffect::None !== $this->effect) {
            $filters[] = $this->effect->filter();
        }
        foreach (['saturate' => $this->saturation, 'brightness' => $this->brightness, 'contrast' => $this->contrast] as $fn => $value) {
            if (100 !== $value) {
                $filters[] = sprintf('%s(%s)', $fn, self::number($value / 100));
            }
        }
        if ($filters) {
            $css[] = 'filter:' . implode(' ', $filters);
        } elseif (ImageEffect::None === $this->effect) {
            $css[] = 'filter:none';
        }
        if (ImageFit::Contain === $this->fit) {
            $css[] = 'object-fit:contain';
            $css[] = match (true) {
                self::BACKGROUND_BLUR === $this->background && '' !== $blurUrl => 'background:url("' . $blurUrl . '") center/cover no-repeat',
                self::BACKGROUND_ACCENT === $this->background => 'background:var(--f-accent)',
                str_starts_with($this->background, '#') => 'background:' . $this->background,
                default => '',
            };
        } elseif (ImageFit::Original === $this->fit) {
            $css[] = 'object-fit:scale-down';
        }
        if (null !== $this->x && null !== $this->y) {
            $css[] = 'object-position:' . $this->x . '% ' . $this->y . '%';
            if (100 !== $this->zoom) {
                $css[] = 'transform-origin:' . $this->x . '% ' . $this->y . '%';
                $css[] = 'scale:' . self::number($this->zoom / 100);
            }
        }

        return implode(';', array_filter($css));
    }

    private function isDefault(): bool
    {
        return null === $this->effect && 100 === $this->saturation && 100 === $this->brightness && 100 === $this->contrast
            && ImageFit::Cover === $this->fit && (null === $this->x || (50 === $this->x && 50 === $this->y && 100 === $this->zoom));
    }

    private static function isBackground(string $background): bool
    {
        return self::BACKGROUND_BLUR === $background || self::BACKGROUND_ACCENT === $background || 1 === preg_match('/^#[0-9a-fA-F]{6}$/', $background);
    }

    private static function int(mixed $value, int $min, int $max): ?int
    {
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            return null;
        }

        return (int) max($min, min($max, round((float) $value)));
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');
    }
}
