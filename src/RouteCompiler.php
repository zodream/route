<?php
declare(strict_types=1);
namespace Zodream\Route;

use Zodream\Disk\Directory;
use Zodream\Disk\File;
use Zodream\Disk\FileObject;
use ReflectionClass;
use Zodream\Helpers\Str;
use ReflectionMethod;
use ReflectionParameter;
use Zodream\Route\Attributes\Route as RouteAttribute;
use Zodream\Infrastructure\Contracts\Route as RouteInterface;

/**
 * 路由生成静态缓存
 * @package Zodream\Route
 */
class RouteCompiler {

    public function map(callable $cb) {
        $modules = config('route.modules', []);
        if (!in_array('default', $modules) && !empty(app('app.module'))) {
            $this->mapDefault($cb);
        }
        foreach ($modules as $key => $module) {
            $this->mapModule($cb, $key, $module);
        }
    }

    protected function mapDefault(callable $cb) {
        $moduleName = app('app.module') ?: 'Home';
        $root = app_path()->directory('Service/'.$moduleName);
        $this->mapFile($cb, $root, 'Service\\'.$moduleName.'\\');
    }

    protected function mapModule(callable $cb, string $path, string $module) {
        $class = $module.'\Module';
        if (!class_exists($class)) {
            return [];
        }
        $func = new ReflectionClass($class);
        $file = new File($func->getFileName());
        $root = $file->getDirectory()->directory('Service');
        $this->mapFile(function (array $action) use ($cb, $path, $module) {
            if ($path === 'default' || $path === '') {
                $action['module'] = ['' => $module];
                call_user_func($cb, $action);
            }
            if (isset($action['path'])) {
                $action['path'] = $this->joinPath($path, $action['path']);
            }
            $action['module'] = [$path => $module];
            call_user_func($cb, $action);
        }, $root, $module.'\\Service\\');
    }

    protected function mapFile(callable $cb, Directory $folder, string $base) {
        if (!$folder->exist()) {
            return;
        }
        if (!empty($base)) {
            $base = trim($base, '\\').'\\';
        }
        $folder->map(function (FileObject $file) use ($cb, $base) {
            if ($file instanceof Directory) {
                $path = Str::unStudly($file->getName(), ' ');
                $this->mapFile(function (array $action) use ($cb, $path) {
                    if (!isset($action['path'])) {
                        call_user_func($cb, $action);
                        return;
                    }
                    $this->mapStudlyName($cb, $action, $path, $action['path']);
                }, $file, $base.$file->getName());
                return;
            }
            $prefix = config('app.controller');
            $name = $file->getNameWithoutExtension();
            if ($name === $prefix) {
                return;
            }
            $path = Str::lastReplace($name, config('app.controller'));
            $path = Str::unStudly($path, ' ');
            $this->mapAction(function (array $action) use ($cb, $path) {
                if (!isset($action['path'])) {
                    call_user_func($cb, $action);
                    return;
                }
                if ($path === 'home') {
                    call_user_func($cb, $action);
                }
                $this->mapStudlyName($cb, $action, $path, $action['path']);
            }, $base.$name);
        });
    }

    protected function mapStudlyName(callable $cb, array $action, string $name, string $path) {
        $action['path'] = $this->joinPath($name, $path);
        call_user_func($cb, $action);
        if (!str_contains($name, ' ')) {
            return;
        }
        $action['path'] = $this->joinPath(str_replace(' ', '_', $name), $path);
        call_user_func($cb, $action);
        $action['path'] = $this->joinPath(str_replace(' ', '-', $name), $path);
        call_user_func($cb, $action);
    }

