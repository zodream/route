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

/**
 * 路由生成静态缓存
 * @package Zodream\Route
 */
class RouteCompiler {

    public function getAction(string $basePath, string $controller): array {
        if (!class_exists($controller)) {
            return [];
        }
        $func = new ReflectionClass($controller);
        $methods = $func->getMethods(ReflectionMethod::IS_PUBLIC);
        $routes = [];
        foreach ($methods as $method) {
            $action = $this->parseMethod($method);
            if (empty($action)) {
                continue;
            }
            $action['controller'] = $controller;
            foreach ((array)$action['path'] as $path) {
                if (empty($path)) {
                    continue;
                }
                if ($path == 'index') {
                    $routes[$basePath] = $action;
                    $routes[$basePath.'/'] = $action;
                }
                if (substr($path, 0, 1) === '/') {
                    $routes[trim($path, '/')] = $action;
                    continue;
                }
                $path = Str::unStudly($path, ' ');
                $routes[$this->joinPath($basePath, $path)] = $action;
                if (!str_contains($path, ' ')) {
                    continue;
                }
                $path = str_replace(' ', '_', $path);
                $routes[$this->joinPath($basePath, $path)] = $action;
                $path = str_replace('_', '-', $path);
                $routes[$this->joinPath($basePath, $path)] = $action;
            }
        }
        return $routes;
    }

    protected function joinPath(string $basePath, string $path): string {
        return trim($basePath.'/'.$path, '/');
    }

    protected function parseMethod(ReflectionMethod $method): array {
        $name = $method->getName();
        $attributes = $method->getAttributes(RouteAttribute::class);
        if (empty($attributes)) {
            return $this->parseMethodDoc($method);
        }
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $parameters[] = $this->parseParameter($parameter);
        }
        $data = [
            'action' => $name,
            'parameters' => $parameters,
            'method' => [],
            'path' => [],
        ];
        foreach ($attributes as $attribute) {
            /** @var RouteAttribute $route */
            $route = $attribute->newInstance();
            $data['path'][] = $route->path;
            $data['method'] = array_filter($route->method, function($item) {
                return in_array($item, Route::HTTP_METHODS);
            });
        }
        return $data;
    }

    protected function parseMethodDoc(ReflectionMethod $method): array {
        $name = $method->getName();
        $actionTag = config('app.action');
        if (!empty($actionTag) && !Str::endWith($name, $actionTag)) {
            return [];
        }
        $path = Str::lastReplace($name, $actionTag);
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $parameters[] = $this->parseParameter($parameter);
        }
        $doc = $method->getDocComment();
        $data = [
            'action' => $name,
            'path' => $path,
            'parameters' => $parameters
        ];
        if (empty($doc)) {
            return $data;
        }
        if (preg_match('/@method\s+(.+)/i', $doc, $match)) {
            $method = array_map(function ($item) {
                return strtoupper(trim($item));
            }, explode(',', $match[1]));
            $data['method'] = array_filter($method, function($item) {
                return in_array($item, Route::HTTP_METHODS);
            });
        }
        if (preg_match_all('/@route\s+(\S+)/i', $doc, $matches, PREG_SET_ORDER)) {
            $data['routes'] = array_column($matches, 1);
        }
        if (preg_match_all('/@path\s+(\S+)/i', $doc, $matches, PREG_SET_ORDER)) {
            $data['path'] = array_column($matches, 1);
        }
        return $data;
    }

    protected function parseParameter(ReflectionParameter $parameter): array {
        $item = [
            'name' => $parameter->getName(),
            'type' => $parameter->getType() ? $parameter->getType()->getName() : '',
        ];
        if ($parameter->isDefaultValueAvailable()) {
            $item['default'] = $parameter->getDefaultValue();
        }
        return $item;
    }

    public function getRoute(Directory $root, string $basePath = '', string $baseName = ''): array {
        if (!$root->exist()) {
            return [];
        }
        if (!empty($basePath)) {
            $basePath = trim($basePath, '/').'/';
        }
        $files = [];
        $root->map(function (FileObject $file) use (&$files, $baseName, $basePath) {
            if (!$file instanceof File) {
                $path = $file->getName();
                $path = Str::unStudly($path, ' ');
                $args = $this->getRoute($file, $basePath.$path, $baseName.$file->getName());
                $files = array_merge($files, $args);
                if (!str_contains($path, ' ')) {
                    return;
                }
                $path = str_replace(' ', '_', $path);
                $args = $this->getRoute($file, $basePath.$path, $baseName.$file->getName());
                $files = array_merge($files, $args);
                $path = str_replace('_', '-', $path);
                $args = $this->getRoute($file, $basePath.$path, $baseName.$file->getName());
                $files = array_merge($files, $args);
                return;
            }
            $name = $file->getNameWithoutExtension();
            if ($name == config('app.controller')) {
                return;
            }
            $class = $baseName.$name;
            $path = Str::lastReplace($name, config('app.controller'));
            $path = Str::unStudly($path, ' ');
            if ($path == 'home') {
                $args = $this->getAction(trim($basePath, '/'), $class);
                $files = array_merge($files, $args);
            }
            $args = $this->getAction($basePath.$path, $class);
            $files = array_merge($files, $args);
            if (!str_contains($path, ' ')) {
                return;
            }
            $path = str_replace(' ', '_', $path);
            $args = $this->getAction($basePath.$path, $class);
            $files = array_merge($files, $args);
            $path = str_replace('_', '-', $path);
            $args = $this->getAction($basePath.$path, $class);
            $files = array_merge($files, $args);
        });
        return $files;
    }

    public function getModuleRoute(string $path, string $module): array {
        if ($path == 'default') {
            $path = '';
        }
        $class = $module.'\Module';
        if (!class_exists($class)) {
            return [];
        }
        $func = new ReflectionClass($class);
        $file = new File($func->getFileName());
        $root = $file->getDirectory()->directory('Service');
        return array_map(function($item) use ($module, $path) {
            $item['module'] = $module;
            $item['module_path'] = $path;
            return $item;
        }, $this->getRoute($root, $path, $module.'\\Service\\'));
    }

    public function getDefaultRoute(): array {
        $moduleName = app('app.module') ?: 'Home';
        $root = app_path()->directory('Service/'.$moduleName);
        return $this->getRoute($root, '', 'Service\\'.$moduleName.'\\');
    }

    public function getAllRoute(): array {
        $routes = [];
        $modules = config('route.modules');
        if (!in_array('default', $modules) && !empty(app('app.module'))) {
            $routes = array_merge($routes, $this->getDefaultRoute());
        }
        foreach ($modules as $key => $module) {
            $routes = array_merge($routes, $this->getModuleRoute($key, $module));
        }
        return $this->formatRoute($routes);
    }

    protected function formatRoute(array $routes): array {
        $data = [];
        foreach ($routes as $key => $route) {
            $uris = [$key];
            if (isset($route['route'])) {
                $uris[] = $route['route'];
            }
            $methods = !isset($route['method']) || empty($route['method']) ? ['any'] : $route['method'];
            unset($route['path'], $route['route']);
            foreach ($methods as $method) {
                foreach ($uris as $uri) {
                    $data[$method][$uri] = $route;
                }
            }
        }
        return $data;
    }

}