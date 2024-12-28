<?php

namespace Flowpack\FullPageCache\Aspects;

use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Utility\ObjectAccess;

/**
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class ContentCacheAspect
{
    private bool $hadUncachedSegments = false;

    /**
     * @Flow\Inject
     * @var StringFrontend
     */
    protected $cacheFrontend;

    /**
     * @Flow\Before("method(Neos\Fusion\Core\Cache\ContentCache->(createUncachedSegment)())")
     */
    public function grabUncachedSegment(JoinPointInterface $joinPoint): void
    {
        $this->hadUncachedSegments = true;
    }

    /**
     * @Flow\Before("method(Neos\Neos\Fusion\Cache\ContentCacheFlusher->shutdownObject())")
     * @param JoinPointInterface $joinPoint
     *
     * @throws \Neos\Utility\Exception\PropertyNotAccessibleException
     */
    public function interceptNodeCacheFlush(JoinPointInterface $joinPoint): void
    {
        $object = $joinPoint->getProxy();

        $tags = ObjectAccess::getProperty($object, 'tagsToFlush', true);
        $tags = array_map([$this, 'sanitizeTag'], array_keys($tags));
        $this->cacheFrontend->flushByTags($tags);
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
}
