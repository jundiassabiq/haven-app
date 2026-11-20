<?php
declare(strict_types=1);

/**
 * Minimal router: exact paths and {param} segments, per HTTP method.
 * A route is [method, pattern, handler, middleware[]]. Handler is [ControllerClass, 'method'] or a callable.
 */
final class Router
{
    /** @var array<int, array{method:string, path:string, regex:string, handler:mixed, middleware:array}> */
    private array $routes = [];

    public function get(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, mixed $handler, array $middleware = []): self
    {
        $path = self::normalize($path);
        // preg_quote escapes the braces, so placeholders are matched in their escaped form "\{name\}".
        $regex = preg_replace_callback(
            '#\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}#',
            static fn (array $m) => '(?P<' . $m[1] . '>[^/]+)',
            preg_quote($path, '#')
        );
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
        return $this;
    }

    /**
     * Finds the route for a request.
     * @return array{handler:mixed, params:array<string,string>, middleware:array}
     * @throws HttpException 404 when no path matches, 405 when the path exists for another method.
     */
    public function match(string $method, string $path): array
    {
        $method = strtoupper($method) === 'HEAD' ? 'GET' : strtoupper($method);
        $path = self::normalize($path);
        $pathMatched = false;

        foreach ($this->routes as $r) {
            if (!preg_match($r['regex'], $path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($r['method'] !== $method) {
                continue;
            }
            $params = array_map('rawurldecode', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
            return ['handler' => $r['handler'], 'params' => $params, 'middleware' => $r['middleware']];
        }
        throw new HttpException($pathMatched ? 405 : 404);
    }

    public static function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return preg_replace('#/+#', '/', $path);
    }
}
