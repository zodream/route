<?php
declare(strict_types=1);
namespace Zodream\Route\Controller\Concerns;

use Zodream\Helpers\Arr;
use Zodream\Html\Page;
use Zodream\Infrastructure\Http\Output\RestResponse;
use Zodream\Service\Factory;

trait RestTrait {

    /**
     * 获取响应数据各式
     * @return string
     * @throws \Exception
     */
    protected function format(): string {
        return RestResponse::formatType();
    }

    /**
     * @param array|mixed $data
     * @param int $statusCode
     * @param array $headers
     * @return RestResponse
     * @throws \Exception
     */
    public function render($data, int $statusCode = 200, array $headers = []) {
        $data = Arr::toArray($data);
        $envelope = app('request')->get('envelope') === 'true';
        if ($envelope) {
            return $this->renderEnvelope($data, $statusCode, $headers);
        }
        Factory::response()->setStatusCode($statusCode)
            ->header->add($headers);
        return $this->renderEncode($data);
    }

    /**
     * 响应
     * @param $data
     * @return RestResponse
     * @throws \Exception
     */
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

    /**
     * ajax 失败返回
     * @param string|array $message
     * @param int $code
     * @param int $statusCode
     * @param array $errors
     * @param string $description
     * @return RestResponse
     * @throws \Exception
     */
    public function renderFailure($message = '', int $code = 10000, int $statusCode = 400, array $errors = [], string $description = '') {
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
        if (!empty($errors)) {
            $data['errors'] = $errors;
        }
        if (!empty($description)) {
            $data['description'] = $description;
        }
        return $this->render($data, $statusCode);
    }

    /**
     * 响应分页数据
     * @param Page $page
     * @return RestResponse
     * @throws \Exception
     */
    public function renderPage(Page $page) {
        return $this->render($page->toArray());
    }

    /**
     * 响应[data: object]
     * @param mixed $data
     * @return RestResponse
     * @throws \Exception
     */
    public function renderData($data) {
        return $this->render(compact('data'));
    }

}