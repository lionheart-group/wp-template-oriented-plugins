<?php

namespace TofuPlugin\Helpers;

use Ramsey\Uuid\Uuid;
use TofuPlugin\Consts;
use \TofuPlugin\Models\Form as FormModel;
use TofuPlugin\Structure\FormConfig;

class Form
{
    /**
     * Form list
     *
     * @var FormModel[]
     */
    protected static $forms = [];

    /**
     * Register a new form
     *
     * @param FormConfig $config
     * @return void
     */
    public static function register(FormConfig $config)
    {
        // Check if the form is already registered
        $form = self::get($config->key, false);
        if ($form) {
            wp_die(
                sprintf('Form with key "%s" is already registered.', $config->key),
                'TOFU Form Registration Error',
                ['response' => 500]
            );

            return;
        }

        self::$forms[] = new \TofuPlugin\Models\Form($config);
    }

    /**
     * Get form by key
     *
     * @return FormModel|false
     */
    public static function get(string $key, bool $isStrict = true): FormModel | false
    {
        foreach (self::$forms as $form) {
            if ($form->getKey() === $key) {
                return $form;
            }
        }

        if ($isStrict) {
            wp_die(
                sprintf('Form with key "%s" is not registered.', $key),
                'TOFU Form Action Error',
                ['response' => 500]
            );
            exit;
        } else {
            return false;
        }
    }

    /**
     * Get action URL for the form
     *
     * @param string $key
     * @return string
     */
    public static function action(string $key, string $action)
    {
        $form = self::get($key);
        return $form->getActionUrl($action);
    }

    /**
     * Get form value
     *
     * @param string $key
     * @param string $field
     */
    public static function value(string $key, string $field, bool $raw = false): mixed
    {
        $form = self::get($key);
        $value = $form->getValue($field);

        if ($raw) {
            return $value;
        }

        if (is_string($value)) {
            return esc_html($value);
        }
        return $value;
    }

    /**
     * Generate nonce field
     *
     * @return string
     */
    public static function generateNonceField(string $key, string $action): void
    {
        $nonceKey = sprintf(Consts::NONCE_FORMAT, $key);
        wp_nonce_field($action, $nonceKey, false, true);
    }

    /**
     * Verify nonce field
     *
     * @return bool
     */
    public static function verifyNonceField(string $key, string $action): bool
    {
        $nonceKey = sprintf(Consts::NONCE_FORMAT, $key);
        $nonce = $_POST[$nonceKey] ?? null;

        if (!isset($nonce)) {
            return false;
        }

        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Get unique cookie name for identifying the session
     *
     * @return string
     */
    public static function getSessionCookieValue(): string
    {
        $cookieValue = $_COOKIE[Consts::SESSION_COOKIE_KEY] ?? null;

        if (!isset($cookieValue)) {
            $cookieValue = Uuid::uuid4()->toString();
            setcookie(Consts::SESSION_COOKIE_KEY, $cookieValue, time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
        }

        return $cookieValue;
    }

    public static function setError($errors)
    {
        set_transient('tofu_error', base64_encode(json_encode($errors)), HOUR_IN_SECONDS);
    }

    public static function getError()
    {
        $error = get_transient('tofu_error');
        if ($error) {
            $error = json_decode(base64_decode($error), true);
            delete_transient('tofu_error');
            return $error;
        }
        return null;
    }
}
