<?php
declare(strict_types=1);
namespace Zodream\Route\Rewrite;

use Zodream\Route\UrlGenerator as Generator;

class UrlGenerator extends Generator {

    protected array $encoders = [
          RewriteEncoder::class
    ];
}