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

class FusionAutoconfigurationMiddleware implements MiddlewareInterface
{
    public const HEADER_ENABLED = 'X-FullPageCache-EnableFusionAutoconfiguration';

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

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        if (!$this->enabled || !$request->hasHeader(RequestCacheMiddleware::HEADER_ENABLED)) {
            return $next->handle($request)->withoutHeader(self::HEADER_ENABLED);
        }

        $response = $next->handle($request);

        if (!$response->hasHeader(self::HEADER_ENABLED)) {
            return $response;
        } else {
            $response = $response->withoutHeader(self::HEADER_ENABLED);
        }

        list($hasUncachedSegments, $tags, $lifetime) = $this->getFusionCacheInformations();

        if ($response->hasHeader('Set-Cookie') || $hasUncachedSegments) {
            return $response;
        }

        $response = $response
            ->withHeader(RequestCacheMiddleware::HEADER_ENABLED, "");

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
