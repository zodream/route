<?php
namespace Zodream\Route\Controller\Concerns;


use Zodream\Helpers\Arr;
use Zodream\Helpers\Str;
use Zodream\Html\Page;
use Zodream\Infrastructure\Http\Output\RestResponse;
use Zodream\Service\Factory;

trait RestTrait {

    protected function format() {
        return RestResponse::formatType();
    }

    /**
     * @param array $data
     * @param int $statusCode
     * @param array $headers
     * @return RestResponse
     * @throws \Exception
     */
    public function render($data, $statusCode = 200, $headers = []) {
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
     * @param $data
     * @return RestResponse
     * @throws \Exception
     */
    protected function renderEncode($data) {
        return RestResponse::create($data, $this->format());
    }

    /**
     * @param $data
     * @param int $status
     * @param array $headers
     * @return RestResponse
     * @throws \Exception
     */
    protected function renderEnvelope($data, $status = 200, $headers = []) {
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
    public function renderFailure($message = '', $code = 10000, $statusCode = 400, $errors = [], $description = '') {
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
     * @param Page $page
     * @return RestResponse
     * @throws \Exception
     */
    public function renderPage(Page $page) {
        return $this->render($page->toArray());
    }

}