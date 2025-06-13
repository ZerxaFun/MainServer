<?php

namespace Core\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class HttpMethod
{
    public array $method;
    public string $uri;
    public array $options;

    public function __construct(array|string $method, string $uri, array $options = [])
    {

        $this->method = (array)$method;
        $this->uri = $uri;
        $this->options = $options;
    }
}
