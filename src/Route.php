<?php
declare(strict_types = 1);
namespace Zodream\Route;

use Exception;
use Zodream\Helpers\Str;
use Zodream\Infrastructure\Contracts\Http\Input;
use Zodream\Infrastructure\Pipeline\MiddlewareProcessor;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Contracts\Route as RouteInterface;
use Zodream\Route\Controller\Module;
use Zodream\Route\Exception\ControllerException;
use Zodream\Route\Exception\ModuleException;
use Zodream\Template\ViewFactory;

class Route implements RouteInterface {

    const VIEW_CTL_PATH = 'view_controller_path';

    const HTTP_METHODS = ['OPTIONS', 'HEAD', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'TRACE', 'CONNECT'];
    /**
     * @var callable
     */
    protected $action;
    /**
     * @var string
     */
    protected string $definition;
    /**
     * @var array
     */
    protected array $constraints;
    /**
     * @var array
     */
    protected array $defaults;
    /**
     * @var array
     */
    protected array $methods;
    /**
     * @var array
     */
    protected array $regex;

    /**
     * @var array
     */
    protected array $params = [];

    protected array $middlewares = [];

    /**
     * Class constructor
     *
     * @param string $definition
     * @param callable|string $action
     * @param array $methods
     * @param array $constraints
     * @param array $defaults
     */
    public function __construct(
        string $definition,
        callable|string $action,
        array $methods = self::HTTP_METHODS,
        array $constraints = [],
        array $defaults = []) {
        $this->setAction($action);
        $this->definition = $definition;
        $this->constraints = $constraints;
        $this->defaults = $defaults;
        $this->method($methods);
        $this->regex = RouteRegex::parse($definition);
    }

    /**
     * @param array $config
     * @return Route
     * @throws Exception
     */
    public static function factory(array $config): Route {
        if (!isset($config['definition'])) {
            throw new Exception(
                __('Missing "definition" config option.')
            );
        }
        $action = $config['action'] ?? $config['definition'];
        $definition = $config['definition'];
        $constraints = $config['constraints'] ?? [];
        $defaults = $config['defaults'] ?? [];
        $methods = $config['methods'] ?? true;
        return new static($definition, $action, $methods,  $constraints, $defaults);
    }

    public function method(array $methods): RouteInterface {
        $this->methods = array_map('strtoupper', $methods);
        return $this;
    }

    public function middleware(...$middlewares): RouteInterface {
        $this->middlewares = array_merge($this->middlewares, $middlewares);
        return $this;
    }

    /**
     * Get methods
     *
     * @return array|bool
     */
    public function getMethods() {
        return $this->methods;
    }

    public function setAction($action): Route {
        $this->action = $action;
        return $this;
    }

    /**
     * 获取当前方法
     * @return callable
     */
    public function getAction(): callable {
        return $this->action;
    }
    /**
     * Get definition
     *
     * @return string
     */
    public function getDefinition(): string {
        return $this->definition;
    }
    /**
     * Get constraints
     *
     * @return array
     */
    public function getConstraints(): array {
        return $this->constraints;
    }
    /**
     * Get defaults
     *
     * @return array
     */
    public function getDefaults(): array {
        return $this->defaults;
    }
    /**
     * Get regex
     *
     * @return array
     */
    public function getRegex(): array {
        return $this->regex;
    }

    /**
     * Match
     *
     * @param string $path
     * @param string|null $basePath
     * @return bool
     */
    public function match(string $path, ?string $basePath = null) {
        if ($basePath !== null) {
            $length = strlen($basePath);
            if (substr($path, 0, $length) !== $basePath) {
                return false;
            }
            $path = substr($path, $length);
        }
        $match = RouteRegex::match($path, $this->regex);
        if (empty($match)) {
            return false;
        }
        $this->params = array_merge(
            $this->defaults, $match['parameters']
        );
        return true;
    }

    /**
     * Params
     *
     * @return array|null
     */
    public function params(): array {
        return $this->params;
    }
    /**
     * Allow
     *
     * @param Input $request
     * @return boolean
     */
    public function allow(Input $request): bool {
        return in_array($request->method(), $this->methods);
    }

