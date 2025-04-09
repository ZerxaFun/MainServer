<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class RequiredRule implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        return isset($data[$field]) && $data[$field] !== '';
    }

    public function message(string $field): string
    {
        return "Поле {$field} обязательно для заполнения.";
    }
}
