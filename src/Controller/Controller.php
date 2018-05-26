<?php
namespace Zodream\Route\Controller;
/**
 * 控制器基类
 *
 * @author Jason
 * @time 2015-12-19
 */
use Zodream\Html\VerifyCsrfToken;
use Zodream\Infrastructure\Event\EventManger;
use Zodream\Infrastructure\Http\Request;
use Zodream\Infrastructure\Http\Response;
use Zodream\Infrastructure\Traits\JsonResponseTrait;
use Zodream\Service\Config;
use Zodream\Service\Factory;
use Zodream\Infrastructure\Loader;
use Zodream\Domain\Access\Auth;
use Zodream\Infrastructure\Traits\LoaderTrait;
use Zodream\Service\Routing\Url;

abstract class Controller extends BaseController {
	
	use LoaderTrait, JsonResponseTrait;

    protected $canCache;

    public $layout;

    /**
     * AUTO CSRF
     * @var bool
     */
    protected $canCSRFValidate;
	
	public function __construct($loader = null) {
		$this->loader = $loader instanceof Loader ? $loader : new Loader();
		if (is_bool($this->canCache)) {
			$this->canCache = Config::cache('auto', false);
		}
		if (is_bool($this->canCSRFValidate)) {
			$this->canCSRFValidate = Config::safe('csrf', false);
		}
	}

    /**
     * @param string $action
     * @param array $vars
     * @return string|Response
     * @throws \Exception
     * @throws \HttpRequestException
     */
    public function runMethod($action, array $vars = array()) {
        if ($this->canCSRFValidate
            && Request::isPost()
            && !VerifyCsrfToken::verify()) {
            throw new \HttpRequestException('BAD POST REQUEST!');
        }
        if ($this->canCSRFValidate
            && Request::isGet()) {
            VerifyCsrfToken::create();
        }
        return parent::runMethod($action, $vars);
    }

    /**
     * @param string $action
     * @param array $vars
     * @return mixed|Response
     * @throws \Exception
     */
    protected function runActionMethod($action, $vars = array()) {
        $arguments = $this->getActionArguments($action, $vars);
        if ($this->canCache && Request::isGet() &&
            (($cache = $this->runCache(get_called_class().
                    $this->action.serialize($arguments))) !== false)) {
            return $this->showContent($cache);
        }
        return call_user_func_array(array($this, $action), $arguments);
    }

    /**
     * 执行缓存
     * @param $key
     * @return bool|string
     * @throws \Exception
     */
    public function runCache($key = null) {
        if (DEBUG) {
            return false;
        }
        $update = Request::get('cache', false);
        if (!Auth::guest() && empty($update)) {
            return false;
        }
        if (empty($key)) {
            $key = get_called_class().$this->action;
        }
        if (!is_string($key)) {
            $key = serialize($key);
        }
        $key = 'views/'.md5($key);
        if (empty($update) && ($cache = Factory::cache()->get($key))) {
            return $cache;
        }
        $this->send('updateCache', true);
        Factory::event()->listen('showView', function ($content) use ($key) {
            Factory::cache()->set($key, $content, 12 * 3600);
        });
        return false;
    }

    /**
     * 在执行之前做规则验证
     * @param string $action 方法名
     * @return boolean|Response
     */
    protected function beforeFilter($action) {
        $rules = $this->rules();
        foreach ($rules as $key => $item) {
            if ($action === $key) {
                return $this->processFilter($item);
            }
            if (is_integer($key) && is_array($item)) {
                $key = (array)array_shift($item);
                if (in_array($action, $key)) {
                    return $this->processFilter($item);
                }
            }
        }
        if (isset($rules['*'])) {
            return $this->processFilter($rules['*']);
        }
        return true;
    }

    /**
     * @param string|callable $role
     * @return bool|Response
     * @throws \Exception
     */
    private function processFilter($role) {
        if (is_callable($role)) {
            return call_user_func($role);
        }
        if (empty($role)) {
            return true;
        }
        if (is_string($role)) {
            $role = explode(',', $role);
        }
        foreach ((array)$role as $item) {
            if (true !== ($arg =
                    $this->processRule($item))) {
                return $arg;
            }
        }
        return true;
    }

