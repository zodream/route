<?php
declare(strict_types=1);
namespace Zodream\Route\Exception;

use Exception;

class NotFoundHttpException extends \RuntimeException {
    /**
     * Constructor.
     *
     * @param null $message The internal exception message
     * @param Exception|null $previous The previous exception
     * @param int $code The internal exception code
     */
    public function __construct($message = '', int $code = 404, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}