    protected function mapAction(callable $cb, string $cls) {
        if (!class_exists($cls)) {
            return [];
        }
        $func = new ReflectionClass($cls);
        $methods = $func->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $this->mapActionRule(function (array $action) use ($cb, $cls) {
                $action['controller'] = $cls;
                call_user_func($cb, $action);
            }, $method);
        }
    }

    protected function mapActionRule(callable $cb, ReflectionMethod $method) {
        $attributes = $method->getAttributes(RouteAttribute::class);
        if (empty($attributes)) {
            $this->mapMethodDoc($cb, $method);
            return;
        }
        $name = $method->getName();
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $parameters[] = $this->parseParameter($parameter);
        }
        $data = [
            'action' => $name,
            'parameters' => $parameters,
        ];
        foreach ($attributes as $attribute) {
            $action = $data;
            /** @var RouteAttribute $route */
            $route = $attribute->newInstance();
            $action['regex'] = RouteRegex::parse($route->path);
            $action['method'] = array_filter($route->method, function($item) {
                return in_array($item, Route::HTTP_METHODS);
            });
            $action['module'] = $route->module;
            $action['middlewares'] = $route->middleware;
            call_user_func($cb, $action);
        }
    }

    protected function mapMethodDoc(callable $cb, ReflectionMethod $method) {
        $name = $method->getName();
        $actionTag = config('app.action');
        if (!empty($actionTag) && !Str::endWith($name, $actionTag)) {
            return;
        }
        $path = Str::lastReplace($name, $actionTag);
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $parameters[] = $this->parseParameter($parameter);
        }
        $doc = $method->getDocComment();
        $data = [
            'action' => $name,
            'parameters' => $parameters
        ];
        if (empty($doc)) {
            $this->mapDefaultAction($cb, $data, $path);
            return;
        }
        if (preg_match('/@method\s+(.+)/i', $doc, $match)) {
            $method = array_map(function ($item) {
                return strtoupper(trim($item));
            }, explode(',', $match[1]));
            $data['method'] = array_filter($method, function($item) {
                return in_array($item, Route::HTTP_METHODS);
            });
        }
        if (!preg_match_all('/@(route|path)\s+(\S+)/i', $doc, $matches, PREG_SET_ORDER)) {
            $this->mapDefaultAction($cb, $data, $path);
            return;
        }
        unset($data['path']);
        foreach ($matches as $match) {
            $action = $data;
            $action['regex'] = RouteRegex::parse($match[2]);
            call_user_func($cb, $action);
        }
    }

    /**
     * 处理action 一些变化情况
     * @param callable $cb
     * @param array $action
     * @param string $path
     */
    protected function mapDefaultAction(callable $cb, array $action, string $path) {
        $action['path'] = $path;
        call_user_func($cb, $action);
        if ($path === 'index') {
            $action['path'] = '';
            call_user_func($cb, $action);
            return;
        }
        $path = Str::unStudly($path, ' ');
        if (!str_contains($path, ' ')) {
            return;
        }
        $action['path'] = str_replace(' ', '_', $path);
        call_user_func($cb, $action);
        $action['path'] = str_replace(' ', '-', $path);
        call_user_func($cb, $action);
    }

    protected function joinPath(string $basePath, string $path): string {
        if (empty($path)) {
            return $basePath;
        }
        return trim($basePath.'/'.$path, '/');
    }


    protected function parseParameter(ReflectionParameter $parameter): array {
        $item = [
            'name' => $parameter->getName(),
            'type' => $this->formatParameterType($parameter),
        ];
        if ($parameter->isDefaultValueAvailable()) {
            $item['default'] = $parameter->getDefaultValue();
        }
        return $item;
    }

    protected function formatParameterType(ReflectionParameter $parameter) {
        if ($parameter->hasType()) {
            return '';
        }
        $type = $parameter->getType();
        if (empty($type) || $type instanceof \ReflectionUnionType) {
            return '';
        }
        return $type->getName();
    }

    public function getAllRoute(): array {
        $routes = [
            0 => [], // 普通
            1 => [], // 正则匹配的
        ];
        $this->map(function (array $action) use (&$routes) {
//            $action = [
//                'method' => [],
//                'regex' => [],
//                'controller' => '',
//                'action' => '',
//                'parameters' => [],
//                'middlewares' => [],
//                'module' => [],
//            ];
            if (isset($action['path'])) {
                $action['regex'] = $action['path'];
            }
            $methods = !isset($action['method']) || empty($action['method']) ? ['any'] : $action['method'];
            unset($action['method'], $action['path']);
            $isMatch = is_array($action['regex']) && isset($action['regex']['parameters'])
                && !empty($action['regex']['parameters']);
            $pattern = !is_array($action['regex']) ? $action['regex'] : $action['regex']['regex'];
            foreach ($methods as $method) {
                $routes[$isMatch ? 1 : 0][$method][$pattern] = $action;
            }
        });
        return $routes;
    }

    public function match(string $method, string $path, array $routes): ?RouteInterface {
        if (isset($routes[0][$method][$path])) {
            return $this->toRoute($routes[0][$method][$path]);
        }
        if (!isset($routes[1][$method])) {
            return null;
        }
        foreach ($routes[1][$method] as $route) {
            $match = RouteRegex::match($path, $route['regex']);
            if (empty($match)) {
                continue;
            }
            return $this->toRoute($route);
        }
        return null;
    }

    public function toRoute(array $route) {
        return new StaticRoute(
            $route['controller'],
            $route['action'],
            $route['regex'],
            $route['parameters'],
            isset($route['middlewares']) ? $route['middlewares'] : [],
            isset($route['module']) ? $route['module'] : [],
        );
    }

}