<?php
declare(strict_types=1);
namespace Zodream\Route\Controller\Concerns;

use Zodream\Html\Page;
use Zodream\Infrastructure\Contracts\Http\Output;
use Zodream\Infrastructure\Contracts\Response\JsonResponse;

trait Json {


    public function jsonFactory(): JsonResponse {
        return app(JsonResponse::class);
    }

    /**
     * 响应数据
     * @param $data
     * @return Output
     */
    public function render($data): Output
    {
        return $this->jsonFactory()->render($data);
    }

    /**
     * 响应data
     * @param $data
     * @param string $message
     * @return Output
     */
    public function renderData($data, string $message = ''): Output
    {
        return $this->jsonFactory()->renderData($data, $message);
    }

    /**
     * 响应分页数据
     * @param Page $page
     * @return Output
     */
    public function renderPage(Page $page): Output
    {
        return $this->jsonFactory()->renderPage($page);
    }

    /**
     * 响应失败数据
     * @param string|array $message
     * @param int $code
     * @return Output
     */
    public function renderFailure(string|array $message, int $code = 400): Output
    {
        return $this->jsonFactory()->renderFailure($message, $code);
    }
}