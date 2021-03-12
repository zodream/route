<?php
declare(strict_types=1);
namespace Zodream\Route\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route {

    public function __construct(
        public string $path,
        public array $method = [],
        public array $middleware = [],
        public array $module = [],
        public array $options = [])
    {

    }
}