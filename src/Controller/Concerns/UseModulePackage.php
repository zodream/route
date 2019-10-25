<?php
namespace Zodream\Route\Controller\Concerns;

trait UseModulePackage {
    public function getControllerNamespace() {
        $prefix = parent::getControllerNamespace();
        if (app('app.module') != 'Home') {
            return $prefix .'\\'. app('app.module');
        }
        return $prefix;
    }
}
