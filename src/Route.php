<?php
namespace Zodream\Route;

/**
 * 单个路由
 * @author Jason
 */
use Zodream\Helpers\Str;
use Zodream\Service\Config;
use Zodream\Route\Controller\Module;
use Zodream\Service\Factory;
use Zodream\Helpers\Arr;
use Zodream\Infrastructure\Http\Request;
use Zodream\Infrastructure\Http\Response;
use Zodream\Service\Routing\Url;
use Exception;

class Route {

    const PATTERN = '#{([\w_]+)}#i';

	protected $uri;

	protected $methods = [];

	protected $action = [];

    /**
     * VALIDATE VALUE
     * @var array
     */
    protected $rules = [];

	/**
	 * Route constructor.
	 * @param $methods
	 * @param $uri
	 * @param string|object $action
	 */
	public function __construct($methods, $uri, $action) {
		$this->uri = preg_replace(self::PATTERN, '(?$1:.*?)', $uri);
		$this->methods = (array) $methods;
		$this->action = $this->parseAction($action);
		if (!array_key_exists('param', $this->action)) {
			$this->action['param'] = [];
		}
		if (in_array('GET', $this->methods) && ! in_array('HEAD', $this->methods)) {
			$this->methods[] = 'HEAD';
		}
	}

	protected function parseAction($action) {
		if (is_string($action)) {
			$action = trim($action, '/\\');
		}
		// If no action is passed in right away, we assume the user will make use of
		// fluent routing. In that case, we set a default closure, to be executed
		// if the user never explicitly sets an action to handle the given uri.
		if (empty($action)) {
			return ['uses' => Config::route('default', 'home/index')];
		}


		// If the action is already a Closure instance, we will just set that instance
		// as the "uses" property, because there is nothing else we need to do when
		// it is available. Otherwise we will need to find it in the action list.
		if (is_callable($action)) {
			return ['uses' => $action];
		}

		if (!is_array($action)) {
			return ['uses' => $action];
		}

		// If no "uses" property has been set, we will dig through the array to find a
		// Closure instance within this list. We will set the first Closure we come
		// across into the "uses" property that will get fired off by this route.
		if (!isset($action['uses'])) {
			$action['uses'] = $this->findCallable($action);
		}
		return $action;
	}

	protected function findCallable(array $action) {
		return Arr::first($action, function ($key, $value) {
			return is_callable($value) && is_numeric($key);
		});
	}
	
	public function getMethods() {
		return $this->methods;
	}
	
	public function getUri() {
		return $this->uri;
	}

    /**
     * CAN RUN ROUTE
     * @param string $url
     * @return bool
     */
    public function canRun($url) {
        if (!preg_match('#'.$this->uri.'#', $url, $match)) {
            return false;
        }
        Request::get()->set($match);
        return true;
    }

    public function filter($key, $pattern) {
        $this->rules[$key] = $pattern;
        return $this;
    }

    /**
     * 执行路由
     * @return Response
     * @throws \Exception
     */
	public function run() {
		return $this->parseResponse($this->runAction());
	}

