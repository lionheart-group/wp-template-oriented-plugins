<?php

namespace TofuPlugin\Helpers;

use TofuPlugin\Consts;
use TofuPlugin\Logger;
use TofuPlugin\Structure\ReCAPTCHAConfig;

class ReCAPTCHA
{
    /**
     * Error messages from reCAPTCHA verification
     *
     * @var string[]
     */
    protected static array $errors = [];

    /**
     * Verify the reCAPTCHA token
     *
     * @return bool
     */
    public static function verifyToken(ReCAPTCHAConfig $config, string $token): bool
    {
        // Reset errors for this verification attempt to avoid accumulation across calls.
        self::$errors = [];

        $request = array(
            'secret' => $config->secretKey,
            'response' => $token,
        );

        $response = wp_remote_post(
            'https://www.google.com/recaptcha/api/siteverify',
            array(
                'body'    => $request,
                'timeout' => 5,
            )
        );

        if (is_wp_error($response)) {
            self::$errors[] = __('Failed to verify reCAPTCHA at this time. Please try again later.', Consts::TEXT_DOMAIN);
            Logger::error('reCAPTCHA verification request failed', ['errors' => $response->get_error_message()]);
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            self::$errors[] = __('Failed to verify reCAPTCHA at this time. Please try again later.', Consts::TEXT_DOMAIN);
            Logger::error('reCAPTCHA verification returned non-200 status', ['code' => (string) $status_code]);
            return false;
        }

        $apiResponse = wp_remote_retrieve_body($response);
        $result = [];
        $decoded = json_decode($apiResponse, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $result = $decoded;
        } else {
            self::$errors[] = __('Unexpected response from the reCAPTCHA service. Please try again later.', Consts::TEXT_DOMAIN);
            return false;
        }

        if (isset($result['error-codes']) && is_array($result['error-codes'])) {
            foreach ($result['error-codes'] as $code) {
                switch ($code) {
                    case 'missing-input-secret':
                        self::$errors[] = __('The secret parameter is missing.', Consts::TEXT_DOMAIN);
                        break;
                    case 'invalid-input-secret':
                        self::$errors[] = __('The secret parameter is invalid or malformed.', Consts::TEXT_DOMAIN);
                        break;
                    case 'missing-input-response':
                        self::$errors[] = __('The response parameter is missing.', Consts::TEXT_DOMAIN);
                        break;
                    case 'invalid-input-response':
                        self::$errors[] = __('The response parameter is invalid or malformed.', Consts::TEXT_DOMAIN);
                        break;
                    case 'bad-request':
                        self::$errors[] = __('The request is invalid or malformed.', Consts::TEXT_DOMAIN);
                        break;
                    case 'timeout-or-duplicate':
                        self::$errors[] = __('The response is no longer valid: either is too old or has been used previously.', Consts::TEXT_DOMAIN);
                        break;
                    default:
                        // Handle any unexpected or new error codes to avoid silent failures.
                        self::$errors[] = sprintf(
                            __('An unknown reCAPTCHA error occurred (code: %s). Please try again later.', Consts::TEXT_DOMAIN),
                            (string) $code
                        );
                        // Log the unknown error code for diagnostics.
                        Logger::error('Unknown reCAPTCHA error code', ['code' => $code]);
                        break;
                }
            }
        }

        if (!isset($result['score'])) {
            self::$errors[] = __('Failed to verify reCAPTCHA score. Please try again later.', Consts::TEXT_DOMAIN);
        } elseif ($config->threshold > 0) {
            if ($result['score'] < $config->threshold) {
                self::$errors[] = __('Verification failed. Please try again later.', Consts::TEXT_DOMAIN);
            }
        }

        return isset($result['success']) && $result['success'] === true && empty(self::$errors);
    }

    /**
     * Get error messages from reCAPTCHA verification
     *
     * @return string[]
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }
}
