<?php
namespace Flowpack\FullPageCache\Http;

use Flowpack\FullPageCache\Aspects\ContentCacheAspect;
use Flowpack\FullPageCache\Cache\MetadataAwareStringFrontend;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use function GuzzleHttp\Psr7\str;

/**
 * The HTTP component to store a full
 */
class RequestStorageComponent implements ComponentInterface
{
    /**
     * @Flow\Inject
     * @var StringFrontend
     */
    protected $cacheFrontend;

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

        $entryIdentifier = md5((string)$request->getUri());

        [$tags, $lifetime] = $this->getCacheTagsAndLifetime();

        if (empty($tags)) {
            // For now do not cache something without tags (maybe it was not a Neos page)
            return;
        }

        $modifiedResponse = $response->withHeader('X-Storage-Component', $entryIdentifier);
        $this->cacheFrontend->set($entryIdentifier, str($modifiedResponse), $tags, $lifetime);
        // TODO: because stream is copied ot the modifiedResponse we would get empty output on first request
        $response->getBody()->rewind();
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
