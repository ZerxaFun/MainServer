<?php

namespace Core\Services\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Validate
{
    public function __construct(
        public  $rules
    ) {}
}