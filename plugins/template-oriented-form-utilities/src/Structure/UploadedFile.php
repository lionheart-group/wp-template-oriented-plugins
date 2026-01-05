<?php

namespace TofuPlugin\Structure;

use TofuPlugin\Helpers\Uploader;

class UploadedFile
{
    public function __construct(
        /**
         * Input field name
         *
         * @var string
         */
        public readonly string $name,

        /**
         * Original file name
         *
         * @var string
         */
        public readonly string $fileName,

        /**
         * MIME type
         *
         * @var string
         */
        public readonly string $mimeType,

        /**
         * Temporary file name
         *
         * @var string
         */
        public readonly string $tempName,

        /**
         * File size in bytes
         *
         * @var int
         */
        public readonly int $size,
    ) {
    }

    /**
     * Convert the uploaded file data to an associative array.
     *
     * @return array<string, mixed>
     */
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
