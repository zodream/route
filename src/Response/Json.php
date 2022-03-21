<?php
declare(strict_types=1);
namespace Zodream\Route\Response;

use Zodream\Html\Page;
use Zodream\Infrastructure\Contracts\ArrayAble;
use Zodream\Infrastructure\Contracts\Http\Output;
use Zodream\Infrastructure\Contracts\Response\JsonResponse;

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

    public function renderFailure(array|string $message, int $code = 400, int $statusCode = 0): Output
    {
        if ($statusCode > 0) {
            response()->statusCode($statusCode);
        }
        $data = is_array($message) ? $message : [
            'code' => $code,
            'status' => __('failure'),
            'message' => $message
        ];
        if (!isset($data['code'])) {
            $data['code'] = $code;
        }
        if (!isset($data['message']) && !is_array($message)) {
            $data['message'] = $message;
        }
        return $this->renderEncode($data);
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
    public function renderResponse($data, string $type = 'json'): Output {
        $response = response();
        switch (strtolower($type)) {
            case 'xml':
                return $response->xml($data);
            case 'jsonp':
                return $response->jsonp($data);
        }
        return $response->json($data);
    }
}