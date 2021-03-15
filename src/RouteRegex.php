<?php
declare(strict_types=1);
namespace Zodream\Route;

class RouteRegex {
    const REGEX_PATTERN = [
        '*' => '(.*)',
        '?' => '([^\/]+)',
        'int' => '([0-9]+)',
        'multiInt' => '([0-9,]+)',
        'title' => '([a-z_-]+)',
        'key' => '([a-z0-9_]+)',
        'multiKey' => '([a-z0-9_,]+)',
        'isoCode2' => '([a-z]{2})',
        'isoCode3' => '([a-z]{3})',
    ];

    /**
     * 转化网址的匹配规则
     * @param string $definition 以 {} 为标识例如  path/{id}/{t}?
     * @return array [
     * 'parameters' => 匹配的值的名称 string[],   // 例如: ['id', 't'], 如果为空则表示不是正则匹配
     * 'patterns' => 匹配参数的正则,
     * 'regex' => 正则表达式 无开始结束符
     * ]
     */
    public static function parse(string $definition): array {
        if (empty($definition)) {
            return ['regex' => $definition];
        }
        $matchedParameter = [];
        $matchedPattern = [];
        $result = preg_replace_callback('/\/\{([a-z-0-9@]+)\}\??((:\(?[^\/]+\)?)?)/i', function ($match) use (&$matchedParameter, &$matchedPattern) {
            [$full, $parameter, $namedPattern] = $match;
            $pattern = '/' . static::REGEX_PATTERN['?'];
            if (! empty($namedPattern)) {
                $replace = substr($namedPattern, 1);

                if (isset(static::REGEX_PATTERN[$replace])) {
                    $pattern = '/' . static::REGEX_PATTERN[$replace];
                } elseif (substr($replace, 0, 1) == '(' && substr($replace, -1, 1) == ')') {
                    $pattern = '/' . $replace;
                }
            } elseif (isset(static::REGEX_PATTERN[$parameter])) {
                $pattern = '/' . static::REGEX_PATTERN[$parameter];
            }
            // Check whether parameter is optional.
            if (str_contains($full, '?')) {
                $pattern = str_replace(['/(', '|'], ['(/', '|/'], $pattern) . '?';
            }
            $matchedParameter[] = $parameter;
            $matchedPattern[] = $pattern;
            return $pattern;
        }, trim($definition));
        return ['parameters' => $matchedParameter, 'patterns' => $matchedPattern, 'regex' => $result];
    }

    /**
     * 匹配结果
     * @param string $path 非 / 开头的网址
     * @param array $regex 来源 RouteRegex::parse 的结果
     * @param bool $fullRegex 必须比配开头
     * @return array 如果为空则表示不匹配 ['path' => 匹配的路径, 'parameters' => 匹配后得到的值]
     */
    public static function match(string $path, array $regex, bool $fullRegex = true): array {
        if (!isset($regex['regex'])) {
            return [];
        }
        $parameters = [];
        if (!isset($regex['parameters']) || empty($regex['parameters'])) {
            return $path === $regex['regex'] ? compact('path', 'parameters') : [];
        }
        $pattern = $fullRegex ? ('~^' . $regex['regex'] . '$~i')
            : ('~' . $regex['regex'] . '$~i');
        if (!preg_match($pattern, $path, $match)) {
            return [];
        }
        $path = array_shift($match);
        foreach ($regex['parameters'] as $name) {
            $value = array_shift($match);
            $parameters[$name] = ltrim($value, '/');
            if (empty($match)) {
                break;
            }
        }
        return compact('path', 'parameters');
    }

    /**
     * 把模块路径加入到匹配规则中去
     * @param array $regex
     * @param array $module
     * @return array
     */
    public static function buildModule(array $regex, array $module): array {
        if (empty($module)) {
            return $regex;
        }
        $path = array_keys($module);
        $regex['regex'] = ltrim(implode('/', $path) . '/'.$regex['regex']);
        return $regex;
    }
}