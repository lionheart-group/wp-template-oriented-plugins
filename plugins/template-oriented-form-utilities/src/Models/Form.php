<?php

namespace TofuPlugin\Models;

use TofuPlugin\Consts;
use TofuPlugin\Helpers\Form as FormHelper;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Helpers\Validate;

class Form
{
    /**
     * Input values.
     *
     * @var array
     */
    protected $values = [];

    /**
     * The key for Transient API.
     *
     * @var string
     */
    protected $transientKey;

    /**
     * Form constructor.
     */
    public function __construct(
        /**
         * Configuration for the form.
         *
         * @var FormConfig
         */
        protected readonly FormConfig $config,
    )
    {
        $this->transientKey = sprintf(
            Consts::TRANSIENT_FORMAT,
            $this->config->key,
            FormHelper::getSessionCookieValue()
        );

        // Load the session values from Transient API
        $sessionValues = json_decode(base64_decode(get_transient($this->transientKey)), true);
        if ($sessionValues) {
            $this->values = $sessionValues;
        }
    }

    /**
     * Get the form key.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->config->key;
    }

    /**
     * Get the form name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->config->name;
    }

    /**
     * Get the values.
     *
     * @return array
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Get the specific value.
     *
     * @param string $key The key of the value to get.
     * @return mixed The value associated with the key, or null if not found.
     */
    public function getValue(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    /**
     * Store the values in the Transient API.
     */
    public function storeValues(): void
    {
        set_transient($this->transientKey, base64_encode(json_encode($this->values)), HOUR_IN_SECONDS);
    }

    /**
     * Get the form action URL.
     *
     * @return string
     */
    public function getActionUrl(string $action): string
    {
        $key = json_encode([
            'key' => $this->config->key,
            'action' => $action,
        ]);
        $key = base64_encode($key);

        $url = home_url('/');
        $query = http_build_query([
            Consts::QUERY_KEY => $key,
        ]);

        return $url . (strpos($url, '?') === false ? '?' : '&') . $query;
    }

    /**
     * Trigger the form action.
     *
     * @param string $action The action to perform.
     * @return void
     */
    public function action(string $action): void
    {
        // Check if the action is valid
        if (!in_array($action, ['input', 'confirm'])) {
            wp_die('Invalid action.', 'TOFU Form Action Error', ['response' => 400]);
        }

        // Call the action method
        $method = 'action' . ucfirst($action);
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            wp_die('Action method not found.', 'TOFU Form Action Error', ['response' => 500]);
        }
    }

    /**
     * Input action.
     * Validate the input and store the input data.
     *
     * @return void
     */
    public function actionInput()
    {
        if (FormHelper::verifyNonceField($this->getKey(), 'input') === false) {
            wp_die('Nonce verification failed.', 'TOFU Nonce Error', ['response' => 403]);
        }

        // Validate input field
        $validated = $_POST;
        $errorFlag = false;
        $error = [];

        foreach ($this->config->validation->rules as $key => $rule) {
            $value = $_POST[$key] ?? null;

            // Check for required fields
            if (!empty($rule['required']) && empty($value)) {
                $errorFlag = true;
                $error[$key] = sprintf('Field "%s" is required.', $key);
                continue;
            }

            // Check for valid email
            if (!empty($rule['email']) && !Validate::isValidEmail($value)) {
                $errorFlag = true;
                $error[$key] = sprintf('Field "%s" must be a valid email address.', $key);
                continue;
            }

            // Check if number
            if (!empty($rule['number']) && !Validate::isValidNumber($value)) {
                $errorFlag = true;
                $error[$key] = sprintf('Field "%s" must be a number.', $key);
                continue;
            }

            // Check min
            if (!empty($rule['min']) && !Validate::isValidLength($value, $rule['min'])) {
                $errorFlag = true;
                $error[$key] = sprintf('Field "%s" length must be greater than or equals to %s.', $key, $rule['min']);
                continue;
            }

            // Check max
            if (!empty($rule['max']) && !Validate::isValidLength($value, null, $rule['max'])) {
                $errorFlag = true;
                $error[$key] = sprintf('Field "%s" length must be less than or equals to %s.', $key, $rule['max']);
                continue;
            }

            // Check if valid phone number
            if (!empty($rule['phone']) && !Validate::isValidPhone($value)) {
                $errorFlag = true;
                $error[$key] = sprintf('Field "%s" must be a valid phone number.', $key);
                continue;
            }

            // Check if valid code
            if (!empty($rule['code']) && !Validate::isValidCode($value)) {
                $errorFlag = true;
                $error[$key] = sprintf('Field "%s" must be a valid code.', $key);
                continue;
            }

            // Check if valid size
            if (!empty($rule['file_size']) && !Validate::isValidCode($value, $rule['file_size'])) {
                $errorFlag = true;
                $error[$key] = sprintf('File "%s" must be at least "%s".', $key, $rule['file_size']);
                continue;
            }
        }

        // Store the input values in the transient
        $this->values = $validated;
        $this->storeValues();

        // Store errors if any
        if($errorFlag) {
            $redirect = $this->config->template->inputPath;
            FormHelper::setError($error);
            wp_redirect($redirect);
            exit;
        }

        // Redirect to the confirmation page
        $url = $this->config->template->confirmPath;

        // If the URL is not set, trigger the confirmation action
        if (empty($url)) {
            return $this->actionConfirm(skipVerify: true);
        }

        wp_redirect($url);
        exit;
    }

    public function actionConfirm(bool $skipVerify = false)
    {
        if ($skipVerify || FormHelper::verifyNonceField($this->getKey(), 'confirm') === false) {
            wp_die('Nonce verification failed.', 'TOFU Nonce Error', ['response' => 403]);
        }

        /** @todo Send email function */

        // Redirect to the result page
        $url = $this->config->template->resultPath;

        wp_redirect($url);
        exit;
    }
}
