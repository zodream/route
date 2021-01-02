<?php
declare(strict_types=1);
namespace Zodream\Route\Response;

use Zodream\Html\Page;
use Zodream\Infrastructure\Contracts\ArrayAble;
use Zodream\Infrastructure\Contracts\Http\Output;
use Zodream\Infrastructure\Contracts\Response\JsonResponse;
use Zodream\Service\Http\RestResponse;

class Json implements JsonResponse {

    public function render($data): Output
    {
        if (is_array($data)
            && isset($data['code'])
            && isset($data['status'])) {
            return $this->renderEncode($data);
        }
        return $this->renderEncode([
            'code' => 200,
            'status' => __('success'),
            'data' => $data
        ]);
    }

    public function renderData($data, string $message = ''): Output
    {
        if (!is_array($message)) {
            $message = ['message' => $message];
        }
        if ($data instanceof ArrayAble) {
            $data = $data->toArray();
        }
        return $this->renderEncode(array_merge(array(
            'code' => 200,
            'status' => __('success'),
            'data' => $data
        ), $message));
    }

    public function renderPage(Page $page): Output
    {
        return $this->renderEncode(array_merge([
            'code' => 200,
            'status' => __('success'),
        ], $page->toArray()));
    }

    public function renderFailure(array|string $message, int $code = 400): Output
    {
        return $this->renderEncode([
            'code' => $code,
            'status' => __('failure'),
            'message' => $message
        ]);
    }

    /**
     * 获取响应数据各式
     * @return string
     * @throws \Exception
     */
    protected function format(): string {
        return RestResponse::formatType();
    }

    protected function renderEncode($data) {
        return $this->renderResponse($data, $this->format());
    }
    /**
     * ajax 返回
     * @param $data
     * @param string $type
     * @return Output
     * @throws \Exception
     */
    public function renderResponse($data, $type = 'json'): Output {
        $response = app('response');
        switch (strtolower($type)) {
            case 'xml':
                return $response->xml($data);
            case 'jsonp':
                return $response->jsonp($data);
        }
        return $response->json($data);
    }
}