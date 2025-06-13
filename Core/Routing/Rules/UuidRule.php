<?php

namespace Core\Routing\Rules;

class UuidRule implements RouteRuleInterface
{
    public function regex(): string
    {
        return '[0-9a-fA-F-]{36}';
    }
}