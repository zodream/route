<?php
declare(strict_types=1);
namespace Zodream\Route\Rewrite;

use Zodream\Http\Uri;

interface URLEncoder {

    /**
     * 解析当前网址
     * @param Uri $url
     * @param callable $next
     * @return Uri
     */
    public function decode(Uri $url, callable $next): Uri;

    /**
     * 编译网址
     * @param Uri $url
     * @param callable $next
     * @return Uri
     */
    public function encode(Uri $url, callable $next): Uri;

}