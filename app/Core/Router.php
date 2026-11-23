<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

/**
 * Front-controller router with route parameters, groups (prefix + middleware)
 * and a middleware pipeline.
 *
 *   $router->get('/students/{id:\d+}', [StudentController::class, 'show'], ['auth', 'can:students.view']);
 */
final class Router
{
    /** {name} or {name:regex}; the regex may contain one level of braces, e.g. {code:[a-z]{2}}. */
    private const PARAM_PATTERN = '#\{([a-zA-Z_]\w*)(?::((?:[^{}]|\{[^{}]*\})+))?\}#';

    /** @var list<array{method: string, uri: string, regex: string, action: array{0: class-string, 1: string}, middleware: list<string>, name: ?string}> */
    private array $routes = [];

    /** @var list<array{prefix: string, middleware: list<string>}> */
    private array $groupStack = [];

    /** @var list<string> Middleware applied to every matched route. */
    private array $globalMiddleware = [];

    /** @var array<string, string> */
    private array $named = [];

    /** @param array<string, class-string<Middleware>> $aliases */
    public function __construct(private readonly array $aliases = [])
    {
    }

    /** @param list<string> $middleware */
    public function setGlobalMiddleware(array $middleware): void
    {
        $this->globalMiddleware = $middleware;
    }

    /**
     * @param array{0: class-string, 1: string} $action
     * @param list<string> $middleware
     */
    public function get(string $uri, array $action, array $middleware = [], ?string $name = null): void
    {
        $this->add('GET', $uri, $action, $middleware, $name);
    }

    /** @param array{0: class-string, 1: string} $action @param list<string> $middleware */
    public function post(string $uri, array $action, array $middleware = [], ?string $name = null): void
    {
        $this->add('POST', $uri, $action, $middleware, $name);
    }

    /** @param array{0: class-string, 1: string} $action @param list<string> $middleware */
    public function put(string $uri, array $action, array $middleware = [], ?string $name = null): void
    {
        $this->add('PUT', $uri, $action, $middleware, $name);
    }

    /** @param array{0: class-string, 1: string} $action @param list<string> $middleware */
    public function patch(string $uri, array $action, array $middleware = [], ?string $name = null): void
    {
        $this->add('PATCH', $uri, $action, $middleware, $name);
    }

    /** @param array{0: class-string, 1: string} $action @param list<string> $middleware */
    public function delete(string $uri, array $action, array $middleware = [], ?string $name = null): void
    {
        $this->add('DELETE', $uri, $action, $middleware, $name);
    }

    /** @param array{prefix?: string, middleware?: list<string>} $attributes */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix' => trim($attributes['prefix'] ?? '', '/'),
            'middleware' => $attributes['middleware'] ?? [],
        ];
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * @param array{0: class-string, 1: string} $action
     * @param list<string> $middleware
     */
    private function add(string $method, string $uri, array $action, array $middleware, ?string $name): void
    {
        $prefix = '';
        $groupMiddleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'] !== '' ? '/' . $group['prefix'] : '';
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
        }

        $uri = '/' . trim($prefix . '/' . trim($uri, '/'), '/');

        // {id} -> any segment, {id:\d+} -> custom regex.
        $regex = preg_replace_callback(
            self::PARAM_PATTERN,
            static fn (array $m): string => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $uri
        );

        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'regex' => '#^' . $regex . '$#u',
            'action' => $action,
            'middleware' => array_merge($groupMiddleware, $middleware),
            'name' => $name,
        ];

        if ($name !== null) {
            $this->named[$name] = $uri;
        }
    }

    /** Build a path for a named route: route('students.show', ['id' => 5]). */
    public function path(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw new RuntimeException("Route [{$name}] is not defined.");
        }

        return preg_replace_callback(
            self::PARAM_PATTERN,
            static fn (array $m): string => rawurlencode((string) ($params[$m[1]] ?? '')),
            $this->named[$name]
        ) ?? $this->named[$name];
    }

    /**
     * Registered routes (read only; used by the access-rights test and diagnostics).
     *
     * @return list<array{method: string, uri: string, regex: string, action: array{0: class-string, 1: string}, middleware: list<string>, name: ?string}>
     */
    public function routes(): array
    {
        return $this->routes;
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }

            $methodMatches = $route['method'] === $request->method
                || ($request->method === 'HEAD' && $route['method'] === 'GET');
            if (!$methodMatches) {
                $allowed[] = $route['method'];
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $request->setRouteParams($params);

            $core = fn (Request $req): Response => $this->callAction($route['action'], $req, $params);
            $middleware = array_merge($this->globalMiddleware, $route['middleware']);

            return $this->pipeline($middleware, $core)($request);
        }

        if ($allowed !== []) {
            throw new HttpException(405, '', ['Allow' => implode(', ', array_unique($allowed))]);
        }

        throw new HttpException(404);
    }

    /**
     * @param list<string> $middleware
     * @param Closure(Request): Response $core
     * @return Closure(Request): Response
     */
    private function pipeline(array $middleware, Closure $core): Closure
    {
        $next = $core;
        foreach (array_reverse($middleware) as $definition) {
            [$alias, $paramString] = array_pad(explode(':', $definition, 2), 2, '');
            $class = $this->aliases[$alias] ?? $alias;
            if (!class_exists($class) || !is_subclass_of($class, Middleware::class)) {
                throw new RuntimeException("Middleware [{$alias}] is not registered.");
            }
            $params = $paramString === '' ? [] : explode(',', $paramString);
            $instance = new $class();
            $next = static fn (Request $req): Response => $instance->handle($req, $next, ...$params);
        }

        return $next;
    }

    /**
     * @param array{0: class-string, 1: string} $action
     * @param array<string, string> $params
     */
    private function callAction(array $action, Request $request, array $params): Response
    {
        [$class, $method] = $action;
        $controller = new $class();
        $reflection = new ReflectionMethod($controller, $method);

        // Inject Request and cast route parameters to declared scalar types.
        $args = [];
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($typeName === Request::class) {
                $args[] = $request;
                continue;
            }

            $value = $params[$parameter->getName()] ?? null;
            if ($value === null) {
                $args[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                continue;
            }

            if ($typeName === 'int') {
                if (!ctype_digit($value) || strlen($value) > 18) {
                    throw new HttpException(404);
                }
                $value = (int) $value;
            }
            $args[] = $value;
        }

        $result = $reflection->invokeArgs($controller, $args);

        return match (true) {
            $result instanceof Response => $result,
            is_array($result) => Response::json($result),
            default => new Response((string) $result),
        };
    }
}
