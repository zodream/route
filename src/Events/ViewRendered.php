<?php
namespace Zodream\Route\Events;


class ViewRendered {
    public $content;

    public function __construct($content) {
        $this->content = $content;
    }
}