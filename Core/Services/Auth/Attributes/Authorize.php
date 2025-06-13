<?php

namespace Core\Services\Auth\Attributes;


#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Authorize
{
    public function __construct(
        public ?string $guard = 'jwt',
        public string|array|null $permission = null
    ) {}
}
