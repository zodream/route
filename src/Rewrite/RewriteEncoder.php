<?php
declare(strict_types=1);
namespace Zodream\Route\Rewrite;

use Zodream\Http\Uri;
use Zodream\Infrastructure\Contracts\Config\Repository;

class RewriteEncoder implements URLEncoder {

    protected string $rewriteExtension = '';
    public function __construct(Repository $config) {
        $this->rewriteExtension = (string)$config->get('route.rewrite', '');
    }



    public function encode(Uri $url, callable $next): Uri
    {
        $url = $next($url);
        list($path, $params) = $this->enRewrite($url->getPath(), $url->getData());
        return $url->setPath($path)->setData($params);
    }

    public function decode(Uri $url, callable $next): Uri
    {
        list($path, $params) = $this->deRewrite($url->getPath());
        return $next($url->setPath($path)->addData($params));
    }

    /**
     * 组成重写网址
     * @param $path
     * @param array $args
     * @return array
     */
    public function enRewrite($path, array $args) {
        list($path, $can) = $this->filterPath($path);
        if (!$can || empty($path) || empty($args) || empty($this->rewriteExtension)) {
            return [
                $path,
                $args
            ];
        }
        return $this->mergeUri($path, $args, $this->rewriteExtension);
    }

    protected function filterPath(string $path): array {
        $path = rtrim($path, '/');
        if (!empty($path) && preg_match('#\.\w*$#', $path, $_)) {
            return [$path, false];
        }
        return [$path, true];
    }

    protected function canRewrite($path, array $data): bool {
        if (empty($this->rewriteExtension)) {
            return false;
        }
        if (empty($path) || $path === '/') {
            return false;
        }
        if (str_ends_with($path, $this->rewriteExtension)) {
            return false;
        }
        if (str_contains($path, '.')) {
            return false;
        }
        if (!empty($args) && count($args) > 2) {
            return false;
        }
        foreach ($data as $key => $item) {
            if (is_string($key) && str_contains($key, '/')) {
                return false;
            }
            if (is_string($item) && str_contains($item, '/')) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $path
     * @param array $args
     * @param string $ext
     * @return array
     */
    private function mergeUri(string $path, array $args, string $ext = ''): array {
        if (empty($args)) {
            return [
                $path . $ext,
                $args
            ];
        }
        $spilt = '0';
        $data = [];
        $queries = [];
        $enable = true;
        foreach ($args as $key => $arg) {
            if (!$enable) {
                $queries[$key] = $arg;
                continue;
            }
            if (!is_string($key) ||
                str_contains($key, '[') ||
                (is_string($arg) && str_contains($arg, '/')) ||
                is_null($arg) || is_array($arg) || is_object($arg)
                || $arg === ''
            ) {
                $enable = false;
                $queries[$key] = $arg;
                continue;
            }
            $data[] = $key;
            $data[] = $arg;
        }
        if (empty($data)) {
            return [
                sprintf('%s%s', $path, $ext),
                $queries
            ];
        }
        if (!is_numeric($data[1])) {
            return [
                sprintf('%s/%s/%s%s', $path, $spilt, implode('/', $data), $ext),
                $queries
            ];
        }
        return [
            sprintf('%s/%s%s', $path, implode('/', $data), $ext),
            $queries
        ];
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
        if (!empty($this->rewriteExtension) && str_ends_with($path, $this->rewriteExtension)) {
            $path = substr($path, 0, strlen($path) - strlen($this->rewriteExtension));
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