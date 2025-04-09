<?php

namespace Core\Services\Auth\Attributes;


use Core\Services\Routing\Router;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Authorize
{
    public function __construct(
        public ?string $guard = 'jwt',
        public string|array|null $permission = null
    ) {}
}