    protected function prepareHandle(HttpContext $context): void {
        if (isset($this->constraints[ModuleRoute::MODULE_PATH])) {
            $context[ModuleRoute::MODULE_PATH] = $this->constraints[ModuleRoute::MODULE_PATH];
        }
        if (isset($this->constraints[Router::MODULE])) {
            $this->invokeModule($this->constraints[Router::MODULE], $context);
        }
    }

    public function handle(HttpContext $context) {
        $this->prepareHandle($context);
        return (new MiddlewareProcessor($context))
            ->through($this->middlewares)
            ->send($context)
            ->then(function (HttpContext $context) {
                $context['request']->append($this->params());
                if (is_callable($this->action)) {
                    return BoundMethod::call($this->action, $context, $this->params());
                }
                return $this->invokeRegisterAction($this->action, $context);
            });
    }

    public function invokeModule($module, HttpContext $context) {
        $instance = ModuleRoute::moduleInstance($module, $context);
        if (!$instance instanceof Module) {
            throw new ModuleException(sprintf('[%s] is not Module::class', $module));
        }
        $context[Router::MODULE] = $instance;
        $instance->boot();
        $context[ModuleRoute::VIEW_PATH] = $instance->getViewPath();
    }

    protected function invokeRegisterAction($arg, HttpContext $context) {
        list($class, $action) = explode('@', $arg);
        return static::invokeControllerAction($class, $action, $context, $this->params());
    }

    /**
     * 执行控制器及方法
     * @param string $controller
     * @param string $action
     * @param HttpContext $context
     * @param array $parameters
     * @return mixed
     * @throws \ReflectionException
     */
    public static function invokeControllerAction(string $controller,
                                                  string $action,
                                                  HttpContext $context, array $parameters = []): mixed {
        if (!class_exists($controller)) {
            throw new ControllerException(sprintf('[%s] is not found', $controller));
        }
        $instance = BoundMethod::newClass($controller, $context);
        $context['controller'] = $instance;
        $baseAction = Str::lastReplace($action, config('app.action'));
        $context['action'] = $baseAction;
        static::refreshDefaultView($context);
        if (method_exists($instance, 'init')) {
            $instance->init($context);
        }
        if (method_exists($instance, 'prepare')) {
            $instance->prepare($context, $baseAction);
        }
        $res = BoundMethod::call([$instance, $action], $context, $parameters);
        if (method_exists($instance, 'finalize')) {
            $instance->finalize($context, $res);
        }
        return $res;
    }


    /**
     * 刷新页面数据
     * @param HttpContext $context
     * @param bool $usePrefix 是否使用设置的后缀
     */
    public static function refreshDefaultView(HttpContext $context, bool $usePrefix = false): void {
        /** @var ViewFactory $view */
        $view = $context['view'];
        if (isset($context['view_base'])) {
            $view->setDirectory($context['view_base']);
        }
        $context[static::VIEW_CTL_PATH] = static::getViewFolder($context['module'] ?? null, $context['controller'], $usePrefix);
        $view->setDefaultFile($context[static::VIEW_CTL_PATH].$context['action']);
        url()->sync();
    }

    /**
     * 获取控制所在的ui路径
     * @param $module
     * @param $controller
     * @param bool $usePrefix
     * @return string
     * @throws Exception
     */
    protected static function getViewFolder($module, $controller, bool $usePrefix = false): string {
        $cls = get_class($controller);
        $prefix = config('app.controller');
        $end = strlen($cls);
        $begin = 0;
        if (!empty($prefix) && str_ends_with($cls, $prefix)) {
            $end = strlen($cls) - strlen($prefix);
        }
        if (!$usePrefix || !empty($module)) {
            $search = 'Service\\';
        } else {
            $search = 'Service\\'.app('app.module').'\\';
        }
        if (str_starts_with($cls, $search)) {
            $begin = strlen($search);
        } elseif (($i = strpos($cls, '\\'.$search)) !== false) {
            $begin = $i + strlen($search) + 1;
        }
        return substr($cls, $begin, $end - $begin).'\\';
    }
}