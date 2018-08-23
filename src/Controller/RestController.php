<?php
namespace Zodream\Route\Controller;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/11/28
 * Time: 18:22
 */
use Zodream\Route\Controller\Concerns\CheckMethodTrait;
use Zodream\Route\Controller\Concerns\RestAuthTrait;
use Zodream\Route\Controller\Concerns\RestTrait;
use Zodream\Route\Controller\Concerns\RuleTrait;

abstract class RestController extends BaseController  {

    use RestTrait, CheckMethodTrait, RestAuthTrait, RuleTrait;

    protected $canCSRFValidate = false;

    public function canInvoke($action) {
        $rules = $this->rules();
        if (!array_key_exists($action, $rules)) {
            return true;
        }
        if (!$this->verifySign()) {
            return $this->renderFailure(
                __('ERROR SIGN')
            );
        }
        if (!$this->checkMethod($action)) {
            return $this->renderFailure(
                __('ERROR REQUEST METHOD!'), 405, 405
            );
        }
        if (!$this->verifyEtag()) {
            return $this->renderFailure(
                __('precondition failed')
                , 412, 412);
        }
        if (!$this->verifyDate()) {
            return $this->renderFailure(
                __('date is expired!')
            );
        }
        return $this->checkRules($action);
    }

    /**
     * 验证请求时间是过期
     * @return bool
     */
    protected function verifyDate() {
        $date = app('request')->header('Date');
        return strtotime($date) > time() - 120;
    }

    protected function verifyEtag() {
        return app('request')->header('Etag')
            == app('request')->header('If-Match');
    }

    protected function verifySign() {
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
        if ($role === '?') {
            return auth()->guest() ?: $this->renderFailure(__('Please Under Guest!'));
        }
        if ($role === '@') {
            return !auth()->guest() ?:
                $this->renderFailure(__('Please Login User!'), 401, 401);
        }
        if ($role === '!') {
            return $this->renderFailure(__('The page not found！'), 404, 404);
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