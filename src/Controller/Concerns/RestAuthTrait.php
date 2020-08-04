<?php
namespace Zodream\Route\Controller\Concerns;

trait RestAuthTrait {

    public function redirectWithAuth() {
        return $this->renderFailure(__('Please Login User!'), 401, 401);
    }
}