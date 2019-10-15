<?php
namespace Flowpack\FullPageCache;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\Flow\Core\Bootstrap;
use Neos\Neos\Service\PublishingService;

/**
 * Class Package
 *
 * @package Flowpack\FullPageCache
 */
class Package extends \Neos\Flow\Package\Package {

    /**
     * @inheritDoc
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();

        $dispatcher->connect(Node::class, 'nodeUpdated', FullCacheFlusher::class, 'registerNodeChange', false);
        $dispatcher->connect(Node::class, 'nodeAdded', FullCacheFlusher::class, 'registerNodeChange', false);
        $dispatcher->connect(Node::class, 'nodeRemoved', FullCacheFlusher::class, 'registerNodeChange', false);

        $dispatcher->connect(PublishingService::class, 'nodePublished', FullCacheFlusher::class, 'registerNodeChange', false);
        $dispatcher->connect(PublishingService::class, 'nodeDiscarded', FullCacheFlusher::class, 'registerNodeChange', false);
    }
}
