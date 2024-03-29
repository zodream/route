<?php
declare(strict_types=1);
namespace Zodream\Route\Response;

use Zodream\Helpers\Arr;
use Zodream\Helpers\Json;
use Zodream\Helpers\Str;
use Zodream\Helpers\Xml;
use Zodream\Infrastructure\Contracts\Http\Output;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Contracts\Response\PreResponse;

class RestResponse implements PreResponse, Output {

    const TYPE_JSON = 0;

    const TYPE_XML = 1;

    const TYPE_JSON_P = 2;

    protected int $type = self::TYPE_JSON;

    protected mixed $data;

    /**
     * @var HttpContext
     */
    protected HttpContext $app;

    public function __construct(mixed $data, string|int $type = self::TYPE_JSON) {
        $this->setType($type)->setData($data);
        $this->app = app(HttpContext::class);
    }

    /**
     * @param string|int $type
     * @return RestResponse
     */
    public function setType(string|int $type) {
        $this->type = self::converterType($type);
        return $this;
    }

    /**
     * @return int
     */
    public function getType(): int {
        return $this->type;
    }

    /**
     * @param mixed $data
     * @return RestResponse
     */
    public function setData(mixed $data): static {
        $this->data = $data;
        return $this;
    }

    public function writeLine(mixed $messages): void {
        $this->setData($messages);
    }

    /**
     * @return mixed
     */
    public function getData(): mixed {
        return $this->data;
    }

    /**
     * @param Output $response
     * @throws \Exception
     */
    public function ready(Output $response) {
        $response->allowCors();
        if ($this->type === self::TYPE_XML) {
            $response->xml($this->formatXml($this->data));
            return;
        }
        if ($this->type === self::TYPE_JSON_P) {
            $response->jsonp($this->data);
            return;
        }
        $response->json($this->data);
    }

    public function text() {
        if ($this->type === self::TYPE_XML) {
            return $this->formatXml($this->data);
        }
        return Json::encode($this->data);
    }

    public function formatXml(mixed $data) {
        if (!is_array($data)) {
            return $data;
        }
        $count = count(array_filter(array_keys($data), 'is_numeric'));
        // 数字不能作为xml的标签
        if ($count > 0) {
            $data = compact('data');
        }
        return Xml::specialEncode($data);
    }

    /**
     * @param mixed $data
     * @param string|int $type
     * @return RestResponse
     */
    public static function create(mixed $data, string|int $type = self::TYPE_JSON) {
        return new static($data, $type);
    }

    /**
     * @param mixed $data
     * @return RestResponse
     * @throws \Exception
     */
    public static function createWithAuto(mixed $data) {
        return static::create($data, self::formatType());
    }

    /**
     * 转化成当前可用类型
     * @param string|int $format
     * @return int
     */
    public static function converterType(string|int $format): int {
        $format = is_numeric($format) ? intval($format) : strtolower($format);
        if ($format == 'xml' || $format === self::TYPE_XML) {
            return self::TYPE_XML;
        }
        if ($format == 'jsonp' || $format === self::TYPE_JSON_P) {
            return self::TYPE_JSON_P;
        }
        return self::TYPE_JSON;
    }

    /**
     * 获取内容类型
     * @return string
     * @throws \Exception
     */
    public static function formatType(): string {
        $request = request();
        $format = $request->get('format');
        if (!empty($format)) {
            return strtolower($format);
        }
        $accept = $request->header('ACCEPT');
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

    public function __toString() {
        return $this->text();
    }

    public function toArray(): array {
        return Arr::toArray($this->data);
    }

    public function send(): bool {
        $output = $this->app->make('response');
        $this->ready($output);
        return $output->send();
    }

    public function statusCode(int $code, string $statusText = ''): Output {
        $output = $this->app->make('response');
        $output->statusCode($code, $statusText);
        return $output;
    }

    public function allowCors(): Output {
        $output = $this->app->make('response');
        $output->allowCors();
        return $output;
    }
}