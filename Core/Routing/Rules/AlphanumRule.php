<?php

namespace Core\Routing\Rules;

class AlphanumRule implements RouteRuleInterface
{
    public function regex(): string
    {
        return '[a-zA-Z0-9]+';
    }
}