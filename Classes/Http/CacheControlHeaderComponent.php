<?php
namespace Flowpack\FullPageCache\Http;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Flowpack\FullPageCache\Cache\MetadataAwareStringFrontend;
use Flowpack\FullPageCache\Aspects\ContentCacheAspect;

/**
 * Cache control header component
 */
class CacheControlHeaderComponent implements ComponentInterface
{
    /**
     * @Flow\Inject
     * @var MetadataAwareStringFrontend
     */
    protected $contentCache;


    /**
     * @var boolean
     * @Flow\InjectConfiguration(path="enabled")
     */
    protected $enabled;

    /**
     * @Flow\Inject
     * @var ContentCacheAspect
     */
    protected $contentCacheAspect;

    /**
     * @inheritDoc
     */
    public function handle(ComponentContext $componentContext)
    {
        if (!$this->enabled) {
            return;
        }

        $request = $componentContext->getHttpRequest();
        if (strtoupper($request->getMethod()) !== 'GET') {
            return;
        }

        if (!empty($request->getUri()->getQuery())) {
            return;
        }

        $response = $componentContext->getHttpResponse();

        if ($response->hasHeader('X-From-FullPageCache')) {
            return;
        }

        if ($this->contentCacheAspect->hasUncachedSegments())
        {
            return;
        }

        if ($response->hasHeader('Set-Cookie')) {
            return;
        }

        [$tags, $lifetime] = $this->getCacheTagsAndLifetime();

        if ($tags) {
            $modifiedResponse = $response
                ->withHeader('X-CacheLifetime', $lifetime)
                ->withHeader('X-CacheTags', $tags);

            $componentContext->replaceHttpResponse($modifiedResponse);
        }
    }

    /**
     * Get cache tags and lifetime from the cache metadata that was extracted by the special cache frontend for content cache
     *
     * @return array with first "tags" and then "lifetime"
     */
    protected function getCacheTagsAndLifetime(): array
    {
        $lifetime = null;
        $tags = [];
        $entriesMetadata = $this->contentCache->getAllMetadata();
        foreach ($entriesMetadata as $identifier => $metadata) {
            $entryTags = isset($metadata['tags']) ? $metadata['tags'] : [];
            $entryLifetime = isset($metadata['lifetime']) ? $metadata['lifetime'] : null;
            if ($entryLifetime !== null) {
                if ($lifetime === null) {
                    $lifetime = $entryLifetime;
                } else {
                    $lifetime = min($lifetime, $entryLifetime);
                }
            }
            $tags = array_unique(array_merge($tags, $entryTags));
        }

        return [$tags, $lifetime];
    }
}
