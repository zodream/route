<?php
declare(strict_types=1);
namespace Zodream\Route;

use Zodream\Helpers\Str;
use Zodream\Http\Uri;
use Zodream\Infrastructure\Contracts\Http\Input;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Contracts\UrlGenerator as UrlGeneratorInterface;

class UrlGenerator implements UrlGeneratorInterface {

    /**
     * @var HttpContext
     */
    protected $container;
    /**
     * @var Input
     */
    protected $request;
    /**
     * @var string
     */
    protected $modulePath = '';

    protected $modulePrefix = '';
    /**
     * @var Uri
     */
    protected $uri;
    protected $useScript = false;

    public function __construct(HttpContext $context)
    {
        $this->container = $context;
        $this->setRequest($this->container->make('request'));
        $this->modulePrefix = Str::unStudly($context->make('app.module') ?: 'home');
        $this->setModulePath($context->make('module_path') ?: '');
    }

    /**
     * @param string $modulePath
     */
    public function setModulePath(string $modulePath): void
    {
        $this->modulePath = $modulePath;
    }

    public function setRequest(Input $request)
    {
        $this->request = $request;
        $this->uri = new Uri($request->url());
    }

    public function full(): string
    {
        return $this->request->url();
    }

    public function current(): string
    {
        $uri = clone $this->uri;
        return (string)$uri->setData([])->setFragment(null);
    }

    public function previous($fallback = false): string
    {
        $referrer = $this->request->referrer();
        if ($referrer) {
            return $referrer;
        }
        if ($fallback) {
            return (string)$this->to($fallback);
        }
        return (string)$this->to('/');
    }

    public function to($path, $extra = [], $secure = null): string
    {
        if ($path instanceof Uri && empty($extra) && !empty($path->getHost())) {
            return (string)$path;
        }
        $uri = $this->toRealUri($path, $extra, $secure);
        return $this->formatUrl($uri);
    }

    public function secure($path, $parameters = []): string
    {
        return '';
    }

