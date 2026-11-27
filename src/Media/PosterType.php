<?php

namespace Klxm\Fluid\Media;

use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\MediaManager\AsMediaType;
use Redaxo\Core\MediaManager\Exception\MediaNotFoundException;
use Redaxo\Core\MediaManager\MediaContext;
use Redaxo\Core\MediaManager\MediaType;
use Redaxo\Core\MediaManager\NegotiatesFormat;
use Redaxo\Core\MediaManager\NegotiatesImageFormats;

use function dirname;
use function in_array;

/**
 * Media manager type „fluid_poster“: poster image for videos. For a video, ffmpeg grabs a typical still frame,
 * for an image it only downscales – so the type fits both automatic and chosen poster images.
 * Output at most 1600 px wide, AVIF/WebP depending on the browser; the media manager stores the result.
 *
 *     MediaManager::getUrl('fluid_poster', 'film.mp4')
 */
#[AsMediaType('fluid_poster', maxSize: 1600)]
final readonly class PosterType implements MediaType, NegotiatesFormat
{
    use NegotiatesImageFormats;

    public const array VIDEO = ['mp4', 'm4v', 'webm', 'mov', 'ogv'];

    public function __construct(
        private int $maxSize,
    ) {}

    public function process(MediaContext $context): void
    {
        if (in_array($context->sourceFormat, self::VIDEO, true)) {
            $tmp = Path::addonCache('fluid', 'poster-' . bin2hex(random_bytes(6)) . '.jpg');
            @mkdir(dirname($tmp), 0o775, true);
            try {
                if (null === Ffmpeg::frame($context->sourcePath, $tmp, $this->maxSize)) {
                    throw new MediaNotFoundException('No video frame (ffmpeg missing or failed).');
                }
                $context->decode((string) file_get_contents($tmp));
            } finally {
                @unlink($tmp);
            }
        }
        $context->image->scaleDown($this->maxSize, $this->maxSize);
    }
}