	protected function runFilter() {
//	    if (!DataFilter::validate(Request::get(), $this->rules)) {
//	        throw new \InvalidArgumentException('URL ERROR');
//        }
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    protected function runAction() {
	    $this->runFilter();
		$action = $this->action['uses'];
		// 排除一个的方法
		if (is_callable($action) && (!is_string($action) || strpos($action, '\\') > 0)) {
			return call_user_func($action);
		}
		if (strpos($action, '@') === false) {
			return $this->invokeAutoAction($action);
		}
		return $this->invokeRegisterAction($action);
	}


    /**
     * @param $response
     * @return Response
     * @throws \Exception
     */
	protected function parseResponse($response) {
		if ($response instanceof Response) {
			return $response;
		}
		if (empty($response) || is_bool($response)) {
            return Factory::response();
        }
		return new Response($response);
	}

    /**
     * 执行动态方法
     * @param $arg
     * @return mixed
     * @throws \Exception
     */
	protected function invokeRegisterAction($arg) {
		list($class, $action) = explode('@', $arg);
		if (!class_exists($class)) {
			return $this->invokeController('Service\\'.APP_MODULE.'\\'.$class, $action);
		}
		$reflectionClass = new \ReflectionClass( $class );
		$method = $reflectionClass->getMethod($action);

		$parameters = $method->getParameters();
		$arguments = array();
		foreach ($parameters as $param) {
			$arguments[] = Request::get($param->getName());
		}
		return call_user_func_array(array(new $class, $action), $arguments);
	}

    /**
     *
     * 执行自动解析的方法
     * @param $path
     * @return mixed
     * @throws \Exception
     */
    protected function invokeAutoAction($path) {
        $modules = Config::modules();
        foreach ($modules as $key => $module) {
            if (!$this->isMatch($path, $key)) {
                continue;
            }
            // 要记录当前模块所对应的路径
            Url::setModulePath($key);
            return $this->invokeModule(Str::firstReplace($path, $key), $module);
        }
        // 默认模块
        if (array_key_exists('default', $modules)) {
            return $this->invokeModule($path, $modules['default']);
        }
        list($class, $action) = $this->getClassAndAction($path, 'Service\\'.APP_MODULE);
        return $this->invokeClass($class, $action);
    }

    protected function isMatch($path, $module) {
        return strpos($path, $module) === 0;
    }

    /**
     * @param $module
     * @return string
     * @throws \Exception
     */
    protected function getRealModule($module) {
	    if (class_exists($module)) {
	        return $module;
        }
        $module = rtrim($module, '\\').'\Module';
	    if (class_exists($module)) {
	        return $module;
        }
        throw new \Exception($module.' Module NO EXIST!');
    }

    /**
     * 执行已注册模块
     * @param $path
     * @param $module
     * @return mixed
     * @throws \Exception
     */
    protected function invokeModule($path, $module) {
	    $module = $this->getRealModule($module);
        $module = new $module();
        if (!$module instanceof Module) {
            return $this->invokeClass($module, $path);
        }
        $module->boot();
        Factory::view()->setDirectory($module->getViewPath());
        // 允许模块内部进行自定义路由解析
        if (method_exists($module, 'invokeRoute')) {
            return $module->invokeRoute($path);
        }
        $baseName = $module->getControllerNamespace();
        list($class, $action) = $this->getClassAndAction($path, $baseName);
        return $this->invokeClass($class, $action);
    }

    /**
     * @param $class
     * @param $action
     * @return mixed
     * @throws \Exception
     */
    protected function invokeController($class, $action) {
        if (!Str::endWith($class, APP_CONTROLLER)) {
            $class .= APP_CONTROLLER;
        }
        if (!class_exists($class)) {
            throw new \InvalidArgumentException($class.' CLASS NOT EXISTS!');
        }
        return $this->invokeClass($class, $action);
    }

    /**
     * 执行控制器，进行初始化并执行方法
     * @param $instance
     * @param $action
     * @return mixed
     * @throws \Exception
     */
    protected function invokeClass($instance, $action) {
	    if (is_string($instance)) {
	        $instance = new $instance;
        }
        if (method_exists($instance, 'init')) {
	        $instance->init();
        }
        if (method_exists($instance, 'runMethod')) {
            return call_user_func(array($instance, 'runMethod'), $action, $this->action['param']);
        }
        throw new Exception('UNKNOWN CLASS');
    }

    protected function getClassAndAction($path, $baseName) {
        $baseName = rtrim($baseName, '\\').'\\';
        $path = trim($path, '/');
        if (empty($path)) {
            return [$baseName.'Home'.APP_CONTROLLER, 'index'];
        }
        $args = array_map(function ($arg) {
            return Str::studly($arg);
        }, explode('/', $path));
        return $this->getControllerAndAction($args, $baseName);
    }

    protected function getControllerAndAction(array $paths, $baseName) {
//        1.匹配全路径作为控制器 index 为方法,
        $class = $baseName.implode('\\', $paths). APP_CONTROLLER;
        if (class_exists($class)) {
            return [$class, 'index'];
        }
//        2.匹配最后一个作为 方法
        $count = count($paths);
        if ($count > 1) {
            $action = array_pop($paths);
            $class = $baseName.implode('\\', $paths). APP_CONTROLLER;
            if (class_exists($class)) {
                return [$class, lcfirst($action)];
            }
        }
//        3.匹配作为文件夹
        $class = $baseName.implode('\\', $paths).'\\HOME'. APP_CONTROLLER;
        if (class_exists($class)) {
            return [$class, 'index'];
        }
//        4.一个时匹配 home 控制器 作为方法
        if ($count == 1) {
            return [$baseName.'Home'.APP_CONTROLLER, lcfirst($paths[0])];
        }
        $action = array_pop($paths);
        $class = $baseName.implode('\\', $paths). '\\HOME'. APP_CONTROLLER;
        if (class_exists($class)) {
            return [$class, lcfirst($action)];
        }
        throw new Exception('UNKNOWN URI');
    }
}