<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;


class EqualsRule implements RuleInterface
{
    protected int|float|string $expected;

    public function __construct(int|float|string $expected)
    {
        $this->expected = $expected;
    }

    public function validate(string $field, mixed $value, array $data): bool
    {
        if (is_string($value) && is_int($this->expected)) {
            return mb_strlen($value) === $this->expected;
        }

        return $value === $this->expected;
    }

    public function message(string $field): string
    {
        if (is_int($this->expected)) {
            return "Поле {$field} должно содержать ровно {$this->expected} символов.";
        }

        return "Поле {$field} должно быть равно {$this->expected}.";
    }
}