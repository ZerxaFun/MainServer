<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class MinRule implements RuleInterface
{
    protected float|int $min;

    public function __construct(float|int $min)
    {
        $this->min = $min;
    }

    public function validate(string $field, mixed $value, array $data): bool
    {
        if (is_string($value)) {
            return mb_strlen($value) >= $this->min;
        }

        if (is_numeric($value)) {
            return floatval($value) >= $this->min;
        }

        return false;
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть не меньше {$this->min}.";
    }
}
