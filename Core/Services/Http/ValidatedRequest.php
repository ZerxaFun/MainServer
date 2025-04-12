<?php

namespace Core\Services\Http;

class ValidatedRequest
{
    protected array $validated;

    public function __construct(array $validated)
    {
        $this->validated = $validated;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->validated[$key] ?? $default;
    }
}