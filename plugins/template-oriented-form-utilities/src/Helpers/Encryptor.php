<?php

namespace TofuPlugin\Helpers;

class Encryptor
{
    protected const METHOD = 'AES-256-CBC';

    /**
     * Get encryption key
     *
     * @return string
     */
    protected static function getKey(): string
    {
        if (!defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY')) {
            \wp_die(
                'AUTH_KEY is not defined. Encryption cannot proceed.',
                'TOFU Encryption Error',
                ['response' => 500]
            );
        }

        $source_key = AUTH_KEY . SECURE_AUTH_KEY;
        return substr(hash('sha256', $source_key, true), 0, 32);
    }

    /**
     * Generate encrypted string from data
     *
     * @param mixed $data
     * @return string
     */
    public static function encrypt($data): string
    {
        // Generate a key for encryption
        $key_for_openssl = self::getKey();

        // Generate an initialization vector
        $iv_length = openssl_cipher_iv_length(self::METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);

        // Serialize the data
        $plaintext = serialize( $data );

        // Encrypt the data
        $encrypted = openssl_encrypt( $plaintext, 'AES-256-CBC', $key_for_openssl, 0, $iv );

        // Return the encrypted data with the IV for decryption
        return base64_encode( $iv . $encrypted );
    }

    /**
     * Decrypt string to original data
     *
     * @param string $encryptedData
     * @return mixed|false
     */
    public static function decrypt(string $encryptedData)
    {
        // Generate a key for decryption
        $key_for_openssl = self::getKey();

        // Decode the base64 encoded data
        $data = base64_decode( $encryptedData );

        // Extract the IV and the encrypted data
        $iv_length = openssl_cipher_iv_length(self::METHOD);
        $iv = substr( $data, 0, $iv_length );
        $encrypted = substr( $data, $iv_length );

        // Decrypt the data
        $decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $key_for_openssl, 0, $iv );

        // Check if decryption was successful
        if ($decrypted === false) {
            return false;
        }

        // Unserialize the data to get the original value
        return unserialize( $decrypted );
    }
}
