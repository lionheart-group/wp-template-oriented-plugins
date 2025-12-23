<?php

namespace TofuPlugin\Helpers;

use TofuPlugin\Consts;
use TofuPlugin\Models\Session as SessionModel;

class Session
{
    const EXPIRY = 3600; // 1 hour

    /**
     * Get unique cookie name for identifying the session
     *
     * @return string
     */
    protected static function getSessionId()
    {
        if (isset($_COOKIE[\TofuPlugin\Consts::SESSION_COOKIE_KEY])) {
            return $_COOKIE[\TofuPlugin\Consts::SESSION_COOKIE_KEY];
        }

        $value = \wp_generate_password( 32, false, false );
        setcookie(Consts::SESSION_COOKIE_KEY, \sanitize_text_field(\wp_unslash($value)), time() + 3600, COOKIEPATH, COOKIE_DOMAIN);

        return $value;
    }

    /**
     * Save session data
     *
     * @param string $form_id
     * @param mixed $data
     * @return void
     */
    public static function save($form_id, $data)
    {
        // Session ID
        $key = self::getSessionId();

        // Expiration time
        $expiration = new \DateTime('now', \wp_timezone());
        $expiration->modify('+' . self::EXPIRY . ' seconds');

        // Encrypt session value
        $encryptedValue = Encryptor::encrypt($data);

        // Insert or update session record in the database
        global $wpdb;
        $current = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . SessionModel::getTableName() . " WHERE form_id = %s AND session_key = %s",
                $form_id,
                $key
            )
        );
        if ($current > 0) {
            $wpdb->update(
                SessionModel::getTableName(),
                [
                    'session_value' => $encryptedValue,
                    'expiration' => $expiration->format('Y-m-d H:i:s'),
                ],
                [
                    'form_id' => $form_id,
                    'session_key' => $key,
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%s',
                    '%s',
                ]
            );
        } else {
            $wpdb->insert(
                SessionModel::getTableName(),
                [
                    'form_id' => $form_id,
                    'session_key' => $key,
                    'session_value' => $encryptedValue,
                    'expiration' => $expiration->format('Y-m-d H:i:s'),
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );
        }
    }

    /**
     * Get session data
     *
     * @param string $form_id
     * @return mixed|null
     */
    public static function get($form_id)
    {
        // Session ID
        $key = self::getSessionId();

        // Retrieve session record from the database
        global $wpdb;
        $table = SessionModel::getTableName();
        $query = $wpdb->prepare(
            "SELECT session_value FROM {$table} WHERE form_id = %s AND session_key = %s AND expiration > %s",
            $form_id,
            $key,
            \current_time('mysql')
        );
        $row = $wpdb->get_var($query);

        if ($row) {
            // Decrypt session value
            return Encryptor::decrypt($row);
        }

        return null;
    }
}
