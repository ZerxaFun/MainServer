<?php

namespace Core\Routing\Rules;

class DateRule implements RouteRuleInterface
{
    public function regex(): string
    {
        return '\\d{4}-\\d{2}-\\d{2}';
    }
}