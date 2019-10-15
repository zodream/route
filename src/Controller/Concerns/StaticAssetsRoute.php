<?php
namespace Zodream\Route\Controller\Concerns;

use Zodream\Disk\File;

trait StaticAssetsRoute {
    /**
     * 返回允许的拓展名
     * @return array
     */
    protected function allowExt() {
        return [
            'js', 'css', 'png', 'gif', 'jpg', 'jpeg', 'json', 'bmp', 'svg'
        ];
    }

    public function invokeRoute($path) {
        $path = trim($path, '/');
        if (empty($path)) {
            return;
        }
        if (strpos($path, 'assets') !== 0) {
            return;
        }
        /** @var File $file */
        $file = $this->getViewPath()->file($path);
        if (!$file->exist()) {
            return;
        }
        $allow = $this->allowExt();
        $ext = $file->getExtension();
        if (!in_array($ext, $allow)) {
            return;
        }
        $response = app('response');
        $response->header->setContentType($ext);
        return $response->setParameter($file);
    }
}