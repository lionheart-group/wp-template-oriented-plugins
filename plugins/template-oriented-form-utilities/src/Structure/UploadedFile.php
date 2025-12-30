<?php

namespace TofuPlugin\Structure;

class UploadedFile
{
    public function __construct(
        public readonly string $name,
        public readonly string $fileName,
        public readonly string $mimeType,
        public readonly string $tempPath,
        public readonly int $size,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'fileName' => $this->fileName,
            'mimeType' => $this->mimeType,
            'tempPath' => $this->tempPath,
            'size' => $this->size,
        ];
    }
}
