<?php
namespace Zodream\Route\Controller\Concerns;


use Zodream\Helpers\Str;
use Zodream\Helpers\Xml;
use Zodream\Html\Page;
use Zodream\Infrastructure\Http\Request;
use Zodream\Infrastructure\Http\Response;
use Zodream\Infrastructure\Interfaces\ArrayAble;
use Zodream\Service\Factory;

trait RestTrait {

    protected function format() {
        $accept = app('request')->header('ACCEPT');
        if (empty($accept)) {
            return 'json';
        }
        $args = explode(';', $accept);
        if (Str::contains($args[0], ['/jsonp', '+jsonp'])) {
            return 'jsonp';
        }
        if (Str::contains($args[0], ['/xml', '+xml'])) {
            return 'xml';
        }
        return 'json';
    }

    /**
     * @param array $data
     * @param int $statusCode
     * @param array $headers
     * @return Response
     * @throws \Exception
     */
    public function render($data, $statusCode = 200, $headers = []) {
        if ($data instanceof ArrayAble) {
            $data = $data->toArray();
        }
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
     * @return Response
     * @throws \Exception
     */
    protected function renderEncode($data) {
        switch (strtolower($this->format())) {
            case 'xml':
                return Factory::response()->xml($this->formatXml($data));
            case 'jsonp':
                return Factory::response()->jsonp($data);
            default:
                return Factory::response()->json($data);
        }
    }

    protected function formatXml($data) {
        if (!is_array($data)) {
            return $data;
        }
        return Xml::specialEncode($data);
    }

    /**
     * @param $data
     * @param int $status
     * @param array $headers
     * @return Response
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
     * @return Response
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
     * @return Response
     * @throws \Exception
     */
    public function renderPage(Page $page) {
        return $this->render($page->toArray());
    }

}