<?php

namespace Core\Services\Routing\Attributes;


#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class HttpGet extends HttpMethod
{
    public function __construct(string $uri, array $options = [])
    {
        parent::__construct('get', $uri, $options);
    }
}
