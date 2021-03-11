<?php
declare(strict_types=1);
namespace Zodream\Route\Controller\Middleware;

use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Contracts\Response\JsonResponse;
use Zodream\Route\Response\Json;
use Zodream\Service\Http\Request;
use Zodream\Service\Middleware\MiddlewareInterface;

class RequestMiddleware implements MiddlewareInterface {

    public function handle(HttpContext $context, callable $next)
    {
        $action = $context['action'];
        $controller = $context['controller'];
        if (!$this->checkRequestMethod($controller, $action, $context)) {
            return $this->renderResponse($context, __('Bad Requests'), 400);
        }
        $res = $this->checkRules($controller, $action, $context);
        if ($res !== true) {
            return $res;
        }
        return $next($context);
    }

    protected function checkRules($controller, string $action, HttpContext $context) {
        if (!method_exists($controller, 'rules')) {
            return true;
        }
        $rules = $controller->rules();
        foreach ($rules as $key => $item) {
            if ($action === $key) {
                return $this->processRole($item, $context);
            }
            if (is_integer($key) && is_array($item)) {
                $key = (array)array_shift($item);
                if (in_array($action, $key)) {
                    return $this->processRole($item, $context);
                }
            }
        }
        if (isset($rules['*'])) {
            return $this->processRole($rules['*'], $context);
        }
        return true;
    }

    /**
     * @param string|callable $role
     * @param HttpContext $context
     * @return bool
     */
    protected function processRole($role, HttpContext $context) {
        if (is_callable($role)) {
            return call_user_func($role, $context);
        }
        if (empty($role)) {
            return true;
        }
        if (is_string($role)) {
            $role = explode(',', $role);
        }
        foreach ((array)$role as $item) {
            if (true !== ($arg =
                    $this->processRule($item, $context))) {
                return $arg;
            }
        }
        return true;
    }

    protected function processRule(string $role, HttpContext $context) {
        if ($role === '*') {
            return true;
        }
        // 添加命令行过滤
        if ($role === 'cli') {
            return $context['request']->isCli() ?:
                $this->renderResponse($context, 'not in console', 400);
        }
        if ($role === '?') {
            return auth()->guest() ?:
                $this->renderResponse($context, 'just on guest');
        }
        if ($role === '@') {
            return !auth()->guest() ?:
                $this->renderRedirectAuth($context);
        }
        if ($role === 'p' || $role === 'post') {
            return $context['request']->isPost() ?:
                $this->renderResponse($context, 'just post', 405);
        }
        if ($role === '!') {
            return $this->renderResponse($context, 'Forbidden', 403);
        }
        return $this->processCustomRule($role, $context);
    }

    protected function processCustomRule(string $role, HttpContext $context)
    {
        return true;
    }

    protected function checkRequestMethod($controller, string $action, HttpContext $context): bool {
        if (!method_exists($controller, 'methods')) {
            return true;
        }
        $methods = $controller->methods();
        $method = $context['request']->method();
        return !isset($methods[$action]) ||
            in_array($method, $methods[$action]);
    }

    protected function renderRedirectAuth(HttpContext $context, string $message = '') {
        return $this->renderResponse($context, $message, 401, url(config('auth.home'), ['redirect_uri' => $context['request']->url()]));
    }

    protected function renderResponse(HttpContext $context, string $message, int $code = 404, string $url = '') {
        /** @var JsonResponse $json */
        $json = $context[JsonResponse::class];
        $request = $context['request'];
        $controller = $context['controller'];
        if ($json instanceof Json && $request instanceof Request && !$request->expectsJson()) {
            return !empty($message) ?
                $controller->redirectWithMessage($url, $message, 4, $code)
                : $controller->redirect($url);
        }
        $data = compact('message', 'code');
        if (!empty($data)) {
            $data['url'] = url()->to($url);
        }
        return $json->renderFailure($data, $code);
    }
}