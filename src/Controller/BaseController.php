<?php
namespace Zodream\Route\Controller;
/**
 * 控制器基类
 *
 * @author Jason
 * @time 2015-12-19
 */
use Exception;
use Zodream\Route\RouteException;
use Zodream\Service\Factory;
use Zodream\Infrastructure\Http\Response;
use ReflectionParameter;

abstract class BaseController extends Action {
	
	protected $action = 'index';

    protected $middlewares = [];

    /**
     * @param string $action
     * @return BaseController
     */
    public function setAction($action) {
        $this->action = $action;
        return $this;
    }
	
	protected function actions() {
		return [];
	}

    public function middleware(...$middlewares) {
        $this->middlewares = array_merge($this->middlewares, $middlewares);
        return $this;
    }


    /**
     * 方法未找到
     * @param $action
     * @return Response|mixed
     * @throws Exception
     */
	public function throwErrorMethod($action) {
        throw new RouteException(sprintf(
            __('%s::%s method error!'), get_called_class(), $action));
    }

    /**
     * 执行方法
     * @param string $action
     * @param array $vars
     * @return string|Response
     * @throws \Exception
     */
	public function invokeMethod($action, array $vars = array()) {
        Factory::timer()->record('controller start');
		$this->action = $action;
		if (!$this->hasMethod($action)) {
			return $this->throwErrorMethod($action);
		}
		if (true !==
            ($arg = $this->canInvoke($action))) {
			return $arg;
		}
		if (array_key_exists($action, $this->actions())) {
			return $this->runClassMethod($action);
		}
		$this->prepare();
		$result = $this->runActionMethod($this->getActionName($action), $vars);
		$this->finalize();
        Factory::timer()->record('controller end');
		return $result;
	}

    /**
     * 直接执行请保证正确
     * @param $action
     * @param array $vars
     * @return mixed
     */
	public function runMethodNotProcess($action, array $vars = []) {
        $this->action = $action;
        $result = call_user_func_array(
            [$this, $this->getActionName($action)],
            $vars
        );
        return $result;
    }

    /**
     * 获取
     * @param $action
     * @return string
     */
	protected function getActionName($action) {
	    return $action.config('app.action');
    }

    /**
     * @param $action
     * @return mixed|null|string|Response
     * @throws Exception
     */
    private function runClassMethod($action) {
		$class = $this->actions()[$action];
		if (is_callable($class)) {
			return call_user_func($class);
		}
		if (!class_exists($class)) {
			throw new Exception($action.
            __(' not found class!'));
		}
		$instance = new $class;
		if (!$instance instanceof Action) {
			throw new Exception($action.
            __(' is not instanceof Action!'));
		}
		$instance->init();
		$instance->prepare();
		$result = $instance->run();
		$instance->finalize();
		return $result;
	}

    /**
     * RUN THIS METHOD ACTION
     * @param string $action
     * @param array $vars
     * @return mixed
     * @throws Exception
     */
	protected function runActionMethod($action, $vars = array()) {
		return call_user_func_array(
		    array($this, $action),
            $this->getActionArguments($action, $vars)
        );
	}

    /**
     * GET ACTION NEED ARGUMENTS
     * @param string $action
     * @param array $vars
     * @return array
     * @throws Exception
     */
	protected function getActionArguments($action, $vars = array()) {
        $reflectionObject = new \ReflectionObject($this);
        $method = $reflectionObject->getMethod($action);
        $parameters = $method->getParameters();
        $arguments = array();
        foreach ($parameters as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $vars)) {
                $arguments[] = $this->parseParameter($vars[$name], $param);
                continue;
            }
            $value = $this->setActionArguments($name);

            if (!is_null($value)){
                $arguments[] = $this->parseParameter($value, $param);
                continue;
            }
            if ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
                continue;
            }
            throw new Exception(sprintf(
                __('%s ACTION`S %s DOES NOT HAVE VALUE!'), $action, $name));
        }
        return $arguments;
    }

    /**
     * 转化值
     * @param $value
     * @param ReflectionParameter $parameter
     * @return int
     */
    protected function parseParameter($value, ReflectionParameter $parameter) {
	    if (!$parameter->hasType()) {
	        return $value;
        }
        if ($parameter->getType() == 'int') {
	        return intval($value);
        }
        return $value;
    }

    /**
     * 设置方法的注入值来源
     * @param $name
     * @return array|string  返回null时取默认值
     */
    protected function setActionArguments(string $name) {
        return app('request')->get($name);
    }

    /**
     * 加载其他控制器的方法
     * @param static|string $controller
     * @param string $actionName
     * @param array $parameters
     * @return string|Response
     * @throws Exception
     */
	public function forward(
	    $controller,
        $actionName = 'index' ,
        $parameters = array()
    ) {
		if (is_string($controller)) {
			$controller = new $controller;
		}
		return $controller->invokeMethod($actionName, $parameters);
	}
	
	/**
	 * 判断是否存在方法
	 * @param string $action
	 * @return boolean
	 */
	public function hasMethod($action) {
		return array_key_exists($action, $this->actions())
        || method_exists($this, $action.config('app.action'));
	}



    /**
     * 验证方法是否合法
     * @param $action
     * @return boolean|Response
     */
    abstract public function canInvoke($action);

}