<?php
namespace Flowpack\FullPageCache\Http;

use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use function GuzzleHttp\Psr7\str;

/**
 *
 */
class RequestStorageComponent implements ComponentInterface
{
    /**
     * @Flow\Inject
     * @var StringFrontend
     */
    protected $cacheFrontend;

    /**
     * @inheritDoc
     */
    public function handle(ComponentContext $componentContext)
    {
        $request = $componentContext->getHttpRequest();
        if (strtoupper($request->getMethod()) !== 'GET') {
            return;
        }

        if (!empty($request->getUri()->getQuery())) {
            return;
        }

        $response = $componentContext->getHttpResponse();

        if ($response->hasHeader('X-From-FullPageCache')) {
            return;
        }

        $entryIdentifier = md5((string)$request->getUri());

        $modifiedResponse = $response->withHeader('X-Storage-Component', $entryIdentifier);
        $this->cacheFrontend->set($entryIdentifier, str($modifiedResponse));
    }
}

