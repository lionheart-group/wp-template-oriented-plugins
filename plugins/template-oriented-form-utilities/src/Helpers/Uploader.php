<?php

namespace TofuPlugin\Helpers;

use TofuPlugin\Consts;
use TofuPlugin\Logger;
use TofuPlugin\Structure\UploadedFile;

class Uploader
{
    public static function upload($name): ?UploadedFile
    {
        if (!isset($_FILES[$name]) || empty($_FILES[$name]['tmp_name'])) {
            return null;
        }
        $filePost = $_FILES[$name];

        // Error check
        if ($filePost['error'] !== UPLOAD_ERR_OK) {
            Logger::error(sprintf('File upload error for field "%s": %s', $name, $filePost['error']));
            return null;
        }

        // Mime type and size
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePost['tmp_name']);
        $size = $filePost['size'];

        // Rename and move the uploaded file to a temporary directory
        $fileName = $filePost['name'];
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $tempName = wp_generate_password(32, false) . '.' . $extension;
        $tempPath = self::getTempFilePath($tempName);

        // Move the uploaded file
        if (!move_uploaded_file($filePost['tmp_name'], $tempPath)) {
            Logger::error(sprintf('Failed to move uploaded file for field "%s" to temporary directory.', $name));
            return null;
        }

        return new UploadedFile(
            name: $name,
            fileName: $fileName,
            mimeType: $mimeType,
            tempName: $tempName,
            size: $size,
        );
    }

    public static function getTempDir(): string
    {
        $uploadDir = wp_upload_dir();
        $tempDir = $uploadDir['basedir'] . DIRECTORY_SEPARATOR . Consts::UPLOAD_SUBFOLDER;

        if (!is_dir($tempDir)) {
            wp_mkdir_p($tempDir);

            file_put_contents($tempDir . DIRECTORY_SEPARATOR . 'index.php', '<?php // Silence is golden.');
            file_put_contents($tempDir . DIRECTORY_SEPARATOR . '.htaccess', "Order Deny,Allow\nDeny from all");
        }

        return $tempDir;
    }

    public static function getTempFilePath(string $tempName): string
    {
        $tempDir = self::getTempDir();
        return $tempDir . DIRECTORY_SEPARATOR . $tempName;
    }

    /**
     * Clear expired uploaded files
     *
     * @return void
     */
    public static function clearExpired()
    {
        $tempDir = self::getTempDir();
        if (!is_dir($tempDir)) {
            return;
        }

        $files = scandir($tempDir);
        if ($files === false) {
            Logger::error(sprintf('Failed to scan temporary upload directory for clearing expired files: %s', $tempDir));
            return;
        }

        $now = time();
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || in_array($file, ['index.php', '.htaccess'], true)) {
                continue;
            }

            $filePath = $tempDir . DIRECTORY_SEPARATOR . $file;
            if (is_file($filePath)) {
                $fileModTime = filemtime($filePath);
                if ($fileModTime !== false && ($now - $fileModTime) > Consts::SESSION_EXPIRY) {
                    unlink($filePath);
                }
            }
        }
    }
}
