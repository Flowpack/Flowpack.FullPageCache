<?php
declare(strict_types=1);

namespace Flowpack\FullPageCache\Middleware;

use Neos\Flow\Annotations as Flow;
use Neos\Cache\Frontend\VariableFrontend;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GuzzleHttp\Psr7\Message;
use function GuzzleHttp\Psr7\str;

class RequestCacheMiddleware implements MiddlewareInterface
{
    public const HEADER_ENABLED = 'X-FullPageCache-Enabled';

    public const HEADER_INFO = 'X-FullPageCache-Info';

    public const HEADER_LIFTIME = 'X-FullPageCache-Lifetime';

    public const HEADER_TAGS = 'X-FullPageCache-Tags';

    /**
     * @var boolean
     * @Flow\InjectConfiguration(path="enabled")
     */
    protected $enabled;

    /**
     * @Flow\Inject
     * @var VariableFrontend
     */
    protected $cacheFrontend;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="request.queryParams.allow")
     */
    protected $allowedQueryParams;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="request.queryParams.ignore")
     */
    protected $ignoredQueryParams;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="request.cookieParams.ignore")
     */
    protected $ignoredCookieParams;

    /**
     * @var boolean
     * @Flow\InjectConfiguration(path="maxPublicCacheTime")
     */
    protected $maxPublicCacheTime;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        if (!$this->enabled) {
            return $next->handle($request);
        }

        $entryIdentifier = $this->getCacheIdentifierForRequestIfCacheable($request);

        if (is_null($entryIdentifier)) {
            return $next->handle($request)->withHeader(self::HEADER_INFO, 'SKIP');
        }

        if ($cacheEntry = $this->cacheFrontend->get($entryIdentifier)) {
            $age = time() - $cacheEntry['timestamp'];
            $response = Message::parseResponse($cacheEntry['response']);
            return $response
                ->withHeader('Age', $age)
                ->withHeader(self::HEADER_INFO, 'HIT: ' . $entryIdentifier);
        }

        $response = $next->handle($request->withHeader(self::HEADER_ENABLED, ''));

        if ($response->hasHeader(self::HEADER_ENABLED)) {
            $lifetime = $response->hasHeader(self::HEADER_LIFTIME) ? (int)$response->getHeaderLine(self::HEADER_LIFTIME) : null;
            $tags = $response->hasHeader(self::HEADER_TAGS) ? $response->getHeader(self::HEADER_TAGS) : [];
            $response = $response
                ->withoutHeader(self::HEADER_ENABLED)
                ->withoutHeader(self::HEADER_LIFTIME)
                ->withoutHeader(self::HEADER_TAGS);

            $publicLifetime = 0;
            if ($this->maxPublicCacheTime > 0) {
                if ($lifetime > 0 && $lifetime < $this->maxPublicCacheTime) {
                    $publicLifetime = $lifetime;
                } else {
                    $publicLifetime = $this->maxPublicCacheTime;
                }
            }

            if ($publicLifetime > 0) {
                $entryContentHash = md5($response->getBody()->getContents());
                $response->getBody()->rewind();
                $response = $response
                    ->withHeader('ETag', '"' . $entryContentHash . '"')
                    ->withHeader('Cache-Control', 'public, max-age=' . $publicLifetime);
            }

            $this->cacheFrontend->set($entryIdentifier,[ 'timestamp' => time(), 'response' => Message::toString($response) ], $tags, $lifetime);
            $response->getBody()->rewind();
            return $response->withHeader(self::HEADER_INFO, 'MISS: ' . $entryIdentifier);
        }

        return $response;
    }


    /**
     * @param ServerRequestInterface $request
     * @return string|null
     */
    protected function getCacheIdentifierForRequestIfCacheable(ServerRequestInterface $request): ?string
    {
        if (!in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'])) {
            return null;
        }

        $requestQueryParams = $request->getQueryParams();
        $allowedQueryParams = [];
        $ignoredQueryParams = [];
        $disallowedQueryParams = [];
        foreach ($requestQueryParams as $key => $value) {
            switch (true) {
                case (in_array($key, $this->allowedQueryParams)):
                    $allowedQueryParams[$key] = $value;
                    break;
                case (in_array($key, $this->ignoredQueryParams)):
                    $ignoredQueryParams[$key] = $value;
                    break;
                default:
                    $disallowedQueryParams[$key] = $value;
            }
        }

        if (count($disallowedQueryParams) > 0) {
            return null;
        }

        $requestCookieParams = $request->getCookieParams();
        $disallowedCookieParams = [];
        foreach ($requestCookieParams as $key => $value) {
            if (!in_array($key, $this->ignoredCookieParams)) {
                $disallowedCookieParams[$key] = $value;
            }
        }

        if (count($disallowedCookieParams) > 0) {
            return null;
        }

        return md5((string)$request->getUri()->withQuery(http_build_query($allowedQueryParams)));
    }
}
