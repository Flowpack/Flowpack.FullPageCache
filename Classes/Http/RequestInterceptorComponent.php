<?php
namespace Flowpack\FullPageCache\Http;

use Neos\Flow\Annotations as Flow;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Http\Component\ComponentChain;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Neos\Flow\Security\SessionDataContainer;
use Neos\Flow\Session\SessionManagerInterface;
use GuzzleHttp\Psr7\Response;
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
     * @var boolean
     * @Flow\InjectConfiguration(path="maxPublicCacheTime")
     */
    protected $maxPublicCacheTime;

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
            if (class_exists('Neos\\Flow\\Http\\Response')) {
                $cachedResponse = \Neos\Flow\Http\Response::createFromRaw($entry);
            } else {
                $cachedResponse = parse_response($entry);
            }

            $etag = $cachedResponse->getHeaderLine('ETag');
            $lifetime = (int)$cachedResponse->getHeaderLine('X-Storage-Lifetime');
            $timestamp = (int)$cachedResponse->getHeaderLine('X-Storage-Timestamp');

            // return 304 not modified when possible
            $ifNoneMatch = $request->getHeaderLine('If-None-Match');
            if ($ifNoneMatch && ($ifNoneMatch === $etag || $ifNoneMatch === 'W/' . $etag)) {
                if (class_exists('Neos\\Flow\\Http\\Response')) {
                    $notModifiedResponse = new \Neos\Flow\Http\Response();
                } else {
                    $notModifiedResponse = new Response();
                }
                $notModifiedResponse = $notModifiedResponse
                    ->withStatus(304)
                    ->withHeader('X-From-FullPageCache', $entryIdentifier);

                $componentContext->replaceHttpResponse($notModifiedResponse);
                $componentContext->setParameter(ComponentChain::class, 'cancel', true);
                return;
            }

            $cachedResponse = $cachedResponse
                ->withoutHeader('X-Storage-Lifetime')
                ->withoutHeader('X-Storage-Timestamp')
                ->withHeader('X-From-FullPageCache', $entryIdentifier);

            if ($this->maxPublicCacheTime > 0) {
                if ($lifetime > 0) {
                    $remainingCacheTime = $lifetime - (time() - $timestamp);
                    if ($remainingCacheTime > $this->maxPublicCacheTime) {
                        $remainingCacheTime = $this->maxPublicCacheTime;
                    }
                    if ($remainingCacheTime > 0) {
                        $cachedResponse = $cachedResponse
                            ->withHeader('CacheControl', 'max-age=' . $remainingCacheTime);
                    }
                } else {
                    $cachedResponse = $cachedResponse
                        ->withHeader('CacheControl', 'max-age=' . $this->maxPublicCacheTime);
                }
            }

            $componentContext->replaceHttpResponse($cachedResponse);
            $componentContext->setParameter(ComponentChain::class, 'cancel', true);
        }
    }
}
