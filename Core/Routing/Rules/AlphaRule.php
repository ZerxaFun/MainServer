<?php

namespace Core\Routing\Rules;

class AlphaRule implements RouteRuleInterface
{
    public function regex(): string
    {
        return '[a-zA-Z]+';
    }
}