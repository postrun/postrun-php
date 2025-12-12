<?php

namespace PostRun\Exceptions;

use Throwable;

class ValidationException extends PostRunException
{
    protected array $errors;

    public function __construct(
        string $message,
        array $errors = [],
        int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
