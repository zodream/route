<?php
namespace Zodream\Route\Controller;
/**
 * 控制器基类
 *
 * @author Jason
 * @time 2015-12-19
 */
use Zodream\Html\VerifyCsrfToken;
use Zodream\Infrastructure\Http\Response;
use Zodream\Route\Controller\Concerns\JsonResponseTrait;
use Zodream\Route\Controller\Concerns\RuleTrait;
use Zodream\Service\Config;
use Zodream\Service\Factory;
use Zodream\Infrastructure\Loader;
use Zodream\Infrastructure\Traits\LoaderTrait;


abstract class Controller extends BaseController {
	
	use LoaderTrait, JsonResponseTrait, RuleTrait;

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
		if (!is_bool($this->canCSRFValidate)) {
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
    public function invokeMethod($action, array $vars = array()) {
        if ($this->canCSRFValidate
            && app('request')->isPost()
            && !VerifyCsrfToken::verify()) {
            throw new \HttpRequestException(
                __('BAD POST REQUEST!')
            );
        }
        if ($this->canCSRFValidate
            && app('request')->isGet()) {
            VerifyCsrfToken::create();
        }
        return parent::invokeMethod($action, $vars);
    }

    /**
     * 判断是否可以执行
     * @param string $action
     * @return bool
     * @throws \Exception
     */
    public function canInvoke($action) {
        return $this->checkRules($action);
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
            return app('request')->isCli() ?:
                $this->redirectWithMessage('/',
                    __('The page need cli！')
                    , 4,'400');
        }
        if ($role === '?') {
            return auth()->guest() ?: $this->redirect('/');
        }
        if ($role === '@') {
            return $this->checkUser() ?: $this->redirectWithAuth();
        }
        if ($role === 'p' || $role === 'post') {
            return app('request')->isPost() ?: $this->redirectWithMessage('/',
                __('The page need post！')
                , 4,'400');
        }
        if ($role === '!') {
            return $this->redirectWithMessage('/',
                __('The page not found！')
                , 4, '413');
        }
        return $this->processCustomRule($role);
    }

    /**
     * 自定义判断规则
     * @param $role
     * @return bool
     */
    protected function processCustomRule($role) {
        return true;
    }

    /**
     * @param string $action
     * @param array $vars
     * @return mixed|Response
     * @throws \Exception
     */
    protected function runActionMethod($action, $vars = array()) {
        $arguments = $this->getActionArguments($action, $vars);
        if ($this->canCache && app('request')->isGet() &&
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
        $update = app('request')->get('cache', false);
        if (!auth()->guest() && empty($update)) {
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
     * 验证用户
     * @return bool
     * @throws \Exception
     */
    protected function checkUser() {
        return !auth()->guest();
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
    public function show($name = null, $data = []) {
        Factory::timer()->record('view render');
        return $this->showContent($this->renderHtml($name, $data));
    }

    /**
     * 根据 pjax 自定义数据
     * @param null $name
     * @param null $data
     * @param null $layout_callback
     * @return Response
     * @throws \Exception
     */
    public function renderIfPjax($name = null, $data = null, $layout_callback = null) {
        if (is_array($name)) {
            list($data, $layout_callback, $name) = [$name, $data, null];
        }
        if (empty($data)) {
            $data = [];
        }
        if (!app('request')->isPjax() && is_callable($layout_callback)) {
            $data = array_merge($data, call_user_func($layout_callback));
        }
        return $this->show($name, $data);
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
     * @throws \Exception
     */
    protected function getViewFile($name = null) {
        if (is_null($name)) {
            $name = $this->action;
        }
        if (strpos($name, '/') !== 0) {
            $pattern = '.*?Service.'.app('app.module').'(.+)'.config('app.controller');
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
        return $this->redirect([Config::auth('home'), 'redirect_uri' => url()->full()]);
    }

    /**
     * 重定向
     * @param string $url
     * @param int $time
     * @return Response
     * @throws \Exception
     */
    public function redirect($url, $time = 0) {
        if (app('request')->wantsJson()) {
            return $this->json([
                'code' => 302,
                'status' => __('failure'),
                'errors' => '重定向',
                'url' => url()->to($url)
            ]);
        }
        return Factory::response()
            ->redirect(url()->to($url), $time);
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function goHome() {
        return $this->redirect(url()->getRoot());
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function goBack() {
        return $this->redirect(app('request')->referrer());
    }
}