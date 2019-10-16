<?php
namespace Flowpack\FullPageCache\Http;

use Neos\Flow\Annotations as Flow;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Http\Component\ComponentChain;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Neos\Flow\Security\SessionDataContainer;
use Neos\Flow\Session\SessionManagerInterface;
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
            $response = parse_response($entry);
            $response = $response->withHeader('X-From-FullPageCache', $entryIdentifier);
            $componentContext->replaceHttpResponse($response);
            $componentContext->setParameter(ComponentChain::class, 'cancel', true);
        }
    }
}
