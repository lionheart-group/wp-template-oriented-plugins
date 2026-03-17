<?php

namespace TofuPlugin\Helpers;

use TofuPlugin\Consts;
use TofuPlugin\Models\Session as SessionModel;

class Session
{
    /**
     * Whether CORS mode is active (set by RestEndpoint for cross-origin requests).
     *
     * When true, the session cookie is issued with SameSite=None; Secure
     * so that cross-domain AJAX with `credentials: 'include'` works.
     *
     * @var bool
     */
    protected static bool $corsMode = false;

    /**
     * Enable CORS mode for the session cookie.
     *
     * Must be called before any session read/write when handling a
     * cross-origin REST request.
     *
     * @return void
     */
    public static function enableCors(): void
    {
        static::$corsMode = true;
    }

    /**
     * Get unique cookie name for identifying the session
     *
     * @return string
     */
    protected static function getSessionId(): string
    {
        if (isset($_COOKIE[\TofuPlugin\Consts::SESSION_COOKIE_KEY])) {
            $value = $_COOKIE[\TofuPlugin\Consts::SESSION_COOKIE_KEY];
        } else {
            $value = \wp_generate_password( 32, false, false );
        }
        $value = \sanitize_text_field(\wp_unslash($value));

        setcookie(
            Consts::SESSION_COOKIE_KEY,
            $value,
            array_filter([
                'expires'  => time() + Consts::SESSION_EXPIRY,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => \is_ssl() || static::$corsMode,
                'httponly' => true,
                'samesite' => static::$corsMode ? 'None' : 'Lax',
            ], fn ($v) => $v !== null)
        );

        return $value;
    }

    /**
     * Save session data
     *
     * @param string $form_id
     * @param mixed $data
     * @return void
     */
    public static function save(string $form_id, $data): void
    {
        // Session ID
        $key = self::getSessionId();

        // Expiration time
        $expiration = new \DateTime('now', \wp_timezone());
        $expiration->modify('+' . Consts::SESSION_EXPIRY . ' seconds');

        // Encrypt session value
        $encryptedValue = Encryptor::encrypt($data);

        // Insert or update session record in the database
        $isExist = SessionModel::exists($form_id, $key);
        if ($isExist) {
            SessionModel::update(
                [
                    'session_value' => $encryptedValue,
                    'expiration' => $expiration,
                ],
                [
                    'form_id' => $form_id,
                    'session_key' => $key,
                ]
            );
        } else {
            SessionModel::insert(
                [
                    'form_id' => $form_id,
                    'session_key' => $key,
                    'session_value' => $encryptedValue,
                    'expiration' => $expiration,
                ]
            );
        }
    }

    /**
     * Get session data
     *
     * @param string $form_id
     * @return ?mixed
     */
    public static function get(string $form_id): mixed
    {
        // Session ID
        $key = self::getSessionId();

        // Retrieve session record from the database
        $row = SessionModel::get($form_id, $key);

        if ($row) {
            // Decrypt session value
            return Encryptor::decrypt($row);
        }

        return null;
    }

    /**
     * Clear session data
     *
     * @param string $form_id
     * @return void
     */
    public static function clear(string $form_id): void
    {
        // Session ID
        $key = self::getSessionId();

        // Delete session record from the database
        SessionModel::delete([
            'form_id' => $form_id,
            'session_key' => $key,
        ]);
    }

    /**
     * Clear expired sessions
     *
     * @return void
     */
    public static function clearExpired(): void
    {
        SessionModel::clearExpired();
    }
}
