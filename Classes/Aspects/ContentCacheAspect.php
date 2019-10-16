<?php
namespace Flowpack\FullPageCache\Aspects;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;

/**
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class ContentCacheAspect
{
    private $hadUncachedSegments = false;

    private $cacheTags = [];

    /**
     * @var null|int
     */
    private $shortestLifetime = null;

    /**
     * @Flow\Before("method(Neos\Fusion\Core\Cache\ContentCache->createCacheSegment())")
     */
    public function grabCachedSegment(JoinPointInterface $joinPoint)
    {
        $tags = $joinPoint->getMethodArgument('tags');
        $lifetime = $joinPoint->getMethodArgument('lifetime');

        foreach ($tags as $tag) {
            $this->cacheTags[$tag] = true;
        }

        if ($lifetime !== null && $lifetime < $this->shortestLifetime) {
            $this->shortestLifetime = $lifetime;
        }
    }

    /**
     * @Flow\Before("method(Neos\Fusion\Core\Cache\ContentCache->createDynamicCachedSegment())")
     */
    public function grabDynamicCachedSegment(JoinPointInterface $joinPoint)
    {
        $tags = $joinPoint->getMethodArgument('tags');
        $lifetime = $joinPoint->getMethodArgument('lifetime');

        foreach ($tags as $tag) {
            $this->cacheTags[$tag] = true;
        }

        if ($lifetime === null) {
            return;
        }

        if ($this->shortestLifetime === null) {
            $this->shortestLifetime = $lifetime;
            return;
        }

        if ($lifetime < $this->shortestLifetime) {
            $this->shortestLifetime = $lifetime;
        }
    }

    /**
     * @Flow\Before("method(Neos\Fusion\Core\Cache\ContentCache->createUncachedSegment())")
     */
    public function grabUncachedSegment(JoinPointInterface $joinPoint)
    {
        $this->hadUncachedSegments = true;
    }

    /**
     * @return array
     */
    public function getAllCacheTags(): array
    {
        return $this->sanitizeTags(array_keys($this->cacheTags));
    }

    /**
     * @return int|null
     */
    public function getShortestLifetime(): ?int
    {
        return $this->shortestLifetime;
    }

    /**
     * @return bool
     */
    public function hasUncachedSegments(): bool
    {
        return $this->hadUncachedSegments;
    }

    /**
     * Sanitizes the given tag for use with the cache framework
     *
     * @param string $tag A tag which possibly contains non-allowed characters, for example "NodeType_Acme.Com:Page"
     * @return string A cleaned up tag, for example "NodeType_Acme_Com-Page"
     */
    protected function sanitizeTag($tag)
    {
        return strtr($tag, '.:', '_-');
    }

    /**
     * Sanitizes multiple tags with sanitizeTag()
     *
     * @param array $tags Multiple tags
     * @return array The sanitized tags
     */
    protected function sanitizeTags(array $tags)
    {
        foreach ($tags as $key => $value) {
            $tags[$key] = $this->sanitizeTag($value);
        }

        return $tags;
    }
}
