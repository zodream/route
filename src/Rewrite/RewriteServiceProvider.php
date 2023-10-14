<?php
declare(strict_types=1);
namespace Zodream\Route\Rewrite;

use Zodream\Infrastructure\Contracts\UrlGenerator as UrlGeneratorInterface;
use Zodream\Infrastructure\Support\ServiceProvider;

class RewriteServiceProvider extends ServiceProvider {

    public function register(): void {
        $this->app->singleton(UrlGeneratorInterface::class, UrlGenerator::class);
    }
}