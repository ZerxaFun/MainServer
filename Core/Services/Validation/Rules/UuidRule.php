<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class UuidRule implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        return is_string($value) && preg_match(
                '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/',
                $value
            );
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть валидным UUID.";
    }
}