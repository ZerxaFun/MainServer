<?php

namespace Core\Routing\Rules;

class SlugRule implements RouteRuleInterface
{
    public function regex(): string
    {
        return '[a-z0-9-]+';
    }
}