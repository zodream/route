<?php
declare(strict_types=1);
namespace Zodream\Route\Events;


class ViewRendered {
    public $content;

    public function __construct($content) {
        $this->content = $content;
    }
}