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
    private $hadUncachedSegments = false;

    private $cacheTags = [];

    /**
     * @var null|int
     */
    private $shortestLifetime = null;

    /**
     * @Flow\Inject
     * @var StringFrontend
     */
    protected $cacheFrontend;

    /**
     * @Flow\Before("method(Neos\Fusion\Core\Cache\ContentCache->(createUncachedSegment)())")
     */
    public function grabUncachedSegment(JoinPointInterface $joinPoint)
    {
        $this->hadUncachedSegments = true;
    }

    /**
     * This aspect is for Neos 8.x compatibility and can be removed, when Neos 8.x isn't supported anymore.
     * See: ContentCacheAspect::interceptNodeCacheFlush() for Neos 9.x cache flushing
     *
     * @Flow\Before("method(Neos\Neos\Fusion\Cache\ContentCacheFlusher->commit())")
     * @param JoinPointInterface $joinPoint
     *
     * @throws \Neos\Utility\Exception\PropertyNotAccessibleException
     */
    public function interceptLegacyNodeCacheFlush(JoinPointInterface $joinPoint)
    {
        $object = $joinPoint->getProxy();

        $tags = ObjectAccess::getProperty($object, 'tagsToFlush', true);
        $tags = array_map([$this, 'sanitizeTag'], array_keys($tags));
        $this->cacheFrontend->flushByTags($tags);
    }

    /**
     * @Flow\Before("method(Neos\Neos\Fusion\Cache\ContentCacheFlusher->flushTagsImmediately())")
     * @param JoinPointInterface $joinPoint
     *
     * @throws \Neos\Utility\Exception\PropertyNotAccessibleException
     */
    public function interceptNodeCacheFlush(JoinPointInterface $joinPoint)
    {
        $tags = $joinPoint->getMethodArgument('tagsToFlush');
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
