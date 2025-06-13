<?php

namespace Core\Routing\Rules;

class FloatRule implements RouteRuleInterface
{
    public function regex(): string
    {
        return '\\d+(?:\\.\\d+)?';
    }
}