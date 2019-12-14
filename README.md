Full Page Cache
===============

This package is meant to cache full HTTP responses for super fast delivery time. It currently works with Neos but a plan is there to provide the functionality for Flow as well.

It works by checking if a page is fully cachable and in that case caching the  whole HTTP response and delivering it immediately. So this won't have much effect on  websites with uncached elements on every page. Also POST requests and requests with query arguments are excluded from caching, as well as requests that set cookies. So if you don't see a difference after installing, these are things to look out for.

Settings
--------

Two settings are available to you to influence the behavior.

```
Flowpack:
  FullPageCache:
    # enable full page caching
    enabled: true

    # the maximum public cache control header sent
    # set to 0 if you do not want to send public CacheControl headers
    maxPublicCacheTime: 86400
```

You can also move the cache backend to something faster if available, to improve performance even more.

Warning
-------

This package is still fairly new, if you install it, make sure to check really well if your page still works. Especially things like Forms and plugins. Ideally those pages should work but just not be faster unlike pure content pages. Still this was tested in limited scenarios so far, so make sure you tested properly before bringing this to production. It has no effect on the content itself, so deinstalling it will bring you back to the state you had before.
