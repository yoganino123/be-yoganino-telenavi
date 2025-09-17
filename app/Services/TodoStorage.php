<?php
namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use RuntimeException;

class TodoStorage
{
    protected string $path;

    public function __construct()
    {
        // file di storage/app/todos.json
        $this->path = storage_path('app/todos.json');
        if (!file_exists($this->path)) {
            // buat kalau belum ada
            file_put_contents($this->path, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * @return array
     */
    public function load(): array
    {
        $content = @file_get_contents($this->path);
        if ($content === false) {
            throw new RuntimeException("Unable to read todos file");
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array $items
     * @return void
     */
    public function save(array $items): void
    {
        $json = json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        // LOCK_EX agar atomic
        if (file_put_contents($this->path, $json, LOCK_EX) === false) {
            throw new RuntimeException("Unable to write todos file");
        }
    }

    /**
     * Append new item and return saved item.
     */
    public function append(array $item): array
    {
        $items = $this->load();
        $items[] = $item;
        $this->save($items);
        return $item;
    }
}
