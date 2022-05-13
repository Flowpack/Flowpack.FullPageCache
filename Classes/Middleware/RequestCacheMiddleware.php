<?php
declare(strict_types=1);

namespace Flowpack\FullPageCache\Middleware;

use GuzzleHttp\Psr7\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Cache\Frontend\VariableFrontend;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestCacheMiddleware implements MiddlewareInterface
{
    public const HEADER_ENABLED = 'X-FullPageCache-Enabled';

    public const HEADER_INFO = 'X-FullPageCache-Info';

    public const HEADER_LIFETIME = 'X-FullPageCache-Lifetime';

    public const HEADER_TAGS = 'X-FullPageCache-Tags';

    #[Flow\InjectConfiguration('enabled')]
    protected bool $enabled;

    /**
     * @var VariableFrontend
     */
    #[Flow\Inject]
    protected $cacheFrontend;

    #[Flow\InjectConfiguration('request.queryParams.allow')]
    protected array $allowedQueryParams;

    #[Flow\InjectConfiguration('request.queryParams.ignore')]
    protected array $ignoredQueryParams;

    #[Flow\InjectConfiguration('request.cookieParams.ignore')]
    protected array $ignoredCookieParams;

    #[Flow\InjectConfiguration('maxPublicCacheTime')]
    protected int $maxPublicCacheTime;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $entryIdentifier = $this->getCacheIdentifierForRequestIfCacheable($request);

        if (is_null($entryIdentifier)) {
            return $handler->handle($request)->withHeader(self::HEADER_INFO, 'SKIP');
        }

        if ($cacheEntry = $this->cacheFrontend->get($entryIdentifier)) {
            $age = time() - $cacheEntry['timestamp'];
            $response = Message::parseResponse($cacheEntry['response']);
            return $response
                ->withHeader('Age', $age)
                ->withHeader(self::HEADER_INFO, 'HIT: ' . $entryIdentifier);
        }

        $response = $handler->handle($request->withHeader(self::HEADER_ENABLED, ''));

        if ($response->hasHeader(self::HEADER_ENABLED)) {
            $lifetime = $response->hasHeader(self::HEADER_LIFETIME) ? (int)$response->getHeaderLine(self::HEADER_LIFETIME) : null;
            $tags = $response->hasHeader(self::HEADER_TAGS) ? $response->getHeader(self::HEADER_TAGS) : [];
            $response = $response
                ->withoutHeader(self::HEADER_ENABLED)
                ->withoutHeader(self::HEADER_LIFETIME)
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

    protected function getCacheIdentifierForRequestIfCacheable(ServerRequestInterface $request): ?string
    {
        if (!in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'])) {
            return null;
        }

        $requestQueryParams = $request->getQueryParams();
        $allowedQueryParams = [];
        $disallowedQueryParams = [];
        foreach ($requestQueryParams as $key => $value) {
            switch (true) {
                case (in_array($key, $this->allowedQueryParams)):
                    $allowedQueryParams[$key] = $value;
                    break;
                case (in_array($key, $this->ignoredQueryParams)):
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
