<?php

namespace TofuPlugin\Helpers;

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
        $value = $form->getValues()->getFieldValue($field);

        if ($value === null) {
            return null;
        }

        if ($raw) {
            return $value->value;
        }

        if (is_string($value->value)) {
            return esc_html($value->value);
        }
        return $value->value;
    }

    /**
     * Check if the form has error for the specified field
     *
     * @param string $key
     * @param string $field
     * @return boolean
     */
    public static function hasError(string $key, string $field): bool
    {
        $form = self::get($key);
        return $form->getErrors()->hasFieldErrors($field);
    }

    /**
     * Get form error messages of the specified field
     *
     * @param string $key
     * @param string $field
     */
    public static function errors(string $key, string $field): array
    {
        $form = self::get($key);
        return $form->getErrors()->getFieldErrorMessages($field);
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
}
