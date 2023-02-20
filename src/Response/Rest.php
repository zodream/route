<?php
declare(strict_types=1);
namespace Zodream\Route\Response;

use Zodream\Helpers\Arr;
use Zodream\Html\Page;
use Zodream\Infrastructure\Contracts\ArrayAble;
use Zodream\Infrastructure\Contracts\Http\Output;
use Zodream\Infrastructure\Contracts\Response\JsonResponse;

class Rest implements JsonResponse {

    public function render($data): Output
    {
        return $this->renderEncode(Arr::toArray($data));
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
            'data' => $data
        ), $message));
    }

    public function renderPage(Page $page): Output
    {
        return $this->renderEncode($page->toArray());
    }

    public function renderFailure(array|string $message, int $code = 400, int $statusCode = 0): Output
    {
        if ($statusCode <= 0) {
            $statusCode = $code > 0 ? $code : 400;
        }
        response()->statusCode($statusCode);
        $data = is_array($message) ? $message : [
            'code' => $code,
            'message' => $message
        ];
        if (!isset($data['code'])) {
            $data['code'] = $code;
        }
        if (!isset($data['message']) && !is_array($message)) {
            $data['message'] = $message;
        }
        return $this->render($data);
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
        return RestResponse::create($data, $this->format());
    }

    /**
     * 把响应信息插入json
     * @param $data
     * @param int $status
     * @param array $headers
     * @return RestResponse
     * @throws \Exception
     */
    protected function renderEnvelope($data, int $status = 200, array $headers = []) {
        $data = [
            'status' => $status,
            'response' => $data
        ];
        if (!empty($headers)) {
            $data['headers'] = $headers;
        }
        return $this->renderEncode($data);
    }
}