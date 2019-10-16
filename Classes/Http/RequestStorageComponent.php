<?php
namespace Flowpack\FullPageCache\Http;

use Flowpack\FullPageCache\Aspects\ContentCacheAspect;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use function GuzzleHttp\Psr7\str;

/**
 *
 */
class RequestStorageComponent implements ComponentInterface
{
    /**
     * @Flow\Inject
     * @var StringFrontend
     */
    protected $cacheFrontend;

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

        $lifetime = $this->contentCacheAspect->getShortestLifetime();
        $tags = $this->contentCacheAspect->getAllCacheTags();

        $modifiedResponse = $response->withHeader('X-Storage-Component', $entryIdentifier);
        $this->cacheFrontend->set($entryIdentifier, str($modifiedResponse), $tags, $lifetime);
        // TODO: because stream is copied ot the modifiedResponse we would get empty output on first request
        $response->getBody()->rewind();
    }
}
