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
     * The session key this browser already carries, if any.
     *
     * Read-only on purpose. Reading a session must never hand out a cookie:
     * Form::register() runs on `init` for every request, so issuing one here
     * would put a Set-Cookie on every page of the site — including pages
     * with no form and the admin — which is enough to make most full-page
     * caches (Varnish, nginx fastcgi_cache, WP Rocket) stop serving cached
     * responses altogether.
     *
     * @return ?string Null when the browser has no session cookie.
     */
    protected static function readSessionId(): ?string
    {
        if (!isset($_COOKIE[Consts::SESSION_COOKIE_KEY])) {
            return null;
        }

        $value = \sanitize_text_field(\wp_unslash($_COOKIE[Consts::SESSION_COOKIE_KEY]));

        return $value === '' ? null : $value;
    }

    /**
     * The session key to store data under, minting and sending one if the
     * browser does not have it yet.
     *
     * The only place a session cookie is issued. It is called when session
     * data is actually being persisted, which is the first moment there is
     * anything for the key to point at.
     *
     * @return string
     */
    protected static function issueSessionId(): string
    {
        $value = self::readSessionId() ?? \wp_generate_password(32, false, false);

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
        // Storing data is the point at which a key becomes worth handing
        // out, so this is the one path that issues the cookie.
        $key = self::issueSessionId();

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
        $key = self::readSessionId();

        // No cookie means no session can belong to this browser, so there is
        // nothing to look up — the old code minted a fresh key here and then
        // queried for a row it could never match.
        if ($key === null) {
            return null;
        }

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
        $key = self::readSessionId();

        // Nothing to clear, and no reason to hand out a cookie on the way out.
        if ($key === null) {
            return;
        }

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
