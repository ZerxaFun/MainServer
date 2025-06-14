<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


use JetBrains\PhpStorm\NoReturn;

if (!function_exists('dump')) {
    /**
     * @author Nicolas Grekas <p@tchwork.com>
     */
    function dump($var, ...$moreVars)
    {
        \Core\Services\DevDumper\DevDumper::dump($var);

        foreach ($moreVars as $v) {
            \Core\Services\DevDumper\DevDumper::dump($v);
        }

        if (1 < func_num_args()) {
            return func_get_args();
        }

        return $var;
    }
}

if (!function_exists('dd')) {
    #[NoReturn] function dd(...$vars)
    {
        foreach ($vars as $v) {
            \Core\Services\DevDumper\DevDumper::dump($v);
        }

        exit(1);
    }
}
