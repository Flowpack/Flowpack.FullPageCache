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
     * @inheritDoc
     */
    public function handle(ComponentContext $componentContext)
    {
        $request = $componentContext->getHttpRequest();
        $response = $componentContext->getHttpResponse();

        if ($response->hasHeader('X-From-FullPageCache')) {
            return;
        }

        if ($response->hasHeader('CacheControl')) {
            $cacheControl = $response->getHeaderLine('CacheControl');
            if ($cacheControl && strpos($cacheControl,'s-maxage=') === 0) {
                $lifetime =  (int) substr($cacheControl, 10);
                $cacheTags = $response->getHeader('X-CacheTags') ;

                $entryIdentifier = md5((string)$request->getUri());
                $etag = md5(str($response));

                $modifiedResponse = $response
                    ->withoutHeader('X-CacheTags')
                    ->withoutHeader('CacheControl')
                    ->withAddedHeader('ETag', $etag)
                    ->withAddedHeader('CacheControl', 'max-age=' . $lifetime);

                $modifiedResponseforStorage = $modifiedResponse
                    ->withHeader('X-Storage-Component', $entryIdentifier)
                    ->withHeader('X-Storage-Timestamp', time())
                    ->withHeader('X-Storage-Lifetime', $lifetime);

                $this->cacheFrontend->set($entryIdentifier, str($modifiedResponseforStorage), $cacheTags, $lifetime);
                $response->getBody()->rewind();

                $ifNoneMatch = $request->getHeaderLine('If-None-Match');
                if ($ifNoneMatch &&  $ifNoneMatch === $etag ) {
                    $notModifiedResponse = (new Response(304))
                        ->withAddedHeader('CacheControl', 'max-age=' . $lifetime)
                        ->withHeader('X-From-FullPageCache', $entryIdentifier);
                    $componentContext->replaceHttpResponse($notModifiedResponse);
                } else {
                    $componentContext->replaceHttpResponse($modifiedResponse);
                }
            }
        }
    }
}
