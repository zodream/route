<?php
declare(strict_types=1);
namespace Zodream\Route;

use Exception;
use Zodream\Helpers\Arr;
use Zodream\Helpers\Str;
use Zodream\Infrastructure\Contracts\Http\Output;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Contracts\Route as RouteInterface;
use Zodream\Infrastructure\Pipeline\MiddlewareProcessor;
use Zodream\Infrastructure\Support\RouteHelper;
use Zodream\Route\Controller\Controller;
use Zodream\Route\Controller\Module;
use Zodream\Route\Exception\ControllerException;
use Zodream\Route\Exception\ModuleException;
use Zodream\Route\Exception\NotFoundHttpException;

class ModuleRoute implements RouteInterface {

    const DEFAULT_ROUTE = 'default';

    /**
     * The HTTP methods the route responds to.
     *
     * @var array
     */
    public array $methods;

    /**
     * The route action array.
     *
     * @var string
     */
    public string $action;

    /**
     * @var Module
     */
    public mixed $module;
    public string $modulePath;

    /**
     * @var Controller
     */
    public mixed $controller;

    public function __construct()
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
        list($path, $this->modulePath, $this->module) = $this->tryMatchModule($this->formatRoutePath($context));
        $context['module_path'] = $this->modulePath;
        $moduleName = app('app.module') ?: 'Home';
        app()->instance('app.module', $moduleName);
        if (!empty($this->module)) {
            return $this->invokeModule($path, $this->module, $context);
        }
        return $this->invokePath($path, 'Service\\'.$moduleName, $context);
    }

    public function module(string $name, callable $handle = null, array $modules = []) {
        if (empty($modules)) {
            $modules = config('route.modules', []);
        }
        $newModule = false;
        foreach ($modules as $key => $module) {
            if ($name === $key || str_starts_with($name, $module)) {
                $newModule = [$key, $module];
                break;
            }
        }
        if (empty($newModule)) {
            return false;
        }
        if (!is_callable($handle)) {
            return $newModule;
        }
        $instance = url();
        $oldGlobalModule = $instance->getModulePath();
        $instance->setModulePath($newModule[0]);
        $res = call_user_func_array($handle, $newModule);
        $instance->setModulePath($oldGlobalModule);
        return $res;
    }

    public function invokePath(string $path, string $baseName, HttpContext $context) {
        list($class, $action) = $this->getClassAndAction($path, $baseName);
        if (!class_exists($class)) {
            throw new Exception($class.
                __(' class no exist!'));
        }
        return $this->invokeClass($class, $action, $context);
    }

    public function invokeModule(string $path, string $module, HttpContext $context) {
        $module = static::moduleInstance($module, $context);
        if (!$module instanceof Module) {
            return $this->invokeClass($module, $path, $context);
        }
        $context['module'] = $module;
        $this->module = $module;
        $module->boot();
        $context['view_base'] = $module->getViewPath();
        // 允许模块内部进行自定义路由解析
        if (method_exists($module, 'invokeRoute')) {
            $res = $module->invokeRoute(ltrim($path, '/'), $context);
            if ($res instanceof Output) {
                return $res;
            }
            if (is_string($res)) {
                $path = $res;
            }
        }
        $baseName = $module->getControllerNamespace();
        return $this->invokePath($path, $baseName, $context);
    }

    /**
     * @param HttpContext $context
     * @return string
     */
    protected function formatRoutePath(HttpContext $context): string
    {
        return $context->path();
    }

    /**
     * 获取模块
     * @param string $path
     * @return string[]
     */
    public function tryMatchModule(string $path): array {
        $modules = config('route.modules', []);
        foreach ($modules as $key => $module) {
            if (!RouteHelper::startWithRoute($path, $key)) {
                continue;
            }
            // 要记录当前模块所对应的路径
            return [trim(Str::firstReplace($path, $key), '/'), $key, $module];
        }
        // 默认模块
        if (array_key_exists(static::DEFAULT_ROUTE, $modules)) {
            return [$path, '', $modules[static::DEFAULT_ROUTE]];
        }
        return [$path, '', ''];
    }

    /**
     * @param string $class
     * @param string $action
     * @param HttpContext $context
     * @param array $vars
     * @return Output|mixed
     * @throws \ReflectionException
     */
    protected function invokeController(string $class, string $action, HttpContext $context, array $vars = []) {
        if (!Str::endWith($class, config('app.controller'))) {
            $class .= config('app.controller');
        }
        if (!class_exists($class)) {
            throw new ControllerException($class.
                __(' class no exist!'));
        }
        return $this->invokeClass($class, $action, $context, $vars);
    }


    /**
     * 执行控制器，进行初始化并执行方法
     * @param $instance
     * @param string $action
     * @param HttpContext $context
     * @param array $vars
     * @return Output|mixed
     * @throws \ReflectionException
     */
    protected function invokeClass(mixed $instance, string $action, HttpContext $context, array $vars = []) {
        timer('controller response');
        if (is_string($instance)) {
            $instance = BoundMethod::newClass($instance, $context, $vars);
        }
        $context['controller'] = $instance;
        $this->controller = $instance;
        $this->action = $action;
        $context['action'] = $action;
        Route::refreshDefaultView($context, true);
        if (method_exists($instance, 'init')) {
            $instance->init($context);
        }
        if (method_exists($instance, 'invokeMethod')) {
            return call_user_func(array($instance, 'invokeMethod'), $context, $action, $vars);
        }
        return $this->tryInvokeAction($instance, $action, $vars, $context);
    }

    public function tryInvokeAction(mixed $instance, string $action, array $vars, HttpContext $context) {
        $middleware = $this->getControllerMiddleware($instance, $action);
        return (new MiddlewareProcessor($context))
            ->through($middleware)
            ->send($context)
            ->then(function ($passable) use ($instance, $action, $vars, $context) {
                if ($passable instanceof Output) {
                    return $passable;
                }
                if (method_exists($instance, 'prepare')) {
                    $instance->prepare($context, $action);
                }
                if (!empty($vars)) {
                    $request = $context['request'];
                    $request->append($vars);
                }
                $method = $action.config('app.action');
                $res = BoundMethod::call([$instance, $method], $context, $vars);
                if (method_exists($instance, 'finalize')) {
                    $instance->finalize($context, $res);
                }
                return empty($res) ? response() : $res;
            });
    }

    protected function getControllerMiddleware(mixed $controller, string $method): array
    {
        if (! method_exists($controller, 'getMiddleware')) {
            return [];
        }
        $items = [];
        foreach ($controller->getMiddleware() as $item) {
            if (static::methodExcludedByOptions($method, $item['options'])) {
                continue;
            }
            $items[] = $item['middleware'];
        }
        return $items;
    }

    protected function getClassAndAction(string $path, string $baseName): array {
        $baseName = rtrim($baseName, '\\').'\\';
        $path = trim($path, '/');
        if (empty($path)) {
            return [$baseName.'Home'.config('app.controller'), 'index'];
        }
        $codeItems = [];
        foreach (explode('/', $path) as $code) {
            if (empty($code)) {
                continue;
            }
            $codeItems[] = Str::studly($code);
        }
        return $this->getControllerAndAction($codeItems, $baseName);
    }

    protected function getControllerAndAction(array $paths, string $baseName): array {
        $ctlTag = config('app.controller');
//        1.匹配全路径作为控制器 index 为方法,
        $class = $baseName.implode('\\', $paths). $ctlTag;
        if (class_exists($class)) {
            return [$class, 'index'];
        }
//        2.匹配最后一个作为 方法
        $count = count($paths);
        if ($count > 1) {
            $action = array_pop($paths);
            $class = $baseName.implode('\\', $paths). $ctlTag;
            if (class_exists($class)) {
                return [$class, lcfirst($action)];
            }
        }
//        3.匹配作为文件夹
        $class = $baseName.implode('\\', $paths).'\\Home'. $ctlTag;
        if (class_exists($class)) {
            return [$class, 'index'];
        }
//        4.一个是匹配 home 控制器 作为方法
        if ($count == 1) {
            return [$baseName.'Home'.$ctlTag, lcfirst($paths[0])];
        }
        $action = array_pop($paths);
        $class = $baseName.implode('\\', $paths). '\\Home'. $ctlTag;
        if (class_exists($class)) {
            return [$class, lcfirst($action)];
        }
        throw new NotFoundHttpException(
            sprintf(__('UNKNOWN URI: %s, Will Invoke: %s::%s'), request()->url(), $class, $action)
        );
    }

    public static function moduleInstance(string $module, HttpContext $context): mixed {
        if (class_exists($module)) {
            return BoundMethod::newClass($module, $context);
        }
        $module = rtrim($module, '\\').'\Module';
        if (class_exists($module)) {
            return BoundMethod::newClass($module, $context);
        }
        throw new ModuleException($module.
            __(' Module no exist!'));
    }

    protected static function methodExcludedByOptions($method, array $options): bool
    {
        return (isset($options['only']) && ! in_array($method, (array) $options['only'])) ||
            (! empty($options['except']) && in_array($method, (array) $options['except']));
    }

    public function getModulePath(string $module): string|bool {
        $maps = config('route.modules', []);
        foreach ($maps as $key => $mdl) {
            if ($this->isModule($module, $mdl)) {
                return $key;
            }
        }
        return false;
    }


    /**
     * 根据这些转化为网址路径
     * @param string $module
     * @param string $controller
     * @param string $action
     * @return string 例如 /aa
     */
    public function toPath(string $module, string $controller, string $action): string {
        $path = [''];
        if (!empty($module)) {
            $mdlPath = $this->getModulePath($module);
            if ($mdlPath === false) {
                // 模块未注册
                return '/';
            }
            if ($mdlPath !== static::DEFAULT_ROUTE) {
                $path[] = $mdlPath;
            }
        }
        $ctlTag = config('app.controller');
        $actTag = config('app.action');
        $ctlPath = empty($controller) ? '' : Str::endTrim($controller, $ctlTag);
        $i = strpos($ctlPath, 'Service\\');
        if ($i !== false) {
            $ctlPath = substr($ctlPath, $i + 8);
        }
        foreach (explode('\\', $ctlPath) as $item) {
            if (empty($item)) {
                continue;
            }
            $path[] = Str::unStudly($item);
        }
        $actPath = empty($action) ? '' : Str::endTrim($action, $actTag);
        $path[] = Str::unStudly($actPath);
        if (!empty($ctlPath)) {
            // 删除 home 控制器 index 方法
            $count = count($path);
            $last = $path[$count - 1];
            if ($last === '' || $last === 'index') {
                array_pop($path);
                $count --;
                if ($path[$count - 1] === 'home') {
                    array_pop($path);
                }
            }
        }
        return implode('/', $path);
    }

    protected function isModule(string $a, string $b): bool {
        $a = trim($a, '\\');
        $b = trim($b, '\\');
        if ($a === $b) {
            return true;
        }
        $min = min(strlen($a), strlen($b));
        if (!substr($a, 0, $min) === substr($b, 0, $min)) {
            return false;
        }
        $mdlTag = '\\Module';
        if (!str_ends_with($a, $mdlTag)) {
            $a .= $mdlTag;
        }
        if (!str_ends_with($b, $mdlTag)) {
            $b .= $mdlTag;
        }
        return $a === $b;
    }

    /**
     *
     * @param string|array $action
     * @return array{module: string,controller: string,action: string}
     */
    public function formatAction(string|array $action): array {
        if (empty($action)) {
            return [];
        }
        $ctlTag = config('app.controller');
        $actTag = config('app.action');
        if (is_array($action) && Arr::isAssoc($action)) {
            return $action;
        }
        if (!is_array($action)) {
            $action = explode('@', $action);
        }
        $count = count($action);
        if ($count > 2) {
            return ['module' => $action[0], 'controller' => $action[1], 'action' => $action[2]];
        }
        $module = '';
        $controller = '';
        $action = '';
        $last = $action[$count - 1];
        if (str_ends_with($last, $actTag)) {
            $action = $last;
            if ($count < 2) {
                return compact('action', 'module', 'controller');
            }
            if (!str_ends_with($action[0], $ctlTag)) {
                $module = $action[0];
                return compact('action', 'module', 'controller');
            }
            $controller = $action[0];
            $module = $this->formatFromController($controller);
            return compact('action', 'module', 'controller');
        }
        if ($count > 1) {
            $module = $action[0];
            $controller = $action[1];
            return compact('action', 'module', 'controller');
        }
        if (!str_ends_with($action[0], $ctlTag)) {
            $module = $action[0];
            return compact('action', 'module', 'controller');
        }
        $controller = $action[0];
        $module = $this->formatFromController($controller);
        return compact('action', 'module', 'controller');
    }

    protected function formatFromController(string $ctr): string {
        $i = strpos($ctr, 'Service');
        return $i > 0 ? substr($ctr, 0, $i) : '';
    }
}