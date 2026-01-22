<?php

namespace TofuPlugin\Helpers;

use TofuPlugin\Consts;
use TofuPlugin\Logger;
use TofuPlugin\Structure\UploadedFile;

class Uploader
{
    /**
     * Handle file upload for a specific field name.
     *
     * @param string $name
     * @return ?UploadedFile
     */
    public static function upload(string $name): ?UploadedFile
    {
        if (!isset($_FILES[$name]) || empty($_FILES[$name]['tmp_name'])) {
            return null;
        }
        $filePost = $_FILES[$name];

        // Error check
        if ($filePost['error'] !== UPLOAD_ERR_OK) {
            Logger::error(sprintf(
                'File upload error for field "%s": %s',
                $name,
                Sanitizer::getSanitizedLoggerString($filePost['error'])
            ));
            return null;
        }

        // Load required file for handling uploads
        if (!function_exists('wp_handle_upload')) {
            if (!defined('ABSPATH')) {
                exit;
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Prepare temporary filter to move the specified directory
        $tempDir = self::getTempDir();
        $change_upload_dir = function ($param) use ($tempDir) {
            // Override path and url
            $param['path'] = $tempDir;
            $param['url'] = '';
            $param['subdir'] = Consts::UPLOAD_SUBFOLDER;
            return $param;
        };
        add_filter('upload_dir', $change_upload_dir);

        // Move the uploaded file
        $movedFile = wp_handle_upload($filePost, [
            'test_form' => false,
            'test_size' => false,
            'test_type' => false,
        ]);

        if ($movedFile === null || isset($movedFile['error'])) {
            \wp_die(
                'Failed to upload file',
                sprintf('TOFU File Upload Error: %s', esc_html($movedFile['error'] ?? 'Unknown error')),
                ['response' => 500]
            );
        }

        // Remove temporary filter
        remove_filter('upload_dir', $change_upload_dir);

        return new UploadedFile(
            name: $name,
            fileName: sanitize_text_field(wp_unslash($filePost['name'])),
            mimeType: $movedFile['type'],
            tempName: $movedFile['file'],
            size: (int)$filePost['size'],
        );
    }

    /**
     * Get temporary upload directory, create if not exists
     *
     * @return string
     */
    public static function getTempDir(): string
    {
        $uploadDir = wp_upload_dir();
        $tempDir = $uploadDir['basedir'] . DIRECTORY_SEPARATOR . Consts::UPLOAD_SUBFOLDER;

        if (!is_dir($tempDir)) {
            wp_mkdir_p($tempDir);

            file_put_contents($tempDir . DIRECTORY_SEPARATOR . 'index.php', '<?php // Silence is golden.');

            // Create .htaccess to prevent direct access
            // Apache 2.4 or later: "Require all denied", Apache 2.2 or earlier: "Order Deny,Allow\nDeny from all"
            $htaccessContent  = '<IfModule mod_authz_core.c>' . PHP_EOL;
            $htaccessContent .= '   Require all denied' . PHP_EOL;
            $htaccessContent .= '</IfModule>' . PHP_EOL;
            $htaccessContent .= '<IfModule !mod_authz_core.c>' . PHP_EOL;
            $htaccessContent .= '    Order Deny,Allow' . PHP_EOL;
            $htaccessContent .= '    Deny from all' . PHP_EOL;
            $htaccessContent .= '</IfModule>' . PHP_EOL;
            file_put_contents($tempDir . DIRECTORY_SEPARATOR . '.htaccess', $htaccessContent);
        }

        return $tempDir;
    }

    /**
     * Clear expired uploaded files
     *
     * @return void
     */
    public static function clearExpired(): void
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
                    wp_delete_file($filePath);
                }
            }
        }
    }
}
