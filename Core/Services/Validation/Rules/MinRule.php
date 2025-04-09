<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class MinRule implements RuleInterface
{
    private float|int $min;

    public function __construct(float|int $min)
    {
        $this->min = $min;
    }

    public function validate(string $field, mixed $value, array $data): bool
    {
        return isset($data[$field]) && $value >= $this->min;
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть не меньше {$this->min}.";
    }
}
