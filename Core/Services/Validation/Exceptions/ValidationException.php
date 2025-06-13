<?php

namespace Core\Services\Validation\Exceptions;

use Core\Services\Http\Request;
use Core\Routing\APIControllers;
use RuntimeException;

class ValidationException extends RuntimeException
{
    protected array $errors;

    /**
     * @throws \JsonException
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;


        parent::__construct("Данные не прошли валидацию.");
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
