<?php
declare(strict_types = 1);
namespace Zodream\Route;

use Exception;
use Zodream\Infrastructure\Contracts\Http\Input;
use Zodream\Infrastructure\Pipeline\MiddlewareProcessor;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Contracts\Route as RouteInterface;
use Zodream\Route\Controller\Module;
use Zodream\Route\Exception\ControllerException;
use Zodream\Route\Exception\ModuleException;
use Zodream\Template\ViewFactory;

class Route implements RouteInterface {

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
        $action,
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
        $action = isset($config['action']) ? $config['action'] : $config['definition'];
        $definition = $config['definition'];
        $constraints = isset($config['constraints']) ? $config['constraints'] : [];
        $defaults = isset($config['defaults']) ? $config['defaults'] : [];
        $methods = isset($config['methods']) ? $config['methods'] : true;
        return new static($definition, $action, $methods,  $constraints, $defaults);
    }

    public function method(array $methods): RouteInterface
    {
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
     * @param $path
     * @param int $basePath
     * @return bool
     */
    public function match(string $path, $basePath = null) {
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

    protected function prepareHandle(HttpContext $context) {
        if (isset($this->constraints['module_path'])) {
            $context['module_path'] = $this->constraints['module_path'];
        }
        if (isset($this->constraints['module'])) {
            $moduleCls = $this->constraints['module'];
            $module = new $moduleCls();
            $context['module'] = $module;
            $module->boot();
            $context['view_path'] = $module->getViewPath();
        }
    }

    public function handle(HttpContext $context) {
        $this->prepareHandle($context);
        if (isset($this->constraints[Router::MODULE])) {
            $this->invokeModule($this->constraints[Router::MODULE], $context);
        }
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
        $context['module'] = $instance;
        $instance->boot();
        $context['view_base'] = $instance->getViewPath();
    }

    protected function invokeRegisterAction($arg, HttpContext $context) {
        list($class, $action) = explode('@', $arg);
        if (!class_exists($class)) {
            throw new ControllerException(sprintf('[%s] is not found', $arg));
        }
        $instance = BoundMethod::newClass($class, $context);
        $context['controller'] = $instance;
        $context['action'] = $action;
        static::refreshDefaultView($context);
        return BoundMethod::call([$instance, $action], $context, $this->params());
    }



    /**
     * 刷新页面数据
     * @param HttpContext $context
     * @param bool $usePrefix 是否使用设置的后缀
     */
    public static function refreshDefaultView(HttpContext $context, bool $usePrefix = false) {
        /** @var ViewFactory $view */
        $view = $context['view'];
        if (isset($context['view_base'])) {
            $view->setDirectory($context['view_base']);
        }
        $context['view_controller_path'] = static::getViewFolder(isset($context['module'])
            ? $context['module'] : null, $context['controller'], $usePrefix);
        $view->setDefaultFile($context['view_controller_path'].$context['action']);
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
        if (!empty($prefix)) {
            $cls = preg_replace('/'. $prefix .'$/', '', $cls);
        }
        if (!$usePrefix || !empty($module)) {
            $pattern = '.*?Service.(.+)';
        } else {
            $pattern = '.*?Service.'.app('app.module').'.(.*)';
        }
        return preg_replace('/^'.$pattern.'$/', '$1', $cls).'/';
    }
}