    /**
     * VALIDATE ONE FILTER
     * @param string $role
     * @return true|Response
     * @throws \Exception
     */
    protected function processRule($role) {
        if ($role === '*') {
            return true;
        }
        // 添加命令行过滤
        if ($role === 'cli') {
            return Request::isCli() ?: $this->redirectWithMessage('/', '您不能直接访问此页面！', 4,'400');
        }
        if ($role === '?') {
            return Auth::guest() ?: $this->redirect('/');
        }
        if ($role === '@') {
            return $this->checkUser() ?: $this->redirectWithAuth();
        }
        if ($role === 'p' || $role === 'post') {
            return Request::isPost() ?: $this->redirectWithMessage('/', '您不能直接访问此页面！', 4,'400');
        }
        if ($role === '!') {
            return $this->redirectWithMessage('/', '您访问的页面暂未开放！', 4, '413');
        }
        return true;
    }

    /**
     * 验证用户
     * @return bool
     */
    protected function checkUser() {
        return !Auth::guest();
    }


    public function getView() {
        return Factory::view();
    }

    /**
     * 传递数据
     *
     * @param string|array $key 要传的数组或关键字
     * @param string $value 要传的值
     * @return static
     * @throws \Exception
     */
    public function send($key, $value = null) {
        $this->getView()->set($key, $value);
        return $this;
    }

    /**
     * 加载视图
     *
     * @param string $name 视图的文件名
     * @param array $data 要传的数据
     * @return Response
     * @throws \Exception
     */
    public function show($name = null, $data = array()) {
        Factory::timer()->record('view render');
        return $this->showContent($this->renderHtml($name, $data));
    }

    /**
     * 生成页面
     * @param string $name
     * @param array $data
     * @return string
     * @throws \Exception
     */
    protected function renderHtml($name = null, $data = []) {
        if (is_array($name)) {
            $data = $name;
            $name = null;
        }
        return $this->renderFile($this->getViewFile($name), $data);
    }

    public function renderFile($file, $data) {
        return $this->getView()->render($file, $data);
    }


    public function findLayoutFile() {
        if (empty($this->layout)) {
            return false;
        }
        if (strpos($this->layout, '/') === 0) {
            return $this->layout;
        }
        return 'layouts/'.$this->layout;
    }

    /**
     * 获取视图文件路径
     * @param string $name
     * @return string
     */
    protected function getViewFile($name = null) {
        if (is_null($name)) {
            $name = $this->action;
        }
        if (strpos($name, '/') !== 0) {
            $pattern = '.*?Service.'.APP_MODULE.'(.+)'.APP_CONTROLLER;
            $name = preg_replace('/^'.$pattern.'$/', '$1', get_called_class()).'/'.$name;
        }
        return $name;
    }

    /**
     * 直接返回文本
     * @param string $html
     * @return Response
     * @throws \Exception
     */
    public function showContent($html) {
        $layoutFile = $this->findLayoutFile();
        if ($layoutFile !== false) {
            return $this->getView()->render($layoutFile, ['content' => $html]);
        }
        return Factory::response()->html($html);
    }


    /**
     * @param $url
     * @param $message
     * @param int $time
     * @param int $status
     * @return Response
     * @throws \Exception
     */
    public function redirectWithMessage($url, $message, $time = 4, $status = 404) {
        return $this->redirect($url, $time);
    }

    /**
     * 重定向到登录界面
     * @return Response
     * @throws \Exception
     */
    public function redirectWithAuth() {
        return $this->redirect([Config::auth('home'), 'redirect_uri' => Url::to()]);
    }

    /**
     * 重定向
     * @param string $url
     * @param int $time
     * @return Response
     * @throws \Exception
     */
    public function redirect($url, $time = 0) {
        return Factory::response()
            ->redirect(Url::to($url), $time);
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function goHome() {
        return $this->redirect(Url::getRoot());
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function goBack() {
        return $this->redirect(Url::referrer());
    }
}