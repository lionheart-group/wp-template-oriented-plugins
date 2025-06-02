<?php

namespace TofuPlugin\Models;

use TofuPlugin\Consts;
use TofuPlugin\Helpers\Form as FormHelper;
use TofuPlugin\Structure\FormConfig;

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

        /** @todo Validate input field */
        $validated = $_POST;

        // Store the input values in the transient
        $this->values = $validated;
        $this->storeValues();

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


        // Send email function
        $url = $this->config->template->resultPath;
        foreach( $this->config->mail->recipients->recipients as $recipient) {
            $headers = [];

            $recipientEmail = $this->replaceBracesValue($recipient->recipientEmail, $this->values);
            $recipientCcEmail = $this->replaceBracesValue($recipient->recipientCcEmail, $this->values);
            $recipientBccEmail = $this->replaceBracesValue($recipient->recipientBccEmail, $this->values);

            $headers[] = 'From: ' . $this->config->mail->fromName . ' <' . $this->config->mail->fromEmail . '>';
            $headers[] = 'Cc: ' . $recipientCcEmail;
            $headers[] = 'Bcc: ' . $recipientBccEmail;

            $subject = $this->getTemplateContent($recipient->subjectPath);
            $body = $this->getTemplateContent($recipient->mailBodyPath);

            wp_mail($recipientEmail, $subject, $body, $headers);
        }

        // Redirect to the result page
        wp_redirect($url);
        exit;
    }

    public function getTemplateContent(string $path): string
    {
        ob_start();
        include($path);
        return ob_get_clean();
    }

    public function replaceBracesValue(string $email, array $values)
    {
        $startsWithBrace = (substr($email, 0, 1) === '{');
        $endsWithBrace = (substr($email, -1) === '}');
        if ($startsWithBrace && $endsWithBrace) {
            $replacedEmail = str_replace(array('{', '}'), '', $email);
            if (array_key_exists($replacedEmail, $values)) {
                $replacedEmail = $values[$replacedEmail];
            } else {
                $replacedEmail = '';
            }
        }
        else {
            $replacedEmail = $email;
        }

        return $replacedEmail;
    }
}
