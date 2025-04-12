<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class DateRule implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        return (bool)strtotime($value);
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть валидной датой.";
    }
}