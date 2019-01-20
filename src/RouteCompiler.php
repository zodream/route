<?php
namespace Zodream\Route;

use function foo\func;
use Zodream\Disk\Directory;
use Zodream\Disk\File;
use Zodream\Disk\FileObject;
use ReflectionClass;
use Zodream\Helpers\Str;
use Zodream\Service\Config;
use Zodream\Service\Factory;
use ReflectionMethod;
use ReflectionParameter;

/**
 * 路由生成静态缓存
 * @package Zodream\Route
 */
class RouteCompiler {

    public function getAction($basePath, $controller) {
        if (!class_exists($controller)) {
            return [];
        }
        $func = new ReflectionClass($controller);
        $methods = $func->getMethods(ReflectionMethod::IS_PUBLIC);
        $routes = [];
        foreach ($methods as $method) {
            $action = $this->parseMethod($method);
            if ($action === false) {
                continue;
            }
            $action['controller'] = $controller;
            $path = $action['path'];
            if ($path == 'index') {
                $routes[$basePath] = $action;
                $routes[$basePath.'/'] = $action;
            }
            $path = Str::unStudly($path, ' ');
            $routes[$this->joinPath($basePath, $path)] = $action;
            if (strpos($path, ' ') === false) {
                continue;
            }
            $path = str_replace(' ', '_', $path);
            $routes[$this->joinPath($basePath, $path)] = $action;
            $path = str_replace('_', '-', $path);
            $routes[$this->joinPath($basePath, $path)] = $action;
        }
        return $routes;
    }

    protected function joinPath($basePath, $path) {
        return trim($basePath.'/'.$path, '/');
    }

    protected function parseMethod(ReflectionMethod $method) {
        $name = $method->getName();
        if (!empty(config('app.action')) && !Str::endWith($name, config('app.action'))) {
            return false;
        }
        $path = Str::lastReplace($name, config('app.action'));
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
        if (preg_match('/@method (.+)/i', $doc, $match)) {
            $method = array_map(function ($item) {
                return strtoupper(trim($item));
            }, explode(',', $match[1]));
            $data['method'] = array_filter($method, function($item) {
               return in_array($item, Route::HTTP_METHODS);
            });
        }
        if (preg_match('/@route (.+)/i', $doc, $match)) {
            $route = trim($match[1]);
            if (!empty($route)) {
                $data['route'] = $route;
            }
        }
        return $data;
    }

    protected function parseParameter(ReflectionParameter $parameter) {
        $item = [
            'name' => $parameter->getName(),
            'type' => $parameter->getType(),
        ];
        if ($parameter->isDefaultValueAvailable()) {
            $item['default'] = $parameter->getDefaultValue();
        }
        return $item;
    }

    public function getRoute(Directory $root, $basePath = '', $baseName = '') {
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
                if (strpos($path, ' ') === false) {
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
            if (strpos($path, ' ') === false) {
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

    public function getModuleRoute($path, $module) {
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

    public function getDefaultRoute() {
        $root = Factory::root()->directory('Service/'.app('app.module'));
        return $this->getRoute($root, '', 'Service\\'.app('app.module').'\\');
    }

    public function getAllRoute() {
        $routes = [];
        $modules = Config::modules();
        if (!in_array('default', $modules) && !empty(app('app.module'))) {
            $routes = array_merge($routes, $this->getDefaultRoute());
        }
        foreach ($modules as $key => $module) {
            $routes = array_merge($routes, $this->getModuleRoute($key, $module));
        }
        return $this->formatRoute($routes);
    }

    protected function formatRoute($routes) {
        $data = [];
        foreach ($routes as $key => $route) {
            $uris = [$key];
            if (isset($route['route'])) {
                $uris[] = $route['route'];
            }
            $methods = !isset($route['method']) || empty($route['method']) ? ['any'] : $route['method'];
            foreach ($methods as $method) {
                foreach ($uris as $uri) {
                    $data[$method][$uri] = $route;
                }
            }
        }
        return $data;
    }

}