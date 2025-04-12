<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class TypeRule implements RuleInterface
{
    protected string $type;

    public function __construct(string $type)
    {
        $this->type = strtolower($type);
    }

    public function validate(string $field, mixed $value, array $data): bool
    {
        return match ($this->type) {
            'string'  => is_string($value),
            'int', 'integer' => is_int($value),
            'float'   => is_float($value),
            'numeric' => is_numeric($value),
            'bool', 'boolean' => is_bool($value),
            'array'   => is_array($value),
            'object'  => is_object($value),
            'null'    => is_null($value),
            default   => false,
        };
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть типа {$this->type}.";
    }
}
