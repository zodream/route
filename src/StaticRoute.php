<?php
declare(strict_types=1);
namespace Zodream\Route;

use Exception;
use Zodream\Infrastructure\Contracts\Http\Input;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Contracts\Route as RouteInterface;
use Zodream\Infrastructure\Pipeline\MiddlewareProcessor;
use Zodream\Route\Controller\Module;
use Zodream\Route\Exception\ModuleException;

/**
 * 缓存用的静态路由
 * @package Zodream\Route
 */
class StaticRoute implements RouteInterface {

    protected ?array $regexMatched = null;

    /**
     * 注册静态路由
     * @param string $controller 完整的控制器类名
     * @param string $action 完整的方法名
     * @param array $regex 从当前网址匹配一些参数
     * @param array $parameters 反射方法获取的参数
     * @param array $middlewares 执行的中间件
     * @param array $module 模块 ['path' => 模块类名]
     * @param array $options
     */
    public function __construct(
        protected string $controller,
        protected string $action,
        protected array $regex = [],
        protected array $parameters = [],
        protected array $middlewares = [],
        protected array $module = [],
        protected array $options = []
    ) {

    }

    /**
     * 设置匹配过的数据，不用重复匹配
     * @param array $match
     * @return $this
     */
    public function matched(array $match): RouteInterface {
        $this->regexMatched = $match;
        return $this;
    }

    public function middleware(...$middlewares): RouteInterface {
        return $this;
    }

    public function method(array $methods): RouteInterface {
        return $this;
    }

    public function handle(HttpContext $context) {
        static::invokeModule($this->module, $context);
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
        return $this->call([$instance, $this->action], $context['request'], $parameters);
    }

    protected function call($callback, Input $input, array $parameters = []) {
        $items = [];
        foreach ($this->parameters as $item) {
            if (array_key_exists($item['name'], $parameters)) {
                $items[] = BoundMethod::formatValue($item['type'], $parameters[$item['name']]);
                continue;
            }
            if ($input->has($item['name'])) {
                $items[] = BoundMethod::formatValue($item['type'], $input->get($item['name']));
                continue;
            }
            if (isset($item['default'])) {
                $items[] = $item['default'];
                continue;
            }
            throw new Exception(sprintf('parameter [%s] do not have default value', $item['name']));
        }
        return call_user_func_array($callback, $items);
    }

    protected function getParameter(HttpContext $context) {
        if (is_array($this->regexMatched)) {
            return $this->regexMatched;
        }
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

    public static function invokeModule(array $moduleItems, HttpContext $context): void {
        if (empty($moduleItems)) {
            return;
        }
        foreach ($moduleItems as $path => $module) {
            $instance = ModuleRoute::moduleInstance($module, $context);
            if (!$instance instanceof Module) {
                throw new ModuleException(sprintf('[%s] is not Module::class', $module));
            }
            $context[Router::MODULE] = $instance;
            $context[ModuleRoute::MODULE_PATH] = $path;
            $instance->boot();
            $context[ModuleRoute::VIEW_PATH] = $instance->getViewPath();
        }
    }
}