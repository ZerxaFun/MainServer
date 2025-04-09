<?php

use Core\Services\DevDumper\DevDumper;
use JetBrains\PhpStorm\NoReturn;

if (!function_exists('dump')) {
    /**
     * @author Nicolas Grekas <p@tchwork.com>
     */
    function dump($var, ...$moreVars) {
        DevDumper::dump($var);

        foreach ($moreVars as $v) {
            DevDumper::dump($v);
        }

        if (1 < func_num_args()) {
            return func_get_args();
        }

        return $var;
    }
}

if (!function_exists('dd')) {
    #[NoReturn] function dd(...$vars): void
    {
        foreach ($vars as $v) {
            DevDumper::dump($v);
        }

        exit(1);
    }
}


