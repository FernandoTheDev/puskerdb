<?php

namespace Fernando\PuskerDB\Pusker;

use Fernando\PuskerDB\Lexer\Lexer;
use Fernando\PuskerDB\Parser\Parser;
use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Exception\PuskerException;
use Fernando\PuskerDB\Storage\Storage;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Throwable;

final class Pusker
{
    private const MINIMUM_SQL_LENGTH = 3;

    private Parser $parser;
    private Lexer $lexer;
    private Runtime $runtime;
    private array $data = [];
    private int $linesAffected = 0;
    private string $querySql = '';
    private ?LoggerInterface $logger;
    private array $queryHistory = [];
    private float $lastQueryTime = 0.0;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly ?string $database = null,
        ?LoggerInterface $logger = null
    ) {
        if ($database !== null) {
            if (empty(trim($database))) {
                throw new InvalidArgumentException('Database name cannot be empty');
            }
        }

        $this->runtime = new Runtime(new Storage(), $this);
        $this->logger = $logger;
        $this->initializeDatabase();
    }

    /**
     * @throws PuskerException
     */
    private function initializeDatabase(): void
    {
        try {
            if ($this->database === null) {
                return;
            }
            $this->runtime->setDatabase($this->database);
        } catch (Throwable $e) {
            $this->logError('Failed to initialize database', $e);
            throw new PuskerException(
                message: "Failed to initialize database: {$e->getMessage()}",
                context: [],
                code: $e->getCode()
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function query(string $sql): self
    {
        $this->validateQuery($sql);

        $this->querySql = $sql;
        $startTime = microtime(true);

        try {
            $this->lexer = new Lexer($sql);
            $this->parser = new Parser($this->lexer->getTokens());

            $this->logQuery($sql);
            $this->updateQueryHistory($sql);

        } catch (Throwable $e) {
            $this->logError('Query parsing failed', $e);
            throw new InvalidArgumentException(
                "Invalid SQL query: {$e->getMessage()}",
                0,
                $e
            );
        } finally {
            $this->lastQueryTime = microtime(true) - $startTime;
        }

        return $this;
    }

    private function validateQuery(string $sql): void
    {
        $trimmedSql = trim($sql);
        if (empty($trimmedSql)) {
            throw new InvalidArgumentException('SQL query cannot be empty');
        }

        if (strlen($trimmedSql) < self::MINIMUM_SQL_LENGTH) {
            throw new InvalidArgumentException(
                'SQL query is too short to be valid'
            );
        }
    }

    public function execute(): bool
    {
        if (empty($this->querySql)) {
            $this->logError('No query to execute');
            return false;
        }

        try {
            $startTime = microtime(true);
            $ast = $this->parser->parse();

            $this->data = $this->runtime->run($ast);
            $this->updateExecutionMetrics($startTime);

            return true;

        } catch (Throwable $e) {
            $this->logError('Query execution failed', $e);
            $this->data = [];
            return false;
        }
    }

    private function updateExecutionMetrics(float $startTime): void
    {
        $this->lastQueryTime = microtime(true) - $startTime;
        $this->linesAffected = count($this->data);
    }

    public function fetch(): array|false
    {
        if (empty($this->data)) {
            return false;
        }

        $firstResult = $this->data[0] ?? false;

        if ($firstResult === false) {
            $this->logWarning('No results found in fetch operation');
        }

        return $firstResult;
    }

    public function fetchAll(): array|false
    {
        if (empty($this->data)) {
            $this->logWarning('No results found in fetchAll operation');
            return false;
        }

        return $this->data;
    }

    public function getLinesAffected(): int
    {
        return $this->linesAffected;
    }

    public function getAST(): array
    {
        try {
            return $this->parser->parse();
        } catch (Throwable $e) {
            $this->logError('Failed to generate AST', $e);
            return [];
        }
    }

    public function getLastQueryTime(): float
    {
        return $this->lastQueryTime;
    }

    public function getQueryHistory(): array
    {
        return $this->queryHistory;
    }

    public function clearQueryHistory(): void
    {
        $this->queryHistory = [];
    }

    private function updateQueryHistory(string $sql): void
    {
        $this->queryHistory[] = [
            'sql' => $sql,
            'timestamp' => microtime(true),
            'datetime' => date('Y-m-d H:i:s')
        ];
    }

    private function logQuery(string $sql): void
    {
        if ($this->logger) {
            $this->logger->info('Executing query', [
                'sql' => $sql,
                'database' => $this->database
            ]);
        }
    }

    private function logError(string $message, ?Throwable $exception = null): void
    {
        if ($this->logger) {
            $context = [
                'database' => $this->database,
                'query' => $this->querySql
            ];

            if ($exception) {
                $context['exception'] = [
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine()
                ];
            }

            $this->logger->error($message, $context);
        }
    }

    private function logWarning(string $message): void
    {
        if ($this->logger) {
            $this->logger->warning($message, [
                'database' => $this->database,
                'query' => $this->querySql
            ]);
        }
    }

    /**
     * For testing/debugging purposes
     */
    public function getLastError(): ?array
    {
        $lastEntry = end($this->queryHistory);
        return $lastEntry['error'] ?? null;
    }

    public function reset(): void
    {
        $this->data = [];
        $this->linesAffected = 0;
        $this->querySql = '';
        $this->lastQueryTime = 0.0;
    }
}
