<?php
declare(strict_types=1);
namespace Zodream\Route\Rewrite;

use Zodream\Helpers\Str;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Route\ModuleRoute as Route;

class ModuleRoute extends Route {

    protected function formatRoutePath(HttpContext $context): string
    {
        $path = $context->path();
        list($path, $data) = $this->deRewrite($path);
        $context['request']->append($data);
        return $path;
    }

    /**
     * 解析重写
     * @param $path
     * @return array
     */
    public function deRewrite($path) {
        if (empty($path)) {
            return ['', []];
        }
        $path = trim($path, '/');
        if (empty($path)) {
            return ['', []];
        }
        $ext = config('route.rewrite');
        if (!empty($ext)) {
            $path = Str::lastReplace($path, $ext);
        }
        if (empty($path)) {
            return ['', []];
        }
        list($path, $data) = $this->spiltArrayByNumber(explode('/', $path));
        return [
            implode('/', $path),
            $data
        ];
    }

    /**
     * 根据数字值分割数组
     * @param array $routes
     * @return array (routes, values)
     */
    private function spiltArrayByNumber(array $routes): array {
        $values = array();
        for ($i = 0, $len = count($routes); $i < $len; $i++) {
            if (!is_numeric($routes[$i])) {
                continue;
            }
            if ($i < $len - 1 && ($len - $i) % 2 === 1) {
                // 数字作为分割符,无意义
                $values = array_splice($routes, $i + 1);
                unset($routes[$i]);
            } else {
                $values = array_splice($routes, $i - 1);
            }
            break;
        }
        return array(
            $routes,
            $this->pairValues($values)
        );
    }

    /**
     * 将索引数组根据奇偶转关联数组
     * @param $values
     * @return array
     */
    private function pairValues($values): array {
        $args = array();
        for ($i = 0, $len = count($values); $i < $len; $i += 2) {
            if (isset($values[$i + 1])) {
                $args[$values[$i]] = $values[$i + 1];
            }
        }
        return $args;
    }
}