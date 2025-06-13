<?php

namespace Core\Routing;

readonly class MatchedRoute
{
    public function __construct(
        public array $params = []
    ) {}

    public function get(string $key): string|null
    {
        return $this->params[$key] ?? null;
    }
}
