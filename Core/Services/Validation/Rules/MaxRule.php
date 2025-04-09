<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class MaxRule implements RuleInterface
{
    private float|int $max;

    public function __construct(float|int $max)
    {
        $this->max = $max;
    }

    public function validate(string $field, mixed $value, array $data): bool
    {
        return isset($data[$field]) && $value <= $this->max;
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть не больше {$this->max}.";
    }
}
