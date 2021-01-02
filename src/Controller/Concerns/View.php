<?php
declare(strict_types=1);
namespace Zodream\Route\Controller\Concerns;

use Zodream\Infrastructure\Contracts\Http\Output;
use Zodream\Infrastructure\Contracts\HttpContext;

trait View {

    public $layout = false;

    public function viewFactory() {
        return view();
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
        $this->viewFactory()->set($key, $value);
        return $this;
    }

    public function show($name = null, $data = []) {
        timer('view render');
        return $this->showContent($this->renderHtml($name, $data));
    }

    /**
     * 根据 pjax 自定义数据
     * @param null $name
     * @param null $data
     * @param null $layout_callback
     * @return Output
     * @throws \Exception
     */
    public function renderIfPjax($name = null, $data = null, $layout_callback = null) {
        if (is_array($name)) {
            list($data, $layout_callback, $name) = [$name, $data, null];
        }
        if (empty($data)) {
            $data = [];
        }
        if (!$this->httpContext('request')->isPjax() && is_callable($layout_callback)) {
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
        $html = $this->viewFactory()->setLayout($this->findLayoutFile())
            ->setAttribute('controller', $this)
            ->render($file, $data);
        return $html;
    }

    protected function findLayoutFile() {
        return $this->layout;
    }

    /**
     * 获取视图文件路径
     * @param string $name
     * @return string
     * @throws \Exception
     */
    protected function getViewFile($name = null) {
        $context = $this->httpContext();
        if (empty($name)) {
            $name = $context['action'];
        }
        $first = substr($name, 0, 1);
        if ($first !== '@' && $first !== '/') {
            $name = $context['view_controller_path'].$name;
        }
        return $name;
    }

    /**
     * 直接返回文本
     * @param string $html
     * @return Output
     * @throws \Exception
     */
    public function showContent($html) {
        return $this->httpContext('response')->html($html);
    }


    /**
     * @param $url
     * @param $message
     * @param int $time
     * @param int $status
     * @return Output
     * @throws \Exception
     */
    public function redirectWithMessage($url, $message, $time = 4, $status = 404) {
        return $this->redirect($url, $time);
    }

    /**
     * 重定向到登录界面
     * @return Output
     * @throws \Exception
     */
    public function redirectWithAuth() {
        return $this->redirect([config('auth.home'), 'redirect_uri' => $this->httpContext('request')->url()]);
    }

    /**
     * 重定向
     * @param string|array $url
     * @param int $time
     * @return Output
     * @throws \Exception
     */
    public function redirect($url, $time = 0) {
        if ($this->httpContext('request')->wantsJson()) {
            return $this->render([
                'code' => 302,
                'status' => __('failure'),
                'message' => '重定向',
                'url' => url()->to($url)
            ]);
        }
        return $this->httpContext('response')
            ->redirect(url()->to($url), $time);
    }

    /**
     * @return Output
     * @throws \Exception
     */
    public function goHome() {
        return $this->redirect(url()->getRoot());
    }

    /**
     * @return Output
     * @throws \Exception
     */
    public function goBack() {
        return $this->redirect($this->httpContext('request')->referrer());
    }
}