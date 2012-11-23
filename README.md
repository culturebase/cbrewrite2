# CbRewrite2

This class can be used to route requests to specific handlers. The general
approach is to match the request URI against a set of regular expressions
(routes). The routes are applied in the defined order. The first matching route
is used. If no route matches or the request is empty, the fallback params
(defined by setFallback()) are returned.

Named groups can and should be used. You still get numerical indexes when using
named groups, though. The recognized groups, named or not, can be merged into
$\_GET. Like this you can either give parameters by path, e.g. "de\_DE/index" or
by query string, e.g. "?language=de_DE&page=index" and they'll be treated in the
same way.

# Examples
Named groups are defined like:

    (?<name>regexp)

For example:

    (?<page>[a-z_]+)

This match would be accessible via:

    $rewrittenParams['page']

Simple usage example:

    CbRewriter2::create(array(
       '/^(?<language>\w+)\/(?<page>\w+)$/'
    ))->setFallback(array(
       'language' => 'en_EN',
       'page'     => 'index'
    ))->mergeGet();