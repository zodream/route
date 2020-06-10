<?php
namespace Zodream\Route\Controller;

abstract class ModuleController extends Controller {

    /**
     * Module config setting
     */
    public function configAction() {}



    protected function getActionName($action) {
        if (app('request')->expectsJson()) {
            return $this->getAjaxActionName($action);
        }
        return parent::getActionName($action);
    }

    protected function getAjaxActionName($action) {
        $arg = parent::getActionName($action).'Json';
        return method_exists($this, $arg) ? $arg : parent::getActionName($action);
    }

    public function hasMethod($action) {
        return array_key_exists($action, $this->actions())
            || method_exists($this, $this->getActionName($action));
    }

    protected function getViewFile($name = null) {
        if (is_null($name)) {
            $name = $this->action;
        }
        $first = substr($name, 0, 1);
        if ($first !== '@' && $first !== '/') {
            $pattern = '.*?Service.(.+)'.config('app.controller');
            $name = preg_replace('/^'.$pattern.'$/', '$1', get_called_class()).'/'.$name;
        }
        return $name;
    }
}