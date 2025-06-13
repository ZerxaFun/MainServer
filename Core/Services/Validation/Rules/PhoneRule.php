<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class PhoneRule implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        return is_string($value) && preg_match('/^\+?[0-9]{7,15}$/', $value);
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть валидным номером телефона.";
    }
}