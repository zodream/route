<?php
declare(strict_types=1);
namespace Zodream\Route\Controller;

use BadMethodCallException;
use Zodream\Infrastructure\Contracts\Http\Output;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Support\BoundMethod;
use Zodream\Route\Controller\Concerns\Json;
use Zodream\Route\Controller\Concerns\View;

abstract class Controller {

    use Json, View;

    /**
     * The middleware registered on the controller.
     *
     * @var array
     */
    protected array $middleware = [];

    public function httpContext(string $abstract = '') {
        return app_call(HttpContext::class, function (HttpContext $context) use ($abstract) {
            if (empty($abstract)) {
                return $context;
            }
            return $context->make($abstract);
        });
    }

    /**
     * Register middleware on the controller.
     *
     * @param \Closure|array|string $middleware
     * @param array $options
     * @return static
     */
    public function middleware($middleware, array $options = [])
    {
        foreach ((array) $middleware as $m) {
            $this->middleware[] = [
                'middleware' => $m,
                'options' => &$options,
            ];
        }
        return $this;
    }

    /**
     * Get the middleware assigned to the controller.
     *
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Execute an action on the controller.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return Output
     */
    public function callMethod(string $method, array $parameters = [])
    {
        return call_user_func_array([$this, $method], $parameters);
    }

    /**
     * 加载其他控制器的方法
     * @param $controller
     * @param string $actionName
     * @param array $parameters
     * @return mixed
     */
    public function forward(
        $controller,
        $actionName = 'index',
        $parameters = []
    ) {
        if (is_string($controller)) {
            $controller = new $controller;
        }
        return BoundMethod::call([$controller, $actionName.config('app.action')],
            $this->httpContext(), $parameters);;
    }

}