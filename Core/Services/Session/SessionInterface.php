<?php

namespace Core\Services\Session;


interface SessionInterface
{
    public function start(): void;
    public function put(string $key, mixed $value): void;
    public function get(string $key, mixed $default = null): mixed;
    public function has(string $key): bool;
    public function forget(string $key): void;
    public function flush(): void;
    public function all(): array;
    public function id(): string;
    public function regenerate(): void;
    public function destroy(): void;
}
