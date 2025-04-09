<?php

namespace Core\Services\Validation\Exceptions;

use RuntimeException;

class ValidationException extends RuntimeException
{
    protected array $errors;

    public function __construct(array $errors)
    {
        parent::__construct("Данные не прошли валидацию.");
        $this->errors = $errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
