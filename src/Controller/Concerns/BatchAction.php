<?php
declare(strict_types=1);
namespace Zodream\Route\Controller\Concerns;

use Zodream\Infrastructure\Contracts\Http\Input;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Route\BoundMethod;
use Zodream\Route\Response\RestResponse;

trait BatchAction {

    /**
     * 批量执行，允许同一个方法执行多次，[path => [data], 1 => [method => path, data => [data]]]
     * @param array $routes
     * @param array|null $postData 默认从请求参数中自动获取
     * @return array
     * @example
     */
    public function invokeBatch(array $routes, ?array $postData = null): array {
        /** @var HttpContext $context */
        $context = $this->httpContext();
        if (is_null($postData)) {
            /** @var Input $request */
            $request = $context[Input::class];
            $postData = $request->all();
        }
        $data = [];
        foreach ($postData as $key => $params) {
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
            $res = BoundMethod::call($routes[$path], $context, $params);
            $data[$key] = $res instanceof RestResponse ? $res->getData() : $res;
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