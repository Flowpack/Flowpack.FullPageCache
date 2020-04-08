<?php
namespace Flowpack\FullPageCache\Helper;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Helper\UriHelper;
use Psr\Http\Message\UriInterface;

/**
 * Helper functions for Flowpack.FullPageCache
 */
class CacheHelper
{
    /**
     * @var array
     * @Flow\InjectConfiguration(path="queryArguments.include")
     */
    protected $includeQueryArguments;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="queryArguments.ignore")
     */
    protected $ignoreQueryArguments;

    /**
     * @param UriInterface $uri
     * @return string|null
     */
    public function getEntryIdentifier(UriInterface $uri)
    {
        if (empty($uri->getQuery()))
            return md5((string)$uri);

        // don't cache requests that include query strings if there is no query argument configuration
        if (!empty($uri->getQuery()) && empty($this->includeQueryArguments) && empty($this->ignoreQueryArguments))
            return null;

        // process query arguments and decide if the request is cacheable
        $queryArguments = UriHelper::parseQueryIntoArguments($uri);
        $queryArgumentsWithoutIgnored = array_diff_key(
            $queryArguments,
            array_fill_keys($this->ignoreQueryArguments, true)
        );
        $queryArgumentsWithoutIgnoredAndWithoutIncluded = array_diff_key(
            $queryArgumentsWithoutIgnored,
            array_fill_keys($this->includeQueryArguments, true)
        );

        // not cacheable - if there are still arguments after filtering
        if (!empty($queryArgumentsWithoutIgnoredAndWithoutIncluded))
            return null;

        // use uri without ignored query arguments
        return md5((string)$uri->withQuery(http_build_query($queryArgumentsWithoutIgnored)));
    }
}
