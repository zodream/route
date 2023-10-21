<?php
declare(strict_types=1);
namespace Zodream\Route\Controller\Concerns;

use Zodream\Disk\File;
use Zodream\Infrastructure\Contracts\Http\Output;
use Zodream\Route\Route;

trait View {

    protected File|string $layout = '';

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
    public function send(string|array $key, mixed $value = null) {
        $this->viewFactory()->set($key, $value);
        return $this;
    }

    public function show(string|array $name = '', array $data = []) {
        timer('view render');
        return $this->showContent($this->renderHtml($name, $data));
    }

    /**
     * 根据 pjax 自定义数据
     * @param string|array $name
     * @param array $data
     * @param callable|null $layoutCallback
     * @return Output
     */
    public function renderIfPjax(string|array $name = '', array $data = [], ?callable $layoutCallback = null) {
        if (is_array($name)) {
            list($data, $layoutCallback, $name) = [$name, $data, null];
        }
        if (empty($data)) {
            $data = [];
        }
        if (!$this->httpContext('request')->isPjax() && is_callable($layoutCallback)) {
            $data = array_merge($data, call_user_func($layoutCallback));
        }
        return $this->show($name, $data);
    }

    /**
     * 生成页面
     * @param array|string $name
     * @param array $data
     * @return string
     */
    protected function renderHtml(array|string $name = '', array $data = []): string {
        if (is_array($name)) {
            $data = $name;
            $name = '';
        }
        return $this->renderFile($this->getViewFile($name), $data);
    }

    public function renderFile(string|File $file, array $data) {
        return $this->viewFactory()->setLayout($this->findLayoutFile())
            ->setAttribute('controller', $this)
            ->render($file, $data);
    }

    protected function findLayoutFile(): string|File {
        return $this->layout;
    }

    /**
     * 获取视图文件路径
     * @param string $name
     * @return string
     */
    protected function getViewFile(string $name = ''): string {
        $context = $this->httpContext();
        if (empty($name)) {
            $name = $context['action'];
        }
        $first = substr($name, 0, 1);
        if ($first !== '@' && $first !== '/') {
            $name = $context[Route::VIEW_CTL_PATH].$name;
        }
        return $name;
    }

    /**
     * 直接返回文本
     * @param string $html
     * @return Output
     * @throws \Exception
     */
    public function showContent(string $html) {
        return $this->httpContext('response')->html($html);
    }


    /**
     * @param $url
     * @param string $message
     * @param int $time
     * @param int $status
     * @return Output
     * @throws \Exception
     */
    public function redirectWithMessage(mixed $url, string $message, int $time = 4, int $status = 404) {
        return $this->redirect($url);
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
    public function redirect(mixed $url, int $time = 0) {
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