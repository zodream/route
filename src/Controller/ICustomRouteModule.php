<?php
declare(strict_types=1);
namespace Zodream\Route\Controller;

use Zodream\Infrastructure\Contracts\Http\Output;
use Zodream\Infrastructure\Contracts\HttpContext;

interface ICustomRouteModule {

    /**
     * 自己解析路径
     * @param string $path
     * @param HttpContext $context
     * @return string|Output|null 返回 string 则是重定向到当前模块的其他路径
     */
    public function invokeRoute(string $path, HttpContext $context): null|string|Output;
}