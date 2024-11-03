<?php
namespace Fernando\PuskerDB\Storage;

final class Storage
{
    private array $cache = [];
    private array $cacheQueue = [];
    private int $maxCacheItems;
    private int $maxMemoryUsage;
    private array $operationQueue = [];
    private readonly string $dirDatabase;
    private ?string $queueFile = null;

    public function __construct(
        string $dirDatabase = __DIR__ . '/../../database/',
        int $maxCacheItems = 1000, // Número máximo de itens em cache
        int $maxMemoryUsage = 100 * 1024 * 1024 // 100MB por padrão
    ) {
        $this->dirDatabase = $dirDatabase;
        $this->maxCacheItems = $maxCacheItems;
        $this->maxMemoryUsage = $maxMemoryUsage;
        $this->queueFile = $dirDatabase . '/.queue';
        $this->loadQueue();
    }

    // Gerenciamento de Memória
    private function checkMemoryUsage(): void
    {
        $currentMemory = memory_get_usage(true);

        if ($currentMemory >= $this->maxMemoryUsage * 0.9) { // 90% do limite
            $this->flushOldestCache(ceil($this->maxCacheItems * 0.3)); // Remove 30% dos itens mais antigos
        }
    }

    private function flushOldestCache(int $count): void
    {
        // Ordena por timestamp (mais antigo primeiro)
        asort($this->cacheQueue);

        $removed = 0;
        foreach ($this->cacheQueue as $key => $timestamp) {
            if ($removed >= $count)
                break;

            // Se o item está marcado como "dirty", salva antes de remover
            if (isset($this->cache[$key]) && ($this->cache[$key]['dirty'] ?? false)) {
                $this->forceSave($this->cache[$key]['file'], $this->cache[$key]['data']);
            }

            unset($this->cache[$key], $this->cacheQueue[$key]);
            $removed++;
        }
    }

    private function addToCache(string $file, array $data, bool $dirty = false): void
    {
        $key = $this->getCacheKey($file);

        // Verifica memória antes de adicionar
        $this->checkMemoryUsage();

        // Se atingiu o limite de itens, remove o mais antigo
        if (count($this->cache) >= $this->maxCacheItems) {
            $oldestKey = array_key_first($this->cacheQueue);
            if ($oldestKey && isset($this->cache[$oldestKey]) && ($this->cache[$oldestKey]['dirty'] ?? false)) {
                $this->forceSave($this->cache[$oldestKey]['file'], $this->cache[$oldestKey]['data']);
            }
            unset($this->cache[$oldestKey], $this->cacheQueue[$oldestKey]);
        }

        $this->cache[$key] = [
            'data' => $data,
            'file' => $file,
            'dirty' => $dirty
        ];

        $this->cacheQueue[$key] = microtime(true);
    }

    private function getCacheKey(string $file): string
    {
        return md5($file);
    }

    private function forceSave(string $file, array $data): bool
    {
        $filePath = "{$this->dirDatabase}{$file}";
        return file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    // Métodos públicos originais otimizados
    public function get(string $file): ?array
    {
        $key = $this->getCacheKey($file);

        // Atualiza timestamp de acesso se estiver em cache
        if (isset($this->cache[$key])) {
            $this->cacheQueue[$key] = microtime(true);
            return $this->cache[$key]['data'];
        }

        if (!file_exists("{$this->dirDatabase}{$file}")) {
            return null;
        }

        $data = json_decode(file_get_contents("{$this->dirDatabase}{$file}"), true);
        if ($data !== null) {
            $this->addToCache($file, $data);
        }

        return $data;
    }

    public function put(string $file, array $data): bool
    {
        $this->addToCache($file, $data, true);
        $this->enqueueOperation('put', ['file' => $file, 'data' => $data]);

        // Processa a fila se a memória estiver ok
        if (memory_get_usage(true) < $this->maxMemoryUsage * 0.8) {
            $this->processQueue();
        }

        return true;
    }

    public function table(string $database, string $file, array $data): bool
    {
        $template = "{$database}/{$file}.json";

        if (file_exists("{$this->dirDatabase}{$template}")) {
            return false;
        }

        $this->addToCache($template, $data, true);
        $this->enqueueOperation('table', [
            'database' => $database,
            'file' => $file,
            'data' => $data
        ]);

        return true;
    }

    // Sistema de Fila
    private function enqueueOperation(string $operation, array $data): void
    {
        $this->operationQueue[] = [
            'operation' => $operation,
            'data' => $data,
            'timestamp' => microtime(true)
        ];

        // Salva a fila apenas se não estiver muito grande
        if (count($this->operationQueue) <= 1000) {
            $this->saveQueue();
        }
    }

    private function processQueue(): void
    {
        foreach ($this->operationQueue as $key => $operation) {
            if (memory_get_usage(true) >= $this->maxMemoryUsage * 0.9) {
                break;
            }

            $success = match ($operation['operation']) {
                'put' => $this->forceSave($operation['data']['file'], $operation['data']['data']),
                'table' => $this->forceSave(
                    "{$operation['data']['database']}/{$operation['data']['file']}.json",
                    $operation['data']['data']
                ),
                default => false
            };

            if ($success) {
                unset($this->operationQueue[$key]);
            }
        }

        // Reseta os índices do array
        $this->operationQueue = array_values($this->operationQueue);
        $this->saveQueue();
    }

    private function saveQueue(): void
    {
        if ($this->queueFile) {
            file_put_contents($this->queueFile, json_encode(array_slice($this->operationQueue, 0, 1000)), LOCK_EX);
        }
    }

    private function loadQueue(): void
    {
        if ($this->queueFile && file_exists($this->queueFile)) {
            $this->operationQueue = json_decode(file_get_contents($this->queueFile), true) ?? [];
        }
    }

    // Métodos de diagnóstico
    public function getCacheStats(): array
    {
        return [
            'items_in_cache' => count($this->cache),
            'memory_usage' => memory_get_usage(true),
            'memory_limit' => $this->maxMemoryUsage,
            'queue_size' => count($this->operationQueue)
        ];
    }

    public function forceFlushCache(): void
    {
        $this->flushOldestCache(count($this->cache));
    }

    // Demais métodos mantidos iguais
    public function isdir(string $database): bool
    {
        return is_dir("{$this->dirDatabase}/{$database}");
    }

    public function makedir(string $database): bool
    {
        if ($this->isdir($database)) {
            return false;
        }
        return mkdir("{$this->dirDatabase}/{$database}", 0777, false);
    }

    public function getAllFolders(): array
    {
        $folders = scandir($this->dirDatabase);
        array_shift($folders);
        array_shift($folders);
        return $folders;
    }

    public function getAllFiles(string $folder): array
    {
        $files = scandir("{$this->dirDatabase}{$folder}");
        array_shift($files);
        array_shift($files);
        return $files;
    }

    public function unsetDatabase(string $database): bool
    {
        if (!$this->isdir($database)) {
            return false;
        }

        // Remove itens do cache relacionados ao database
        foreach ($this->cache as $key => $item) {
            if (strpos($item['file'], $database . '/') === 0) {
                unset($this->cache[$key], $this->cacheQueue[$key]);
            }
        }

        return exec("rm -rfv {$this->dirDatabase}{$database}");
    }

    public function unsetFile(string $database, string $table): bool
    {
        $file = "{$database}/{$table}.json";
        $key = $this->getCacheKey($file);

        if (!$this->get($file)) {
            return false;
        }

        // Remove do cache
        unset($this->cache[$key], $this->cacheQueue[$key]);

        return unlink("{$this->dirDatabase}{$database}/{$table}.json");
    }
}
