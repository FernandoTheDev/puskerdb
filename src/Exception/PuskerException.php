<?php
namespace Fernando\PuskerDB\Exception;

abstract class PuskerException
{
    public static function expect(string $message): void
    {
        echo $message . PHP_EOL;
        if (!PHP_SAPI == 'cli') {
            exit(0);
        }
    }
}
