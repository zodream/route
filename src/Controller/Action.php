<?php
declare(strict_types=1);
namespace Zodream\Route\Controller;


/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/5/22
 * Time: 8:55
 */

use Zodream\Infrastructure\Contracts\Http\Output;
use Zodream\Infrastructure\Contracts\HttpContext;

abstract class Action {

    public function init() { }

    public function prepare(HttpContext $context, string $action) {  }

    /**
     * 其他Action正式执行的入口 允许返回值
     * @param HttpContext $context
     * @param string $action
     * @param array $vars
     * @return string|Output|null
     */
    public function invokeMethod(HttpContext $context, string $action, array $vars = []) {
        return null;
    }
    
    public function finalize(HttpContext $context, $response) {  }
}