<?php
namespace Zodream\Route;

/**
 * 单个路由
 * @author Jason
 */
use Zodream\Domain\Filter\DataFilter;
use Zodream\Helpers\Str;
use Zodream\Service\Config;
use Zodream\Route\Controller\Module;
use Zodream\Service\Factory;
use Zodream\Helpers\Arr;
use Zodream\Infrastructure\Http\Request;
use Zodream\Infrastructure\Http\Response;
use Zodream\Service\Routing\Url;

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
	    if (!DataFilter::validate(Request::get(), $this->rules)) {
	        throw new \InvalidArgumentException('URL ERROR');
        }
    }
	
	protected function runAction() {
	    $this->runFilter();
		$action = $this->action['uses'];
		// 排除一个的方法
		if (is_callable($action) && (!is_string($action) || strpos($action, '\\') > 0)) {
			return call_user_func($action);
		}
		if (strpos($action, '@') === false) {
			return $this->runDefault($action);
		}
		return $this->runClassAndAction($action);
	}

	protected function runClassWithConstruct($action) {
		if (class_exists($action)) {
			return new $action;
		}
		return $this->runDefault($action);
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
	protected function runClassAndAction($arg) {
		list($class, $action) = explode('@', $arg);
		if (!class_exists($class)) {
			return $this->runController('Service\\'.APP_MODULE.'\\'.$class, $action);
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
     * @param $path
     * @return mixed
     * @throws \Exception
     */
    protected function runDefault($path) {
        $modules = Config::modules();
        foreach ($modules as $key => $module) {
            if (!$this->isMatch($path, $key)) {
                continue;
            }
            // 要记录当前模块所对应的路径
            Url::setModulePath($key);
            return $this->runModule(Str::firstReplace($path, $key), $module);
        }
        // 默认模块
        if (array_key_exists('default', $modules)) {
            return $this->runModule($path, $modules['default']);
        }
        list($class, $action) = $this->getClassAndAction($path);
        return $this->runController('Service\\'.APP_MODULE.'\\'.$class, $action);
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
     * @param $path
     * @param $module
     * @return mixed
     * @throws \Exception
     */
    protected function runModule($path, $module) {
	    $module = $this->getRealModule($module);
        $module = new $module();
        if (!$module instanceof Module) {
            return $this->runClass($module, $path);
        }
        list($class, $action) = $this->getClassAndAction($path);
        Factory::view()->setDirectory($module->getViewPath());
        $class = $module->getControllerNamespace().'\\'.$class;
        return $this->runController($class, $action);
    }

    /**
     * @param $class
     * @param $action
     * @return mixed
     * @throws \Exception
     */
    protected function runController($class, $action) {
	    $class .= APP_CONTROLLER;
        if (!class_exists($class)) {
            throw new \InvalidArgumentException($class.' CLASS NOT EXISTS!');
        }
        return $this->runClass($class, $action);
    }

    /**
     * @param $instance
     * @param $action
     * @return mixed
     * @throws \Exception
     */
    protected function runClass($instance, $action) {
	    if (is_string($instance)) {
	        $instance = new $instance;
        }
        if (method_exists($instance, 'init')) {
	        $instance->init();
        }
        if (method_exists($instance, 'runMethod')) {
            return call_user_func(array($instance, 'runMethod'), $action, $this->action['param']);
        }
        throw new \Exception('UNKNOWN CLASS');
    }

    protected function getClassAndAction($path) {
        $path = trim($path, '/');
        if (empty($path)) {
            return ['Home', 'index'];
        }
        $args = array_map(function ($arg) {
            return Str::studly($arg);
        }, explode('/', $path));
        if (count($args) == 1) {
            return [ucfirst($path), 'index'];
        }
        $action = array_pop($args);
        return [implode('\\', $args), lcfirst($action)];
    }
}