<?php
declare(strict_types=1);

namespace Flowpack\FullPageCache\Middleware;

use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Flowpack\FullPageCache\Aspects\ContentCacheAspect;
use Flowpack\FullPageCache\Cache\MetadataAwareStringFrontend;

class CacheHeaderMiddleware implements MiddlewareInterface
{

    /**
     * @Flow\Inject
     * @var MetadataAwareStringFrontend
     */
    protected $contentCache;

    /**
     * @Flow\Inject
     * @var ContentCacheAspect
     */
    protected $contentCacheAspect;

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
        if (!$this->enabled || !$request->hasHeader(RequestCacheMiddleware::HEADER_ENABLED)) {
            return $next->handle($request);
        }

        $response = $next->handle($request);

        list($hasUncachedSegments, $tags, $lifetime) = $this->getFusionCacheInformations();

        if ($response->hasHeader('Set-Cookie') || $hasUncachedSegments) {
            return $response;
        }

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

        if ($tags) {
            $response = $response
                ->withHeader(RequestCacheMiddleware::HEADER_TAGS, $tags);
        }

        if ($lifetime) {
            $response = $response
                ->withHeader(RequestCacheMiddleware::HEADER_LIFTIME, $lifetime);
        }

        return $response;
    }

    /**
     * Get cache tags and lifetime from the cache metadata that was extracted by the special cache frontend for content cache
     *
     * @return array with first "hasUncachedSegments", "tags" and "lifetime"
     */
    public function getFusionCacheInformations(): array
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
        $hasUncachedSegments = $this->contentCacheAspect->hasUncachedSegments();

        return [$hasUncachedSegments, $tags, $lifetime];
    }
}
