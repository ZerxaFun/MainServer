<?php

namespace Core\Routing;


use Core\Routing\Rules\AlphanumRule;
use Core\Routing\Rules\AlphaRule;
use Core\Routing\Rules\AnyRule;
use Core\Routing\Rules\BoolRule;
use Core\Routing\Rules\DateRule;
use Core\Routing\Rules\FloatRule;
use Core\Routing\Rules\IntRule;
use Core\Routing\Rules\SlugRule;
use Core\Routing\Rules\UuidRule;

class RouteRuleFactory
{
    public static function make(string $type)
    {
        return match ($type) {
            'int' => new IntRule(),
            'float' => new FloatRule(),
            'slug' => new SlugRule(),
            'any' => new AnyRule(),
            'uuid' => new UuidRule(),
            'alpha' => new AlphaRule(),
            'alphanum' => new AlphanumRule(),
            'date' => new DateRule(),
            'bool' => new BoolRule(),
            default => throw new \InvalidArgumentException("Unknown route rule type: $type"),
        };
    }
}
