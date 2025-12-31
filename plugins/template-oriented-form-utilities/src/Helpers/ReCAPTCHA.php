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
            'response' =>  $token,
        );

        $context = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($request),
            ),
        );
        $context = stream_context_create($context);

        $apiResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        $result = [];

        if ($apiResponse === false) {
            self::$errors[] = __('Failed to verify reCAPTCHA at this time. Please try again later.', Consts::TEXT_DOMAIN);
            return false;
        }

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
                        Logger::error('Unknown reCAPTCHA error code', $code);
                        break;
                }
            }
        }

        if ($config->threshold > 0) {
            if (!isset($result['score'])) {
                self::$errors[] = __('Failed to verify reCAPTCHA score. Please try again later.', Consts::TEXT_DOMAIN);
            } elseif ($result['score'] < $config->threshold) {
                self::$errors[] = __('Failed to submit, please try again after some time or contact us by phone.', Consts::TEXT_DOMAIN);
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
