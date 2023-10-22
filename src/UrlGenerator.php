<?php
declare(strict_types=1);
namespace Zodream\Route;

use Zodream\Helpers\Str;
use Zodream\Http\Uri;
use Zodream\Infrastructure\Contracts\Http\Input;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Contracts\UrlGenerator as UrlGeneratorInterface;
use Zodream\Infrastructure\Pipeline\MiddlewareProcessor;
use Zodream\Route\Rewrite\URLEncoder;

class UrlGenerator implements UrlGeneratorInterface {

    /**
     * @var HttpContext
     */
    protected HttpContext $container;
    /**
     * @var Input|null
     */
    protected ?Input $request = null;
    /**
     * @var string
     */
    protected string $modulePath = '';

    protected string $modulePrefix = '';

    protected string $routeQueryKey = '';
    /**
     * @var Uri
     */
    protected ?Uri $uri = null;
    protected bool|string $useScript = false;
    /**
     * @var URLEncoder[]
     */
    protected array $encoders = [];
    protected MiddlewareProcessor $processor;

    public function __construct(HttpContext $context) {
        $this->processor = new MiddlewareProcessor($context);
        $this->container = $context;
        $this->routeQueryKey = (string)config('route.route_key', '');
        $this->sync();
        $this->loadMiddleware();
    }

    public function sync() {
        $this->setRequest($this->container->make('request'));
        $this->modulePrefix = Str::unStudly($this->container->make('app.module') ?: 'home');
        $this->setModulePath($this->container->make('module_path') ?: '');
    }

    /**
     * @param string $modulePath
     */
    public function setModulePath(string $modulePath): void {
        $this->modulePath = $modulePath;
    }

    public function getModulePath(): string {
        return $this->modulePath;
    }

    public function setRequest(Input $request): void {
        $this->request = $request;
        $this->uri = new Uri($request->url());
    }

    public function full(): string {
        return $this->request->url();
    }

    public function current(): string {
        $uri = clone $this->uri;
        return (string)$uri->setData([])->setFragment(null);
    }

    public function previous(bool $fallback = false): string {
        $referrer = $this->request->referrer();
        if ($referrer) {
            return $referrer;
        }
        if ($fallback) {
            return $this->to($fallback);
        }
        return $this->to('/');
    }

    public function to(mixed $path, array $extra = [], ?bool $secure = null, bool $encode = true): string {
        if ($path instanceof Uri && empty($extra) && !empty($path->getHost())) {
            return $this->formatUrl($path);
        }
        $uri = $this->toRealUri($path, $extra, $secure);
        return $this->formatUrl($uri, $encode);
    }

    public function secure(mixed $path, array $parameters = []): string {
        return $this->to($path, $parameters, true);
    }

    public function asset(string $path, ?bool $secure = null): string {
        if ($this->isValidUrl($path)) {
            return $path;
        }
        return sprintf('%s://%s/%s', $this->formatScheme($secure),
            $this->uri->getHost(), trim($path, '/'));
    }

    public function route(string $name, array $parameters = [], bool $absolute = true): string {
        return $this->to($name, $parameters);
    }

    public function action(string|array $action, array $parameters = [], bool $absolute = true): string {
        /** @var ModuleRoute $route */
        $route = $this->container->make(ModuleRoute::class);
        $action = $route->formatAction($action);
        $path = $route->toPath($action['module'] ?? '',
            $action['controller'] ?? '', $action['action'] ?? '');
        return $this->to($path, $parameters);
    }

    public function decode(string $url = ''): Uri {
        $uri = clone $this->uri;
        if (!empty($url)) {
            $uri->setData([])
                ->setFragment('')->decode($url);
        } else {
            $uri->setPath('');
        }
        return $this->invokeMiddleware($uri, 'decode');
    }

    public function encode(Uri $url): Uri {
        return $this->invokeMiddleware($url, 'encode');
    }

    public function formatScheme(?bool $secure = null): string {
        if (! is_null($secure)) {
            return $secure ? 'https' : 'http';
        }
        return $this->uri->getScheme();
    }

    /**判断是否带url段
     * @param string|null $search
     * @return bool
     */
    public function hasUri(?string $search = null): bool {
        $url = $this->uri->getPath();
        if (is_null($search) && $url === '/') {
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

    protected function formatUrl(mixed $url, bool $encode = true): string {
        if (!$encode || !($url instanceof Uri)) {
            return (string)$url;
        }
        return (string)$this->encode($url);
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
        return str_starts_with($path, '#') || str_starts_with($path, 'javascript:');
    }

    public function useCustomScript(bool|string $script = 'index.php'): static {
        $this->useScript = $script;
        return $this;
    }

    /**
     * 获取真实的uri 不经过重写的
     * @param null $path
     * @param array|bool|null $extra
     * @param bool|null $secure
     * @return string|Uri
     */
    public function toRealUri(mixed $path = null, array|bool $extra = null, ?bool $secure = null) {
        if (is_string($path) && ($this->isSpecialUrl($path) || $this->isValidUrl($path))) {
            return $path;
        }
        return $this->toUri($path, $extra, $secure);
    }

    /**
     * @param string|Uri $path
     * @param array|bool|null $extra
     * @param bool|null $secure
     * @return Uri
     */
    protected function toUri(mixed $path, array|bool $extra = null, ?bool $secure = null): Uri {
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
    public function createUri(mixed $file): Uri {
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

    protected function addScript(string $path): string {
        if (strpos($path, '.') > 0
            || str_starts_with($path, '/')) {
            return $path;
        }
        $name = $this->getCurrentScript();
        if ($name === '/index.php') {
            return '/'.$path;
        }
        if (!empty($this->routeQueryKey)) {
            return sprintf('%s?%s=%s', $name, $this->routeQueryKey, $path);
        }
        return $name.'/'.$path;
    }

    protected function getPath(mixed $path): string {
        if ($path === false) {
            return $this->current();
        }
        if (empty($path)) {
            return $this->request->url();
        }
        if ($path === -1 || $path === '-1') {
            return $this->previous();
        }
        if (!empty(parse_url($path, PHP_URL_HOST))) {
            return $path;
        }
        if (str_contains($path, '//')) {
            $path = preg_replace('#/+#', '/', $path);
        }
        if (str_starts_with($path, './')) {
            return $this->addModulePath(substr($path, 2));
        }
        return $path;
    }

    /**
     * 添加当前模块路径
     * @param string $path
     * @return string
     */
    protected function addModulePath(string $path): string {
        if (empty($this->modulePath)) {
            return $path;
        }
        return $this->modulePath .'/'.$this->addModulePrefix($path);
    }

    protected function addModulePrefix(string $path): string {
        if (empty($path) || !str_starts_with($path, '@')) {
            return $path;
        }
        if (empty($this->modulePrefix)) {
            return substr($path, 1);
        }
        $prefix = '@'.$this->modulePrefix;
        if ($prefix === $path) {
            return '';
        }
        if (str_starts_with($path, $prefix . '/')) {
            return substr($path, strlen($prefix) + 1);
        }
        return substr($path, 1);
    }

    /**
     * 执行中间件
     * @param Uri $source
     * @param string $action
     * @return Uri
     */
    protected function invokeMiddleware(Uri $source, string $action) {
        return $this->processor->send($source)->via($action)->thenReturn();
    }

    private function loadMiddleware() {
        $items = [];
        $encoders = array_merge($this->encoders, (array)config('route.encoders'));
        foreach ($encoders as $item) {
            if (isset($items[$item]) || empty($item)) {
                continue;
            }
            $items[$item] = $this->container->make($item);
        }
        $this->processor->through(array_values($items));
    }
}