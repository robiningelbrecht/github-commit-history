<?php

namespace App\Infrastructure\Twig;

class StrRepeatTwigExtension
{
    public static function doRepeat(string $char, int $times): string
    {
        return str_repeat($char, $times);
    }
}
