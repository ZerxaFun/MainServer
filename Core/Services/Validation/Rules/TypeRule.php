<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class TypeRule implements RuleInterface
{
    private string $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function validate(string $field, mixed $value, array $data): bool
    {
        if (!isset($data[$field])) return true;

        return match ($this->type) {
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL),
            'string' => is_string($value),
            'numeric' => is_numeric($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            default => false,
        };
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть типа {$this->type}.";
    }
}
