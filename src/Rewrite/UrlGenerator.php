<?php
declare(strict_types=1);
namespace Zodream\Route\Rewrite;

use Zodream\Helpers\Str;
use Zodream\Http\Uri;
use Zodream\Route\UrlGenerator as Generator;

class UrlGenerator extends Generator {

    protected bool $rewritable = true;

    public function to($path, $extra = [], $secure = null): string
    {
        if (is_bool($extra)) {
            $secure = $extra;
            $extra = null;
        }
        $rewritable = !is_bool($secure) || $secure;
        if (!$rewritable) {
            $this->rewritable = false;
        }
        $url = parent::to($path, $extra, is_bool($secure) ? null : $secure);
        if (!$this->rewritable) {
            $this->rewritable = true;
        }
        return $url;
    }

    protected function formatUrl($url): string
    {
        if ($this->rewritable && $url instanceof Uri) {
            list($path, $data) = $this->enRewrite($url->getPath(), $url->getData());
            $url->setPath($path)->setData($data);
        }
        return (string)$url;
    }

    /**
     * 组成重写网址
     * @param $path
     * @param array $args
     * @return array
     */
    public function enRewrite($path, array $args) {
        if (empty($path)) {
            return ['', $args];
        }
        if (empty($path) || $path === '/') {
            return ['', $args];
        }
        $ext = config('route.rewrite');
        if (!empty($ext) && str_contains($path, $ext)) {
            return [
                $path,
                $args
            ];
        }
        if (empty($ext) || empty($args) || count($args) > 2) {
            $path = trim($path, '/');
            return [
                !Str::endWith($path, '.php') ? $path.$ext : $path,
                $args
            ];
        }
        return $this->mergeUri(trim($path, '/'), $args, $ext);
    }

    /**
     * @param $path
     * @param array $args
     * @param string $ext
     * @return array
     */
    private function mergeUri($path, array $args, $ext = ''): array {
        $spilt = '0';
        $data = [];
        foreach ($args as $key => $arg) {
            if (!is_numeric($arg) && empty($arg)) {
                return [
                    $path . $ext,
                    $args
                ];
            }
            if (is_numeric($key) || str_contains((string)$arg, '/')) {
                return [
                    $path . $ext,
                    $args
                ];
            }
            if ($spilt === '0' && is_numeric($arg) && count($args) < 2) {
                $spilt = sprintf('%s/%s', $key, $arg);
                continue;
            }
            $data[] = $key;
            $data[] = $arg;
        }
        if (!empty($data)) {
            $spilt = sprintf('%s/%s', $spilt, implode('/', $data));
        }
        return [
            sprintf('%s/%s%s', $path, $spilt, $ext),
            []
        ];
    }
}