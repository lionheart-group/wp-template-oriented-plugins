<?php

namespace TofuPlugin\Helpers;

use TofuPlugin\Logger;
use TofuPlugin\Structure\TurnstileConfig;

class Turnstile
{
    /**
     * Error messages from Turnstile verification
     *
     * @var string[]
     */
    protected static array $errors = [];

    /**
     * Verify the Turnstile token
     *
     * @return bool
     */
    public static function verifyToken(TurnstileConfig $config, string $token): bool
    {
        // Reset errors for this verification attempt to avoid accumulation across calls.
        self::$errors = [];

        $request = array(
            'secret' => $config->secretKey,
            'response' => $token,
        );

        $response = wp_remote_post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            array(
                'body'    => $request,
                'timeout' => 10,
            )
        );

        if (is_wp_error($response)) {
            self::$errors[] = __('Failed to verify Turnstile at this time. Please try again later.', 'template-oriented-form-utilities');
            Logger::error('Turnstile verification request failed', ['errors' => $response->get_error_message()]);
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            self::$errors[] = __('Failed to verify Turnstile at this time. Please try again later.', 'template-oriented-form-utilities');
            Logger::error('Turnstile verification returned non-200 status', ['code' => (string) $status_code]);
            return false;
        }

        $apiResponse = wp_remote_retrieve_body($response);
        $result = [];
        $decoded = json_decode($apiResponse, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $result = $decoded;
        } else {
            self::$errors[] = __('Unexpected response from the Turnstile service. Please try again later.', 'template-oriented-form-utilities');
            return false;
        }

        if (isset($result['error-codes']) && is_array($result['error-codes'])) {
            foreach ($result['error-codes'] as $code) {
                switch ($code) {
                    case 'missing-input-secret':
                        self::$errors[] = __('The secret parameter is missing.', 'template-oriented-form-utilities');
                        break;
                    case 'invalid-input-secret':
                        self::$errors[] = __('The secret parameter is invalid or malformed.', 'template-oriented-form-utilities');
                        break;
                    case 'missing-input-response':
                        self::$errors[] = __('The response parameter is missing.', 'template-oriented-form-utilities');
                        break;
                    case 'invalid-input-response':
                        self::$errors[] = __('The response parameter is invalid or malformed.', 'template-oriented-form-utilities');
                        break;
                    case 'bad-request':
                        self::$errors[] = __('The request is invalid or malformed.', 'template-oriented-form-utilities');
                        break;
                    case 'timeout-or-duplicate':
                        self::$errors[] = __('The response is no longer valid: either is too old or has been used previously.', 'template-oriented-form-utilities');
                        break;
                    case 'internal-error':
                        self::$errors[] = __('An internal error occurred while verifying the response.', 'template-oriented-form-utilities');
                        break;
                    default:
                        // Handle any unexpected or new error codes to avoid silent failures.
                        self::$errors[] = sprintf(
                            /* translators: %s is the error code */ __('An unknown Turnstile error occurred (code: %s). Please try again later.', 'template-oriented-form-utilities'),
                            (string) $code
                        );
                        // Log the unknown error code for diagnostics.
                        Logger::error('Unknown Turnstile error code', ['code' => $code]);
                        break;
                }
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
