<?php

namespace Core\Services\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class HttpPut extends HttpMethod
{
    public function __construct(string $uri, array $options = [])
    {
        parent::__construct('put', $uri, $options);
    }
}
