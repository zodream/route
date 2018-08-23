<?php
namespace Zodream\Route\Controller\Concerns;


use Zodream\Infrastructure\Http\Response;

trait RuleTrait {

    /**
     * 此方法主要是为了继承并附加规则
     * @return array
     */
    protected function rules() {
        return [];
    }



    protected function checkRules($action) {
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


}