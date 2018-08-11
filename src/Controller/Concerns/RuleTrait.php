<?php
namespace Zodream\Route\Controller\Concerns;


trait RuleTrait {

    /**
     * 此方法主要是为了继承并附加规则
     * @return array
     */
    protected function rules() {
        return [];
    }

    /**
     * 判断是否可以执行
     * @param string $action
     * @return bool
     */
    public function canInvoke($action) {
        $rules = $this->rules();
        foreach ($rules as $key => $item) {
            if ($action === $key) {
                return $this->processRole($item);
            }
            if (is_integer($key) && is_array($item)) {
                $key = (array)array_shift($item);
                if (in_array($action, $key)) {
                    return $this->processRole($item);
                }
            }
        }
        if (isset($rules['*'])) {
            return $this->processRole($rules['*']);
        }
        return true;
    }

    /**
     * @param string|callable $role
     * @return bool|Response
     * @throws \Exception
     */
    protected function processRole($role) {
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
}