<?php
namespace Fernando\PuskerDB\Utils;

final class ClockUtils
{
    private const MESSAGE = 'Finished in (%s) ms';

    public function __construct(
        protected int|float $microtime
    ) {
    }
    public function now(): void
    {
        $this->microtime = microtime(true);
    }

    public function finish(): void
    {
        echo sprintf(self::MESSAGE, number_format(microtime(true) - $this->microtime, 2, '.')) . PHP_EOL;
    }
}
