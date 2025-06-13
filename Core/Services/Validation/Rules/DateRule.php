<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class DateRule implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Попытка удалить миллисекунды и Z из ISO8601 строки
        $normalized = preg_replace('/\.\d{3}Z$/', '', $value);

        // Проверяем strtotime уже после нормализации
        return $normalized && strtotime($normalized) !== false;
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть валидной датой.";
    }
}