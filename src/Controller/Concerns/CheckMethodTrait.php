<?php
namespace Zodream\Route\Controller\Concerns;


trait CheckMethodTrait {

    protected function methods() {
        return [
            'index' => ['GET', 'HEAD'],
            'view' => ['GET', 'HEAD'],
            'create' => ['POST'],
            'update' => ['PUT', 'PATCH'],
            'delete' => ['DELETE'],
        ];
    }

    /**
     * 验证请求方式
     * @param $action
     * @return bool
     */
    public function checkMethod($action): bool {
        $methods = $this->methods();
        return !isset($methods[$action]) ||
            in_array(app('request')->method(), $methods[$action]);
    }

}