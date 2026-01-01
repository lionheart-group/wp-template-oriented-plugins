<?php

namespace TofuPlugin\Structure;

use TofuPlugin\Helpers\Uploader;

class UploadedFile
{
    public function __construct(
        public readonly string $name,
        public readonly string $fileName,
        public readonly string $mimeType,
        public readonly string $tempName,
        public readonly int $size,
    ) {
        $tempPath = Uploader::getTempFilePath($this->tempName);

        if (!file_exists($tempPath)) {
            throw new \RuntimeException(sprintf('Temporary file does not exist: %s', esc_html($tempPath)));
        }
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'fileName' => $this->fileName,
            'mimeType' => $this->mimeType,
            'tempName' => $this->tempName,
            'size' => $this->size,
        ];
    }
}
