<?php

namespace Core\Routing\Rules;

class BoolRule implements RouteRuleInterface
{
    public function regex(): string
    {
        return '0|1|true|false';
    }
}