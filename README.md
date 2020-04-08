Full Page Cache
===============

This package is meant to cache full HTTP responses for super fast delivery time. It currently works with Neos but a plan is there to provide the functionality for Flow as well.

It works by checking if a page is fully cachable and in that case caching the whole HTTP response and delivering it immediately. So this won't have much effect on websites with uncached elements on every page. Also POST requests and requests with query arguments (unless you configured `queryArguments`) are excluded from caching, as well as requests that set cookies. So if you don't see a difference after installing, these are things to look out for.

Settings
--------

```yaml
Flowpack:
  FullPageCache:
    # enable full page caching
    enabled: true

    # the maximum public cache control header sent
    # set to 0 if you do not want to send public CacheControl headers
    maxPublicCacheTime: 86400

    # requests that include query arguments won't be cached by default to prevent unexpected caching behaviour
    # however, if you know what you are doing, use these settings to enable cached results for query arguments
    # if the request uri still has arguments that are not listed in 'include' or 'ignore', the default no-cache behaviour will apply
    queryArguments:

      # list of arguments that should response cached results
      # thus we will *include* these arguments when we create and/or lookup the cache keys
      include: []

      # list of arguments that should be ignored (typically arguments that won't change the rendered html)
      # thus we will *ignore* these arguments when we create and/or lookup the cache keys
      ignore: []
```

You can also move the cache backend to something faster if available, to improve performance even more.

Warning
-------

This package is still fairly new, if you install it, make sure to check really well if your page still works. Especially things like Forms and plugins. Ideally those pages should work but just not be faster unlike pure content pages. Still this was tested in limited scenarios so far, so make sure you tested properly before bringing this to production. It has no effect on the content itself, so deinstalling it will bring you back to the state you had before.
