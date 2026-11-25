<?php
declare(strict_types=1);
namespace Zodream\Route\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Route {

    public function __construct(
        public readonly string $path,
        public readonly array $method = [],
        public readonly array $middleware = [],
        public readonly array $module = [],
        public readonly array $options = [])
    {

    }
}