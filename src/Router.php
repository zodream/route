<?php
declare(strict_types = 1);
namespace Zodream\Route;

use Closure;
use Exception;
use Zodream\Infrastructure\Pipeline\MiddlewareProcessor;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Contracts\Route as RouteInterface;
use Zodream\Infrastructure\Contracts\Router as RouterInterface;

class Router implements RouterInterface {

    const PREFIX = 'prefix';
    const PACKAGE = 'namespace';
    const MODULE = 'module';
    const MIDDLEWARE = 'middleware';
    const BEFORE = 'before';
    const AFTER = 'after';

    protected $middlewares = [];

    /**
     * @var array
     */
    protected $globalFilters = [];
    /**
     * @var
     */
    protected $globalRoutePrefix;

    protected $globalRoutePackage;
    protected $globalRouteMiddleware;
    /**
     * @var Route[]
     */
    protected $staticRouteMap = [];

    public function group(array $filters, $callback): RouterInterface {
        $oldGlobalFilters = $this->globalFilters;
        $oldGlobalPrefix = $this->globalRoutePrefix;
        $oldGlobalPackage = $this->globalRoutePackage;
        $this->globalFilters = array_merge_recursive($this->globalFilters,
            array_intersect_key($filters,
                [
                    self::MODULE => 1,
                    self::AFTER => 1,
                    self::BEFORE => 1
                ]));
        $newPrefix = isset($filters[self::PREFIX]) ? trim($filters[self::PREFIX], '/') : '';
        $newPackage = isset($filters[self::PACKAGE]) ? $filters[self::PACKAGE] : '';
        $this->globalRoutePrefix = $this->addPrefix($newPrefix);
        $this->globalRoutePackage = $this->addPackage($newPackage);
        $this->loadRoutes($callback);
        $this->globalFilters = $oldGlobalFilters;
        $this->globalRoutePrefix = $oldGlobalPrefix;
        $this->globalRoutePackage = $oldGlobalPackage;
        return $this;
    }

    protected function addPrefix(string $route): string {
        if (empty($this->globalRoutePrefix)) {
            return trim($route, '/');
        }
        return trim(trim($this->globalRoutePrefix, '/') . '/' . $route, '/');
    }

    protected function addPackage(string $package): string {
        if (empty($this->globalRoutePackage) || str_starts_with($package,
            '\\')) {
            return trim($package, '\\');
        }
        return trim(trim($this->globalRoutePackage, '\\') . '\\' . $package, '\\');
    }

    protected function loadRoutes($routes) {
        if ($routes instanceof Closure) {
            $routes($this);
        } else {
            $router = $this;
            require (string)$routes;
        }
    }

    /**
     * 手动注册路由
     * @param $method
     * @param $uri
     * @param $action
     * @return Route
     */
    public function addRoute(array $method, string $uri, $action): RouteInterface {
        if (is_string($action)) {
            $action = $this->addPackage($action);
        }
        $uri = $this->addPrefix($uri);
        $route = new Route($uri, $action, $method, $this->globalFilters);
        foreach ($route->getMethods() as $item) {
            $this->staticRouteMap[$item][$uri] = $route;
        }
        return $route;
    }

    public function get($uri, $action = null): RouteInterface {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    public function head($uri, $action = null): RouteInterface {
        return $this->addRoute(['HEAD'], $uri, $action);
    }

    /**
     * Register a new POST route with the router.
     *
     * @param  string  $uri
     * @param  \Closure|array|string|null  $action
     * @return Route
     */
    public function post($uri, $action = null): RouteInterface {
        return $this->addRoute(['POST'], $uri, $action);
    }

    /**
     * Register a new PUT route with the router.
     *
     * @param  string  $uri
     * @param  \Closure|array|string|null  $action
     * @return Route
     */
    public function put($uri, $action = null): RouteInterface {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    /**
     * Register a new PATCH route with the router.
     *
     * @param  string  $uri
     * @param  \Closure|array|string|null  $action
     * @return Route
     */
    public function patch($uri, $action = null): RouteInterface {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    /**
     * Register a new DELETE route with the router.
     *
     * @param  string  $uri
     * @param  \Closure|array|string|null  $action
     * @return Route
     */
    public function delete($uri, $action = null): RouteInterface {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    /**
     * Register a new OPTIONS route with the router.
     *
     * @param  string  $uri
     * @param  \Closure|array|string|null  $action
     * @return Route
     */
    public function options($uri, $action = null): RouteInterface {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    /**
     * Register a new route responding to all verbs.
     *
     * @param  string  $uri
     * @param  \Closure|array|string|null  $action
     * @return Route
     */
    public function any($uri, $action = null): RouteInterface {
        return $this->addRoute(Route::HTTP_METHODS, $uri, $action);
    }

    /**
     * Register a new route with the given verbs.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  \Closure|array|string|null  $action
     * @return Route
     */
    public function match($methods, $uri, $action = null): RouteInterface {
        return $this->addRoute(array_map('strtoupper', (array) $methods), $uri, $action);
    }



    public function middleware(...$middlewares): RouterInterface {
        $this->middlewares = array_merge($this->middlewares, $middlewares);
        return $this;
    }

    /**
     * @param string $method
     * @param string $uri
     * @return bool|Route
     * @throws Exception
     */
    public function getRoute(string $method, string $uri) {
        timer('match route');
        if (isset($this->staticRouteMap[$method][$uri])) {
            return $this->staticRouteMap[$method][$uri];
        }
        if (array_key_exists($method, $this->staticRouteMap)) {
            foreach ($this->staticRouteMap[$method] as $item) {
                /** @var $item Route */
                if ($item->match($uri)) {
                    timer('match route end');
                    return $item;
                }
            }
        }
        return false;
    }

    public function handle(HttpContext $context): RouteInterface {
        return (new MiddlewareProcessor($context))
            ->through($this->middlewares)
            ->send($context)
            ->then(function ($passable) use ($context) {
                if ($passable instanceof RouteInterface) {
                    return $passable;
                }
                return $context->make(ModuleRoute::class);
            });
    }

    public function cachePath(): string {
        return (string)app_path('data/cache_routes.php');
    }
}