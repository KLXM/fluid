<?php

namespace Klxm\Fluid\Media;

use Redaxo\Core\MediaManager\AsMediaType;
use Redaxo\Core\MediaManager\MediaContext;
use Redaxo\Core\MediaManager\MediaType;
use Redaxo\Core\MediaManager\NegotiatesFormat;
use Redaxo\Core\MediaManager\NegotiatesImageFormats;

/**
 * Media manager type „fluid_poster“: chosen poster image of a video, scaled down to at most 1600 px, AVIF/WebP
 * depending on the browser. A still frame of the video itself comes from the addon video_editor
 * (`VideoEditor::frameUrl()`, type „video_editor_poster“).
 *
 *     MediaManager::getUrl('fluid_poster', 'poster.jpg')
 *
 * @internal
 */
#[AsMediaType('fluid_poster', maxSize: 1600)]
final readonly class PosterType implements MediaType, NegotiatesFormat
{
    use NegotiatesImageFormats;

    public function __construct(
        private int $maxSize,
    ) {}

    public function process(MediaContext $context): void
    {
        $context->image->scaleDown($this->maxSize, $this->maxSize);
    }
}
