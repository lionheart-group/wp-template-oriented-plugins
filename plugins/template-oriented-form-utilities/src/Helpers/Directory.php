<?php

namespace TofuPlugin\Helpers;

class Directory
{
    /**
     * Create an upload subdirectory under the WordPress uploads directory.
     *
     * @param string $subfolder Subdirectory name
     * @param bool $isPrivate Whether to restrict direct access (default: true)
     * @return string The created directory path or false on failure
     * @throws \Exception
     */
    public static function createUploadSubDirectory(string $subfolder, bool $isPrivate = true): string
    {
        $uploadDir = wp_upload_dir();
        if (isset($uploadDir['error']) && $uploadDir['error'] !== false) {
            throw new \Exception('Upload directory is not writable: ' . $uploadDir['error']);
        }

        // Allowable characters: alphanumeric, hyphen, underscore
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $subfolder)) {
            throw new \Exception('Invalid subfolder name: ' . $subfolder);
        }

        // Check length (to prevent OS limitations)
        if (strlen($subfolder) > 255 || strlen($subfolder) === 0) {
            throw new \Exception('Subfolder name length is invalid: ' . $subfolder);
        }

        $tempDir = $uploadDir['basedir'] . DIRECTORY_SEPARATOR . $subfolder;
        if (!is_dir($tempDir)) {
            // If not exists, create directory
            wp_mkdir_p($tempDir);

            if ($isPrivate) {
                // Create index.php to prevent directory listing
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
        }

        return $tempDir;
    }
}
