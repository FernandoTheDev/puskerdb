<?php

declare(strict_types=1);

namespace Fernando\PuskerDB\Exception;

use Throwable;

/**
 * Custom exception class for PuskerDB errors
 */
class PuskerException extends \Exception
{
    /**
     * @var array Additional context/metadata about the exception
     */
    private array $context = [];

    /**
     * @param string $message The exception message
     * @param array $context Additional context about the error
     * @param int $code The exception code
     * @param Throwable|null $previous The previous throwable used for exception chaining
     */
    public function __construct(
        string $message,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;

        if (PHP_SAPI === 'cli') {
            $this->outputCliMessage();
        }
    }

    /**
     * Helper method to format and output CLI messages
     */
    private function outputCliMessage(): void
    {
        $output = sprintf(
            "\033[31mError:\033[0m %s\n",
            $this->getMessage()
        );

        if (!empty($this->context)) {
            $output .= sprintf(
                "\033[33mContext:\033[0m %s\n",
                json_encode($this->context, JSON_PRETTY_PRINT)
            );
        }

        if ($this->getPrevious()) {
            $output .= sprintf(
                "\033[33mCaused by:\033[0m %s\n",
                $this->getPrevious()->getMessage()
            );
        }

        echo $output;
    }

    /**
     * Static factory method to throw an exception with context
     *
     * @param string $message The exception message
     * @param array $context Additional context about the error
     * @param int $code The exception code
     * @throws self
     */
    public static function throw(
        string $message,
        array $context = [],
        int $code = 0
    ): never {
        throw new static($message, $context, $code);
    }

    /**
     * Get the exception context
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Convert the exception to a string representation
     * 
     * @return string
     */
    public function __toString(): string
    {
        $output = parent::__toString();

        if (!empty($this->context)) {
            $output .= "\nContext: " . json_encode($this->context, JSON_PRETTY_PRINT);
        }

        return $output;
    }
}
