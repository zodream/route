<?php
declare(strict_types=1);
namespace Zodream\Route\Rewrite;

use Zodream\Route\ModuleRoute as Route;
use Zodream\Infrastructure\Contracts\UrlGenerator as UrlGeneratorInterface;
use Zodream\Infrastructure\Support\ServiceProvider;

class RewriteServiceProvider extends ServiceProvider {

    public function register()
    {
        $this->app->transient(Route::class, ModuleRoute::class);
        $this->app->singleton(UrlGeneratorInterface::class, UrlGenerator::class);
    }
}