<?php
namespace Fernando\PuskerDB\Cli;

final class PrinterCli
{
    public static function expect(string $message): void
    {
        echo $message . PHP_EOL;
        if (!PHP_SAPI == 'cli') {
            exit(0);
        }
    }
}
