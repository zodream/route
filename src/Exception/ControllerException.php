<?php
declare(strict_types=1);
namespace Zodream\Route\Exception;

use Exception;
use Throwable;

class ControllerException extends Exception {
    /**
     * Constructor.
     *
     * @param string $message The internal exception message
     * @param int $code The internal exception code
     * @param Throwable|null $previous The previous exception
     */
    public function __construct($message = '', $code = 404, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}