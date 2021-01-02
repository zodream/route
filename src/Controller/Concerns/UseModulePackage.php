<?php
declare(strict_types=1);
namespace Zodream\Route\Controller\Concerns;

trait UseModulePackage {
    public function getControllerNamespace(): string {
        $prefix = parent::getControllerNamespace();
        $moduleName = app('app.module');
        if (!empty($moduleName) && $moduleName !== 'Home') {
            return $prefix .'\\'. $moduleName;
        }
        return $prefix;
    }
}
