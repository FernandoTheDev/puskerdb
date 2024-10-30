<?php
namespace Fernando\PuskerDB\Storage;

final readonly class Storage
{
    public function __construct(
        private string $dirDatabase = __DIR__ . '/../../database/'
    ) {
    }

    public function get(string $file): ?array
    {
        if (!file_exists("{$this->dirDatabase}{$file}")) {
            return null;
        }
        return json_decode(file_get_contents("{$this->dirDatabase}{$file}"), true);
    }

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

    public function table(string $database, string $file, array $data): bool
    {
        $template = "{$this->dirDatabase}{$database}/{$file}.json";
        if (file_exists($template)) {
            return false;
        }
        return file_put_contents($template, json_encode($data, JSON_PRETTY_PRINT));
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
        return exec("rm -rfv {$this->dirDatabase}{$database}");
    }

    public function unsetFile(string $database, string $table): bool
    {
        if (!$this->get("{$database}/{$table}.json")) {
            return false;
        }
        // var_dump("{$this->dirDatabase}{$database}/{$table}.json");
        return unlink("{$this->dirDatabase}{$database}/{$table}.json");
    }

    public function put(string $file, array $data): bool
    {
        $filePath = "{$this->dirDatabase}{$file}";

        if (!file_exists($filePath)) {
            return false;
        }

        return file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }
}
