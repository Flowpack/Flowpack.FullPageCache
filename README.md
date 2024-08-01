Full Page Cache
===============

This package is meant to cache full HTTP responses for super fast delivery time. It currently works with Neos but a plan is there to provide the functionality for Flow as well.

It works by checking if a page is fully cachable and in that case caching the  whole HTTP response and delivering it immediately. So this won't have much effect on  websites with uncached elements on every page. Also POST requests and requests with query arguments are excluded from caching, as well as requests that set cookies. So if you don't see a difference after installing, these are things to look out for.

Settings
--------

Two settings are available to you to influence the behavior.

```yaml
Flowpack:
  FullPageCache:
    # enable full page caching
    enabled: true

    # the maximum public cache control header sent
    # set to 0 if you do not want to send public CacheControl headers
    maxPublicCacheTime: 86400

    # requests have to fulfill certain conditions for beeing cached
    request:
      # !!! Only the http methods "GET" and "HEAD" are supported !!!

      # a request will only qualify for caching if it contains no cookieParams that
      # are not ignored.
      cookieParams:
        # ignored cookie params exclude cookies that are handled by the frontend
        # and are not relevant for the backend. A usecase would be gdpr consent cookies
        # if they are only used on the client side
        ignore: []

      # a request will only qualify for caching if it only contains queryParams that
      # are allowed or ignored. All other arguments will prevent caching.
      queryParams:
        # allowed params become part of the cache identifier, use this for
        # arguments that modify the reponse but still allow caching like pagination
        allow: []

        # ignored arguments are not part of the cache identifier but do not
        # prevent caching either. Use this for arguments that are meaningless for
        # the backend like utm_campaign
        ignore: []
```

You can also move the cache backend to something faster if available, to improve performance even more.

How it works
------------

The package defines two http middlewares:
  
- `RequestCacheMiddleware`: If a request is cacheable the cache is asked first and only if no response is found the 
  request is passed down the middleware chain. The cache lifetime and tags are determined from the 
  `X-FullPageCache-Enabled`, `X-FullPageCache-Lifetime` and `X-FullPageCache-Tags` that are set by upstream middlewares 
  or controllers. Additionally the middleware adds `ETag` and `Cache-Control` Headers taking the lifetime and setting
  `maxPublicCacheTime` into account.

- `FusionAutoconfigurationMiddleware`: Connects to the fusion cache and extracts tags plus the allowed lifetime which is then 
  stored in the response headers `X-FullPageCache-Enabled`, `X-FullPageCache-Lifetime` and `X-FullPageCache-Tags`. 
  This component is only active if the header `X-FullPageCache-EnableFusionAutoconfiguration` is present in the response 
  which is set automatically for `Neos.Neos:Page`.

Custom controllers that want to control the caching behavior directly can set the headers `X-FullPageCache-Enabled`, 
`X-FullPageCache-Lifetime` and `X-FullPageCache-Tags` directly while fusion based controllers can enable the autoconfiguration
by setting the header `X-FullPageCache-EnableFusionAutoconfiguration`.

Warning
-------

This package is still fairly new, if you install it, make sure to check really well if your page still works. Especially things like Forms and plugins. Ideally those pages should work but just not be faster unlike pure content pages. Still this was tested in limited scenarios so far, so make sure you tested properly before bringing this to production. It has no effect on the content itself, so deinstalling it will bring you back to the state you had before.
