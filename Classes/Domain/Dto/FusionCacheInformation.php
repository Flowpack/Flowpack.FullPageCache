<?php

declare(strict_types=1);

namespace Flowpack\FullPageCache\Domain\Dto;

readonly class FusionCacheInformation
{
    /**
     * @param bool $hasUncachedSegments
     * @param string[] $tags
     * @param int|null $lifetime
     */
    public function __construct(
        public bool $hasUncachedSegments,
        public array $tags,
        public ?int $lifetime
    ) {
    }
}
