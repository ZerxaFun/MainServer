<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class EmailRule implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть корректным email-адресом.";
    }
}
