<?php

namespace TofuPlugin\Structure;

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

        /**
         * Unique ID for the uploaded file
         *
         * @var string
         */
        protected ?string $id = null,
    ) {
        // Generate a unique ID for the uploaded file
        if ($this->id === null) {
            $this->id = bin2hex(random_bytes(16));
        }
    }

    /**
     * Get the unique ID of the uploaded file.
     *
     * @return ?string
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Convert the uploaded file data to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fileName' => $this->fileName,
            'mimeType' => $this->mimeType,
            'tempName' => $this->tempName,
            'size' => $this->size,
        ];
    }
}