    public function asset(string $path, $secure = null): string
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }
        return sprintf('%s://%s/%s', $this->formatScheme($secure),
            $this->uri->getHost(), trim($path, '/'));
    }

    public function route(string $name, $parameters = [], $absolute = true): string
    {
        return '';
    }

    public function action($action, $parameters = [], $absolute = true): string
    {
        return '';
    }

    public function formatScheme($secure = null): string
    {
        if (! is_null($secure)) {
            return $secure ? 'https' : 'http';
        }
        return $this->uri->getScheme();
    }

    /**判断是否带url段
     * @param string $search
     * @return bool
     */
    public function hasUri($search = null): bool {
        $url = $this->uri->getPath();
        if (is_null($search) && $url == '/') {
            return true;
        }
        return str_contains($url, '/' . trim($search, '/'));
    }

    /**
     * 判断是否是url
     * @param string $url
     * @return bool
     */
    public function isUrl(string $url): bool {
        return trim($this->uri->getPath(), '/') == trim($url, '/');
    }

    /**
     * 获取根网址
     *
     * @param boolean $withScript 是否带执行脚本文件
     * @return string
     */
    public function getRoot(bool $withScript = true): string {
        $root = $this->formatScheme(). '://'.$this->uri->getHost() . '/';
        $self = $this->request->script();
        if ($self !== '/index.php' && $withScript) {
            $root .= ltrim($self, '/');
        }
        return $root;
    }

    protected function formatUrl($url): string {
        return (string)$url;
    }

    protected function removeIndex(string $root): string {
        $i = 'index.php';
        return Str::contains($root, $i) ? str_replace('/'.$i, '', $root) : $root;
    }

    public function isValidUrl(string $path): bool {
        if (! Str::startsWith($path, ['#', '//', 'mailto:', 'tel:', 'http://', 'https://'])) {
            return filter_var($path, FILTER_VALIDATE_URL) !== false;
        }
        return true;
    }

    public function isSpecialUrl(string $path): bool  {
        return $path == '#' || strpos($path, 'javascript:') === 0;
    }

    public function useCustomScript($script = 'index.php') {
        $this->useScript = $script;
        return $this;
    }

    /**
     * 获取真实的uri 不经过重写的
     * @param null $path
     * @param null $extra
     * @param null $secure
     * @return string|Uri
     */
    public function toRealUri($path = null, $extra = null, $secure = null) {
        if (is_string($path) && ($this->isSpecialUrl($path) || $this->isValidUrl($path))) {
            return $path;
        }
        return $this->toUri($path, $extra, $secure);
    }

    /**
     * @param string|Uri $path
     * @param null $extra
     * @param null $secure
     * @return Uri
     */
    protected function toUri($path, $extra = null, $secure = null): Uri {
        if (!$path instanceof Uri) {
            $path = $this->createUri($path);
        }
        if (is_bool($extra)) {
            $secure = $extra;
            $extra = null;
        }
        if (!empty($extra)) {
            $path->addData($extra);
        }
        if (empty($path->getHost())) {
            $path->setScheme($this->formatScheme($secure))
                ->setHost($this->uri->getHost());
        }
        return $path;
    }

    /**
     * CREATE URI BY STRING OR ARRAY
     * @param array|string $file
     * @return Uri
     */
    public function createUri($file): Uri
    {
        $uri = new Uri();
        if (!is_array($file)) {
            return $uri->decode($this->addScript($this->getPath($file)));
        }
        $path = false;
        $data = [];
        foreach ($file as $key => $item) {
            if (is_integer($key)) {
                $path = $item;
                continue;
            }
            $data[$key] = (string)$item;
        }
        if ($path === false) {
            return (clone $this->uri)->addData($data);
        }
        return $uri->decode($this->addScript($this->getPath($path)))
            ->addData($data);
    }

    protected function getCurrentScript() {
        if ($this->useScript === false) {
            return $this->request->script();
        }
        if ($this->useScript === true || empty($this->useScript)) {
            return '/index.php';
        }
        $this->useScript = ltrim((string)$this->useScript, '/');
        if (empty($this->useScript)) {
            return '/index.php';
        }
        return '/'.$this->useScript;
    }

    protected function addScript($path) {
        if (strpos($path, '.') > 0
            || strpos($path, '/') === 0) {
            return $path;
        }
        $name = $this->getCurrentScript();
        if ($name === '/index.php') {
            return '/'.$path;
        }
        return $name.'/'.$path;
    }

    protected function getPath($path): string {
        if ($path === false) {
            return $this->current();
        }
        if (empty($path) || $path === '0') {
            return $this->request->url();
        }
        if ($path === -1 || $path === '-1') {
            return $this->previous();
        }
        if (!empty(parse_url($path, PHP_URL_HOST))) {
            return $path;
        }
        if (strpos($path, '//') !== false) {
            $path = preg_replace('#/+#', '/', $path);
        }
        if (strpos($path, './') === 0) {
            return $this->addModulePath(substr($path, 2));
        }
        return $path;
    }

    /**
     * 添加当前模块路径
     * @param $path
     * @return string
     */
    protected function addModulePath(string $path): string {
        if (empty($this->modulePath)) {
            return $path;
        }
        return $this->modulePath .'/'.$this->addModulePrefix($path);
    }

    protected function addModulePrefix(string $path) {
        if (empty($path) || substr($path, 0, 1) !== '@') {
            return $path;
        }
        if (empty($this->modulePrefix)) {
            return substr($path, 1);
        }
        $prefix = '@'.$this->modulePrefix;
        if ($prefix === $path) {
            return '';
        }
        if (strpos($path, $prefix.'/') === 0) {
            return substr($path, strlen($prefix) + 1);
        }
        return substr($path, 1);
    }
}