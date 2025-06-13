<?php

namespace Core\Services\Auth\Contracts;

use Core\Services\Http\ValidatedRequest;

interface AuthServiceInterface
{
    public function register(ValidatedRequest $request): array;
    public function login(ValidatedRequest $request): array;
    public function logout(): void;
    public function refresh(): array;
    public function me(ValidatedRequest $request): array;
}
