<?php
declare(strict_types=1);
namespace Zodream\Route\Controller\Concerns;

use Zodream\Infrastructure\Contracts\Http\Input;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Route\BoundMethod;

trait BatchAction {

    /**
     * 批量执行，允许同一个方法执行多次，[path => [data], 1 => [method => path, data => [data]]]
     * @param array $routes
     * @return array
     * @example
     */
    public function invokeBatch(array $routes) {
        /** @var HttpContext $context */
        $context = $this->httpContext();
        /** @var Input $request */
        $request = $context[Input::class];
        $data = [];
        foreach ($request->all() as $key => $params) {
            $path = $key;
            if (is_integer($key) && !empty($params) && is_array($params) && isset($params['method'])) {
                $path = $params['method'];
                $params = isset($params['data']);
            }
            if (!isset($routes[$path])) {
                continue;
            }
            if (!is_array($params)) {
                $params = [];
            }
            $data[$key] = BoundMethod::call($routes[$path], $context, $params);
        }
//        foreach ($routes as $path => $action) {
//            if (!$request->has($path)) {
//                continue;
//            }
//            $data[$path] = BoundMethod::call($action, $context, $request->get($path, []));
//        }
        return $data;
    }
}