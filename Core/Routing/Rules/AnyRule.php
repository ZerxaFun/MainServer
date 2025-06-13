<?php

namespace Core\Routing\Rules;

class AnyRule implements RouteRuleInterface
{
    public function regex(): string
    {
        return '[^/]+';
    }
}