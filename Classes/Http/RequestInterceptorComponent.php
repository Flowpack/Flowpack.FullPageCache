<?php
namespace Flowpack\FullPageCache\Http;

use GuzzleHttp\Psr7\Response;
use Neos\Flow\Annotations as Flow;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Http\Component\ComponentChain;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Neos\Flow\Security\SessionDataContainer;
use Neos\Flow\Session\SessionManagerInterface;
use function GuzzleHttp\Psr7\parse_response;

/**
 *
 */
class RequestInterceptorComponent implements ComponentInterface
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
     * @Flow\Inject(lazy=false)
     * @var SessionManagerInterface
     */
    protected $sessionManager;

    /**
     * @Flow\Inject
     * @var SessionDataContainer
     */
    protected $sessionDataContainer;

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

        if ($this->sessionManager->getCurrentSession()->isStarted() && !empty($this->sessionDataContainer->getSecurityTokens())) {
            return;
        }

        $entryIdentifier = md5((string)$request->getUri());

        $entry = $this->cacheFrontend->get($entryIdentifier);
        if ($entry) {
            $cachedResponse = parse_response($entry);

            $etag = $cachedResponse->getHeaderLine('ETag');
            $lifetime = (int)$cachedResponse->getHeaderLine('X-Storage-Lifetime');
            $timestamp = (int)$cachedResponse->getHeaderLine('X-Storage-Timestamp');
            $age = time() - $timestamp;

            if ($age > $lifetime) {
                return;
            }

            $ifNoneMatch = $request->getHeaderLine('If-None-Match');
            if ($ifNoneMatch &&  $ifNoneMatch === $etag ) {
                $response = (new Response( 304))
                    ->withHeader('CacheControl', 'max-age=' . ($lifetime - $age))
                    ->withHeader('X-From-FullPageCache', $entryIdentifier);
            } else {
                $response = $cachedResponse
                    ->withoutHeader('X-Storage-Lifetime')
                    ->withoutHeader('X-Storage-Timestamp')
                    ->withoutHeader('CacheControl')
                    ->withHeader('CacheControl', 'max-age=' . ($lifetime - $age))
                    ->withHeader('X-From-FullPageCache', $entryIdentifier);
            }

            $componentContext->replaceHttpResponse($response);
            $componentContext->setParameter(ComponentChain::class, 'cancel', true);
        }
    }
}
