<?php

namespace Core\Routing;

use Core\Routing\Rules\RouteRuleInterface;

class RoutePatternParser
{
    public static function parse(string $pattern): array
    {
        $regex = preg_replace_callback('#\((\w+):(\w+)\)#', function ($matches) {
            $param = $matches[1];
            $type = $matches[2];

            $rule = RouteRuleFactory::make($type)->regex();

            return '(?P<' . $param . '>' . $rule . ')';
        }, $pattern);

        $regex = '#^' . $regex . '$#';

        preg_match_all('#\((\w+):\w+\)#', $pattern, $matches);
        $params = $matches[1] ?? [];

        return [
            'regex' => $regex,
            'params' => $params
        ];
    }

}
