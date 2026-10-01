<?php

namespace TofuPlugin\Models;

use TofuPlugin\Structure\UploadedFile;

class UploadedFileCollection
{
    /**
     * File collection
     *
     * @var array<string, UploadedFile>
     */
    private array $files = [];

    /**
     * Get all files
     *
     * @return array<string, UploadedFile>
     */
    public function getAllFiles(): array
    {
        return $this->files;
    }

    /**
     * Add a file
     *
     * @param UploadedFile $fileData
     * @return void
     */
    public function addFile(UploadedFile $fileData): void
    {
        $this->files[$fileData->name] = $fileData;
    }

    /**
     * Check if a specific uploaded file exists by name.
     *
     * @param string $name
     * @return bool
     */
    public function hasFile(string $name): bool
    {
        return isset($this->files[$name]) && !empty($this->files[$name]);
    }

    /**
     * Get files by field name
     *
     * @param string $name
     * @return ?UploadedFile
     */
    public function getFile(string $name): ?UploadedFile
    {
        return $this->files[$name] ?? null;
    }

    /**
     * Get file by unique ID
     *
     * @param string $id
     * @return ?UploadedFile
     */
    public function getFileById(string $id): ?UploadedFile
    {
        foreach ($this->files as $file) {
            if ($file->getId() === $id) {
                return $file;
            }
        }
        return null;
    }

    /**
     * Remove a file by field name
     *
     * @param string $name
     * @return void
     */
    public function removeFile(string $name): void
    {
        if (isset($this->files[$name])) {
            unset($this->files[$name]);
        }
    }

    /**
     * Get files as array
     *
     * @return array<string, array>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->files as $name => $file) {
            $result[$name] = $file->toArray();
        }
        return $result;
    }
}
