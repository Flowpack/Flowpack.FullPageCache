<?php
namespace Flowpack\FullPageCache\Http;

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
     * @var boolean
     * @Flow\InjectConfiguration(path="enabled")
     */
    protected $enabled;

    /**
     * @var boolean
     * @Flow\InjectConfiguration(path="maxPublicCacheTime")
     */
    protected $maxPublicCacheTime;

    /**
     * @inheritDoc
     */
    public function handle(ComponentContext $componentContext)
    {
        $request = $componentContext->getHttpRequest();
        $response = $componentContext->getHttpResponse();

        if ($response->hasHeader('X-From-FullPageCache')) {
            return;
        }

        if ($response->hasHeader('Set-Cookie')) {
            if ($response->hasHeader('X-CacheLifetime')) {
                $responseWithoutStorageHeaders = $response
                    ->withoutHeader('X-CacheTags')
                    ->withoutHeader('X-CacheLifetime');
                $componentContext->replaceHttpResponse($responseWithoutStorageHeaders);
            }
            return;
        }

        if ($response->hasHeader('X-CacheLifetime')) {
            $lifetime = (int)$response->getHeaderLine('X-CacheLifetime');
            $cacheTags = $response->getHeader('X-CacheTags') ;
            $entryIdentifier = md5((string)$request->getUri());

            if (!is_array($cacheTags)) {
                $cacheTags = [$cacheTags];
            }

            $publicLifetime = 0;
            if ($this->maxPublicCacheTime > 0) {
                if ($lifetime > 0 && $lifetime < $this->maxPublicCacheTime) {
                    $publicLifetime = $lifetime;
                } else {
                    $publicLifetime = $this->maxPublicCacheTime;
                }
            }

            $modifiedResponse = $response
                ->withoutHeader('X-CacheTags')
                ->withoutHeader('X-CacheLifetime');

            if ($publicLifetime > 0) {
                $entryContentHash = md5(str($response));
                $modifiedResponse = $modifiedResponse
                    ->withAddedHeader('ETag', $entryContentHash)
                    ->withAddedHeader('CacheControl', 'max-age=' . $publicLifetime);
            }

            $modifiedResponseforStorage = $modifiedResponse
                ->withHeader('X-Storage-Timestamp', time())
                ->withHeader('X-Storage-Lifetime', $lifetime);

            $this->cacheFrontend->set($entryIdentifier, str($modifiedResponseforStorage), $cacheTags, $lifetime);

            $modifiedResponse->getBody()->rewind();
            $componentContext->replaceHttpResponse($modifiedResponse);
        }
    }
}
