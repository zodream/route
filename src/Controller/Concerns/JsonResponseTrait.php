<?php
namespace Zodream\Route\Controller\Concerns;

use Zodream\Html\Page;
use Zodream\Infrastructure\Http\Output\RestResponse;
use Zodream\Infrastructure\Http\Response;
use Zodream\Infrastructure\Interfaces\ArrayAble;
use Zodream\Service\Factory;

trait JsonResponseTrait {

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
     * @return Response
     * @throws \Exception
     */
    public function renderResponse($data, $type = 'json') {
        switch (strtolower($type)) {
            case 'xml':
                return Factory::response()->xml($data);
            case 'jsonp':
                return Factory::response()->jsonp($data);
        }
        return Factory::response()->json($data);
    }

    public function render($data) {
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

    /**
     * ajax 成功返回
     * @param null $data
     * @param null $message
     * @return Response
     * @throws \Exception
     */
    public function renderData($data = null, $message = null) {
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

    public function renderPage(Page $page) {
        return $this->renderEncode(array_merge([
            'code' => 200,
            'status' => __('success'),
        ], $page->toArray()));
    }

    /**
     * ajax 失败返回
     * @param string|array $message
     * @param int $code
     * @return Response
     * @throws \Exception
     */
    public function renderFailure($message = '', $code = 400) {
        return $this->renderEncode([
            'code' => $code,
            'status' => __('failure'),
            'message' => $message
        ]);
    }
}