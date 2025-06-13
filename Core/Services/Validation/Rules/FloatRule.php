<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class FloatRule implements RuleInterface
{
    protected mixed $param;

    public function __construct(mixed $param = null)
    {
        $this->param = $param;
    }

    public function validate(string $field, mixed $value, array $data): bool
    {
        return is_float($value) || (is_numeric($value) && str_contains((string)$value, '.'));
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть числом с плавающей точкой.";
    }
}
