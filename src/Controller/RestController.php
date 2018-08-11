<?php
namespace Zodream\Route\Controller;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/11/28
 * Time: 18:22
 */
use Zodream\Route\Controller\Concerns\RestTrait;
use Zodream\Infrastructure\Http\Request;

abstract class RestController extends BaseController  {

    use RestTrait;

    protected $canCSRFValidate = false;

    protected function rules() {
        return [
            'index' => ['GET', 'HEAD'],
            'view' => ['GET', 'HEAD'],
            'create' => ['POST'],
            'update' => ['PUT', 'PATCH'],
            'delete' => ['DELETE'],
        ];
    }

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
        if (!in_array(app('request')->method(), $rules[$action])) {
            return $this->renderFailure(
                __('ERROR REQUEST METHOD!')
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
        return true;
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
        return app('request')->header('Etag') == app('request')->header('If-Match');
    }

    protected function verifySign() {
        return true;
    }

}