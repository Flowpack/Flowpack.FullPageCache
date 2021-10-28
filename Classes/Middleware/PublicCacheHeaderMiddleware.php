<?php
declare(strict_types=1);

namespace Flowpack\FullPageCache\Middleware;

use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PublicCacheHeaderMiddleware implements MiddlewareInterface
{

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

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        if (!$this->enabled || !$request->hasHeader(RequestCacheMiddleware::HEADER_ENABLED) || $this->maxPublicCacheTime == 0) {
            return $next->handle($request);
        }

        $response = $next->handle($request);

        if ($response->hasHeader("CacheControl")) {
            return $response;
        }

        $lifetime = (int)$response->getHeaderLine(RequestCacheMiddleware::HEADER_LIFTIME);

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
                ->withHeader('CacheControl', 'public, max-age=' . $publicLifetime);
        }

        return $response;
    }
}
