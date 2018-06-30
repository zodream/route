<?php
namespace Zodream\Route;

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
            $name = $method->getName();
            if (!empty(APP_ACTION) && !Str::endWith($name, APP_ACTION)) {
                continue;
            }
            $path = Str::lastReplace($name, APP_ACTION);
            $parameters = [];
            foreach ($method->getParameters() as $parameter) {
                $parameters[] = $this->parseParameter($parameter);
            }
            $action = [
                'class' => $controller,
                'method' => $name,
                'parameters' => $parameters
            ];
            if ($path == 'index') {
                $routes[$basePath] = $action;
                $routes[$basePath.'/'] = $action;
            }
            $path = Str::unStudly($path, ' ');
            $routes[$basePath.'/'.$path] = $action;
            if (strpos($path, ' ') === false) {
                continue;
            }
            $path = str_replace(' ', '_', $path);
            $routes[$basePath.'/'.$path] = $action;
            $path = str_replace('_', '-', $path);
            $routes[$basePath.'/'.$path] = $action;
        }
        return $routes;
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
            if ($name == APP_CONTROLLER) {
                return;
            }
            $class = $baseName.$name;
            $path = Str::lastReplace($name, APP_CONTROLLER);
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
        return $this->getRoute($root, $path, $module.'\\Service\\');
    }

    public function getDefaultRoute() {
        $root = Factory::root()->directory('Service/'.APP_MODULE);
        return $this->getRoute($root, '', 'Service\\'.APP_MODULE.'\\');
    }

    public function getAllRoute() {
        $routes = [];
        $modules = Config::modules();
        foreach ($modules as $key => $module) {
            $routes = array_merge($routes, $this->getModuleRoute($key, $module));
        }
        if (!in_array('default', $modules) && !empty(APP_MODULE)) {
            $routes = array_merge($routes, $this->getDefaultRoute());
        }
        return $routes;
    }

}