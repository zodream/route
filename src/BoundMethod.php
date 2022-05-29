<?php
declare(strict_types=1);
namespace Zodream\Route;

use Exception;
use ReflectionException;
use ReflectionParameter;
use Zodream\Helpers\Str;
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
        $type = $parameter->getType();
        if (!($type instanceof \ReflectionUnionType)) {
            return static::formatValue($type->getName(), $value);

        }
        $types = [];
        $hasArr = false;
        foreach ($type->getTypes() as $item) {
            $name = $item->getName();
            if ($name === 'array') {
                $hasArr = true;
                continue;
            }
            $types[] = $name;
        }
        return static::formatUnionValue($hasArr, $types, $value);
    }

    public static function formatUnionValue(bool $hasArr, array $types, $value) {
        if ($hasArr && is_array($value)) {
            return array_map(function ($val)  use ($types) {
                return static::formatAnyType($types, $val);
            }, $value);
        }
        return static::formatAnyType($types, $value);
    }

    protected static function formatAnyType(array $types, $value) {
        foreach ($types as $type) {
            if ($type === 'float' && is_numeric($value)) {
                return floatval($value);
            }
            if ($type === 'int' && is_numeric($value)) {
                return intval($value);
            }
            if ($type === 'bool' && is_bool($value)) {
                return Str::toBool($value);
            }
            if ($type === 'null' && is_null($value)) {
                return null;
            }
        }
        return self::formatValue(end($types), $value);
    }

    public static function formatValue(string $type, $value) {
        return match ($type) {
            'int' => intval($value),
            'float' => floatval($value),
            'double' => doubleval($value),
            'bool' => Str::toBool($value),
            'array' => (array)$value,
            'null' => null,
            default => $value
        };
    }
}