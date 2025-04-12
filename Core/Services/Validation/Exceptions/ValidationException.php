<?php

namespace Core\Services\Validation\Exceptions;

use Core\Services\Http\Request;
use Core\Services\Routing\APIControllers;
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

        // Если это API-запрос — отдадим JSON-ошибки прямо отсюда
        if (Request::isJson() || Request::isApi()) {
            APIControllers::setData([
                'message' => 'Данные не прошли валидацию',
                'errors'  => $this->errors,
            ], 422, 'error');
        }

        parent::__construct("Данные не прошли валидацию.");
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
