<?php
declare(strict_types=1);
namespace Zodream\Route\Controller\Concerns;

use Zodream\Infrastructure\Contracts\Http\Input;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Route\BoundMethod;

trait BatchAction {

    /**
     * @param array $routes
     * @return array
     */
    public function invokeBatch(array $routes) {
        /** @var HttpContext $context */
        $context = $this->httpContext();
        /** @var Input $request */
        $request = $context[Input::class];
        $data = [];
        foreach ($routes as $path => $action) {
            if (!$request->has($path)) {
                continue;
            }
            $data[$path] = BoundMethod::call($action, $context, $request->get($path, []));
        }
        return $data;
    }
}