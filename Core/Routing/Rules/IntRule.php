<?php

namespace Core\Routing\Rules;

class IntRule implements RouteRuleInterface
{
    public function regex(): string
    {
        return '\\d+';
    }
}