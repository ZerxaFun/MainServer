<?php

namespace Core\Services\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class HttpPatch extends HttpMethod
{
    public function __construct(string $uri, array $options = [])
    {
        parent::__construct('patch', $uri, $options);
    }
}
