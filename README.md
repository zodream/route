# route
路由导航

## 获取指定模板的路径

app(Router::class)->module('name', );

module 包含三个参数

    string $name 可以使用url 路径，也可以是模块命名空间
    callable $handle 默认为空，返回数组[路径, 模块命名空间]，为匿名方法时，url('./') 所使用的为模块的路径,支持匿名方法返回值
    array $modules 默认未空，表示自动从配置文件中取所有注册模块
    返回值，如果未找到注册的模板，则返回false, 找到了没有匿名方法返回数组[路径, 模块命名空间]，其他返回匿名方法返回的值

## 自定义路由

路由文件
```php
$router->get('gggggg', 'HomeController@aboutAction');
$router->group([
    'module' => 'Module\Blog\Module',
    'namespace' => '\Module\Blog\Service'
], function ($router) {
   $router->get('hhh', 'HomeController@indexAction');
});
```

服务提供

```php
class RouteServiceProvider extends ServiceProvider {
    public function register()
    {
        $this->mapWebRoutes($this->app->make(Router::class));
    }
    protected function mapWebRoutes(Router $router)
    {
        $router->group([
            'namespace' => 'Service\Home',
        ], __DIR__.'/routes.php');
    }
}
```

## BUG

模块判断

注册 'chat' => 'Module/Chat'

请求路径可以是  /chat/a 也可以是 /chata 都能正确导向 模块中的 a 方法
