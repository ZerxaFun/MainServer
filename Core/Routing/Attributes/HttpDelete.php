<?php

namespace Core\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class HttpDelete extends HttpMethod
{
    public function __construct(string $uri, array $options = [])
    {
        parent::__construct('delete', $uri, $options);
    }
}
