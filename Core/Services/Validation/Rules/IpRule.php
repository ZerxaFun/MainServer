<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;

class IpRule implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    public function message(string $field): string
    {
        return "Поле {$field} должно быть валидным IP-адресом.";
    }
}