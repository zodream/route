<?php
declare(strict_types=1);
namespace Zodream\Route;

use Exception;
use ReflectionException;
use ReflectionParameter;
use Zodream\Infrastructure\Contracts\Container;
use Zodream\Infrastructure\Support\BoundMethod as BaseBound;

class BoundMethod extends BaseBound {

    protected static function getParameterFromSource(ReflectionParameter $dependency, Container $container, array &$parameters)
    {
        $name = $dependency->getName();
        if (array_key_exists($name, $parameters)) {
            $res = $parameters[$name];
            unset($parameters[$name]);
            return static::parseParameter($res, $dependency);
        }
        $className = static::getParameterClassName($dependency);
        if (!empty($className)) {
            return $container->make($className);
        }
        $request = request();
        if ($request->has($name)) {
            return static::parseParameter($request->get($name), $dependency);
        }
        if ($dependency->isDefaultValueAvailable()) {
            return $dependency->getDefaultValue();
        }
        if (!$dependency->isOptional() && empty($parameters)) {
            $message = "Unable to resolve dependency [{$dependency}] in class {$dependency->getDeclaringClass()->getName()}";
            throw new Exception($message);
        }
    }

    protected static function parseParameter($value, ReflectionParameter $parameter) {
        if (!$parameter->hasType()) {
            return $value;
        }
        $type = $parameter->getType()->getName();
        if ($type === 'int') {
            return intval($value);
        }
        if ($type === 'float') {
            return floatval($value);
        }
        if ($type === 'double') {
            return doubleval($value);
        }
        if ($type === 'bool') {
            return (is_numeric($value) && $value > 0) || (is_bool($value) && $value)
                || (is_string($value) && strtolower($value) === 'true') || !empty($value);
        }
        return $value;
    }
}