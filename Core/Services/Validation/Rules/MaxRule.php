<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;


class MaxRule implements RuleInterface
{
    protected int $max;

    public function __construct(int $max)
    {
        $this->max = $max;
    }

    public function validate(string $field, mixed $value, array $data): bool
    {
        if (is_string($value)) {
            return mb_strlen($value) <= $this->max;
        }

        if (is_numeric($value)) {
            return $value >= $this->max;
        }

        return false;
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть не длиннее {$this->max} символов.";
    }
}

