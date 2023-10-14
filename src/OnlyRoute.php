<?php
declare(strict_types=1);
namespace Zodream\Route;

use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Contracts\Route as RouteInterface;

/**
 * 指向一个固定的方法路由
 * @package Zodream\Route
 */
class OnlyRoute implements RouteInterface {

    /**
     * 注册静态路由
     * @param string $controller 完整的控制器类名
     * @param string $action 完整的方法名
     * @param array $parameters 反射方法获取的参数
     * @param array $module 模块 ['path' => 模块类名]
     */
    public function __construct(
        protected string $controller,
        protected string $action,
        protected array $parameters = [],
        protected array $module = [],
    ) {

    }

    public function middleware(...$middlewares): RouteInterface {
        return $this;
    }

    public function method(array $methods): RouteInterface {
        return $this;
    }

    public function handle(HttpContext $context) {
        StaticRoute::invokeModule($this->module, $context);
        return Route::invokeControllerAction($this->controller, $this->action, $context, $this->parameters);
    }
}