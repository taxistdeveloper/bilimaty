<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Router;
use Tests\TestCase;

final class RouterTest extends TestCase
{
    public function testParametersAndNamedRoutes(): void
    {
        $router = new Router();
        $router->group(['prefix' => 'api'], function (Router $r): void {
            $r->get('/journals/{id:\d+}', [self::class, 'none'], [], 'journal');
            $r->get('/verify/{code:[a-f0-9]{32}}', [self::class, 'none'], [], 'verify');
        });
        $this->assertSame('/api/journals/15', $router->path('journal', ['id' => 15]));
        $routes = $router->routes();
        $this->assertCount(2, $routes);
        $this->assertSame(1, preg_match($routes[1]['regex'], '/api/verify/' . str_repeat('a', 32)), 'Nested braces in a parameter regex');
        $this->assertSame(0, preg_match($routes[1]['regex'], '/api/verify/xyz'));
        $this->assertSame(0, preg_match($routes[0]['regex'], '/api/journals/abc'));
    }

    public function testUnknownPathAndWrongMethod(): void
    {
        $router = new Router();
        $router->post('/items', [self::class, 'none']);
        $e = $this->assertThrows(HttpException::class, fn () => $router->dispatch(new Request('GET', '/nothing', '', [], [], [], [])));
        $this->assertSame(404, $e->status);
        $e = $this->assertThrows(HttpException::class, fn () => $router->dispatch(new Request('GET', '/items', '', [], [], [], [])));
        $this->assertSame(405, $e->status);
    }

    public function testEveryRouteHasAKnownControllerAction(): void
    {
        foreach (Application::instance()->routes() as $route) {
            [$class, $method] = $route['action'];
            $this->assertTrue(method_exists($class, $method), "{$route['method']} {$route['uri']}: {$class}::{$method} does not exist");
        }
    }
}
