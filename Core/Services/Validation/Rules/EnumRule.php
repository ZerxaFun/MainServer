<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class EnumRule implements RuleInterface
{
    protected array $allowed;

    public function __construct(array $allowed)
    {
        $this->allowed = $allowed;
    }

    public function validate(string $field, mixed $value, array $data): bool
    {
        return in_array($value, $this->allowed, true);
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть одним из: " . implode(', ', $this->allowed);
    }
}