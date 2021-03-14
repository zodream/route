<?php
declare(strict_types=1);
namespace Zodream\Route;

use Exception;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Contracts\Route as RouteInterface;
use Zodream\Infrastructure\Pipeline\MiddlewareProcessor;
use Zodream\Route\Controller\Module;

/**
 * 静态路由
 * @package Zodream\Route
 */
class StaticRoute implements RouteInterface {

    public function __construct(
        protected string $controller,
        protected string $action,
        protected array $regex = [],
        protected array $parameters = [],
        protected array $middlewares = [],
        protected array $module = [],
        protected array $options = []
    )
    {

    }

    public function middleware(...$middlewares): RouteInterface
    {
        return $this;
    }

    public function method(array $methods): RouteInterface
    {
        return $this;
    }

    public function handle(HttpContext $context)
    {
        $this->invokeModule($context);
        return (new MiddlewareProcessor($context))
            ->through($this->middlewares)
            ->send($context)
            ->then(function (HttpContext $context) {
                return $this->invokeAction($context);
            });
    }

    protected function invokeAction(HttpContext $context) {
        $parameters = $this->getParameter($context);
        $context['request']->append($parameters);
        $instance = BoundMethod::newClass($this->controller, $context);
        $context['controller'] = $instance;
        $context['action'] = $this->action;
        Route::refreshDefaultView($context, true);
        return BoundMethod::call([$instance, $this->action], $context, $parameters);
    }

    protected function getParameter(HttpContext $context) {
        if (empty($this->regex)
            || !isset($this->regex['parameters'])
            || empty($this->regex['parameters'])) {
            return [];
        }
        $match = RouteRegex::match($context->path(), $this->regex, false);
        if (empty($match) || empty($match['parameters'])) {
            return [];
        }
        return $match['parameters'];
    }

    protected function invokeModule(HttpContext $context) {
        if (empty($this->module)) {
            return;
        }
        foreach ($this->module as $path => $module) {
            $instance = ModuleRoute::moduleInstance($module, $context);
            if (!$instance instanceof Module) {
                throw new Exception(sprintf('[%s] is not Module::class', $module));
            }
            $context['module'] = $instance;
            $context['module_path'] = $path;
            $instance->boot();
            $context['view_base'] = $instance->getViewPath();
        }
    }
}