<?php
namespace Zodream\Route\Controller;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/11/28
 * Time: 18:22
 */
use Zodream\Infrastructure\Http\Response;
use Zodream\Route\Controller\Concerns\CheckMethodTrait;
use Zodream\Route\Controller\Concerns\RestAuthTrait;
use Zodream\Route\Controller\Concerns\RestTrait;
use Zodream\Route\Controller\Concerns\RuleTrait;

abstract class RestController extends BaseController  {

    use RestTrait, CheckMethodTrait, RestAuthTrait, RuleTrait;

    protected $canCSRFValidate = false;

    /**
     * @param $action
     * @return bool|Response
     * @throws \Exception
     */
    public function canInvoke($action) {
        if (app('request')->isPreFlight()) {
            return $this->checkCorsMethod($action);
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
     * @throws \Exception
     */
    protected function verifyDate() {
        $date = app('request')->header('Date');
        return empty($date) || strtotime($date) > time() - 120;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function verifyEtag() {
        return app('request')->header('Etag')
            == app('request')->header('If-Match');
    }

    protected function verifySign() {
        return true;
    }

    protected function checkCorsMethod($action) {
        $methods = $this->methods();
        if (!isset($methods[$action])) {
            return $this->responseAllowCors();
        }
        $method = app('request')->header('Access-Control-Request-Method');
        if (in_array($method, $methods[$action])) {
            return $this->responseAllowCors();
        }
        return $this->responseDisallowCors();
    }

    protected function responseAllowCors() {
        return app('response')->allowCors();
    }

    protected function responseDisallowCors() {
        return $this->renderFailure('Disallow Method');
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
                $this->redirectWithAuth();
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