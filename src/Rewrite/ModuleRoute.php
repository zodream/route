<?php
declare(strict_types=1);
namespace Zodream\Route\Rewrite;

use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Route\ModuleRoute as Route;

class ModuleRoute extends Route {

    protected function formatRoutePath(HttpContext $context): string
    {
        $path = $context->path();
        list($path, $data) = url()->deRewrite($path);
        $context['request']->append($data);
        return $path;
    }


}