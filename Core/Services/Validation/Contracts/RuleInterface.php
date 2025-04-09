<?php

namespace Core\Services\Validation\Contracts;

interface RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool;
    public function message(string $field): string;
}
