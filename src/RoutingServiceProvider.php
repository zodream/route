<?php
declare(strict_types=1);
namespace Zodream\Route;

use Zodream\Domain\Access\Auth;
use Zodream\Infrastructure\Caching\FileCache;
use Zodream\Infrastructure\Contracts\Http\Input;
use Zodream\Infrastructure\Contracts\Http\Output;
use Zodream\Infrastructure\Contracts\HttpContext as HttpContextInterface;
use Zodream\Infrastructure\Contracts\Response\JsonResponse;
use Zodream\Infrastructure\Contracts\Router as RouterInterface;
use Zodream\Infrastructure\Contracts\UrlGenerator as UrlGeneratorInterface;
use Zodream\Infrastructure\I18n\PhpSource;
use Zodream\Infrastructure\Session\CacheSession;
use Zodream\Infrastructure\Support\ServiceProvider;
use Zodream\Service\Http\HttpContext;
use Zodream\Service\Http\Request;
use Zodream\Service\Http\Response;
use Zodream\Route\Response\Json;
use Zodream\Template\ViewFactory;

class RoutingServiceProvider extends ServiceProvider {

    public function register(): void {
        $this->app->singletonIf(RouterInterface::class, Router::class);
        $this->app->scopedIf(HttpContextInterface::class, HttpContext::class);
        $this->app->scopedIf(JsonResponse::class, Json::class);
        $this->app->singletonIf(UrlGeneratorInterface::class, UrlGenerator::class);
        $this->app->scopedIf(Output::class, Response::class);
        $this->app->scopedIf(Input::class, Request::class);
        $this->app->scopedIf(ModuleRoute::class);
        $this->app->alias(Input::class, 'request');
        $this->app->alias(Output::class, 'response');
        $this->app->alias(UrlGeneratorInterface::class, 'url');
        $this->app->alias(RouterInterface::class, 'router');
    }
}