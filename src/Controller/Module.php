<?php
declare(strict_types=1);
namespace Zodream\Route\Controller;


/**
 * 模块基类
 *
 * @author Jason
 * @time 2015-12-19
 */
use Zodream\Database\Migrations\Migration;
use Zodream\Disk\Directory;
use Zodream\Infrastructure\Contracts\HttpContext;
use Zodream\Infrastructure\Http\Response;
use Zodream\Route\Router;

abstract class Module extends Action {

    /**
     * @var Directory
     */
    protected $basePath;


    /**
     * @return Directory
     * @throws \ReflectionException
     */
    public function getBasePath(): Directory {
        if ($this->basePath === null) {
            $class = new \ReflectionClass($this);
            $this->basePath = new Directory(dirname($class->getFileName()));
        }
        return $this->basePath;
    }

    /**
     * @return Directory.
     * @throws \ReflectionException
     */
    public function getViewPath() {
        return $this->getBasePath()->childDirectory('UserInterface');
    }

    public function getControllerNamespace() {
        $class = get_class($this);
        if (($pos = strrpos($class, '\\')) !== false) {
            return substr($class, 0, $pos) . '\\Service';
        }
        return '';
    }

    /**
     * 启动
     */
    public function boot() {

    }

    /**
     * 安装
     */
    public function install() {
        $migration = $this->getMigration();
        if (!$migration instanceof Migration) {
            return;
        }
        $migration->up();
    }

    /**
     * 卸载
     */
    public function uninstall() {
        $migration = $this->getMigration();
        if (!$migration instanceof Migration) {
            return;
        }
        $migration->down();
    }

    /**
     * 填充测试数据
     */
    public function seeder() {
        $migration = $this->getMigration();
        if (!$migration instanceof Migration) {
            return;
        }
        $migration->seed();
    }

    /**
     * @return Migration|void
     */
    public function getMigration() {
        return;
    }

    /**
     * 执行控制器的指定方法
     * @param $class
     * @param $action
     * @param array $vars
     * @return Response
     * @throws \Exception
     */
    public function invokeController($class, $action, $vars = []) {
        return app(Router::class)
            ->invokeController($class, $action, $vars);
    }

    /**
     * @param $module
     * @param $path
     * @return mixed
     * @throws \Exception
     */
    public function invokeModule($module, $path) {
        $context = app(HttpContext::class);
        return $context->make('route')
            ->invokeModule($path, $module, $context);
    }

    public static function url($path = null, $parameters = [], $secure = null) {
        $url = false;
        $args = func_get_args();
        app(HttpContext::class)->make('route')->module(static::class, function () use (&$url, $args) {
            $url = url()->to(...$args);
        });
        if ($url === false) {
            throw new \Exception(sprintf('[%s] is uninstall', static::class));
        }
        return $url;
    }

}