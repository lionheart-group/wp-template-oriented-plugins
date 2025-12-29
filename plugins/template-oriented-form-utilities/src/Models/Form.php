<?php

namespace TofuPlugin\Models;

use TofuPlugin\Consts;
use TofuPlugin\Helpers\Form as FormHelper;
use TofuPlugin\Helpers\Session;
use TofuPlugin\Helpers\Template;
use TofuPlugin\Logger;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Models\Validation;
use TofuPlugin\Structure\MailAddress;

class Form
{
    /**
     * Input values.
     *
     * @var FieldValueCollection
     */
    protected $values;

    /**
     * Error values.
     *
     * @var ValidationErrorCollection
     */
    protected $errors;

    /**
     * Form constructor.
     */
    public function __construct(
        /**
         * Configuration for the form.
         *
         * @var FormConfig
         */
        public readonly FormConfig $config,
    )
    {
        $this->values = new FieldValueCollection();
        $this->errors = new ValidationErrorCollection();

        // Load the session values from Session Table
        $sessionValues = Session::get($this->config->key);

        // Populate values and errors from session
        if ($sessionValues) {
            if ($sessionValues['values']) {
                foreach ($sessionValues['values'] as $field => $value) {
                    $this->values->addValue($field, $value);
                }
            }

            if ($sessionValues['errors']) {
                foreach ($sessionValues['errors'] as $field => $messages) {
                    foreach ($messages as $message) {
                        $this->errors->addError($field, $message);
                    }
                }
            }
        }

        Logger::info('Form initialized', [
            'key' => $this->config->key,
            'name' => $this->config->name,
            'session' => $sessionValues,
            'values' => $this->values->toArray(),
            'errors' => $this->errors->toArray(),
        ]);
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
     * @return FieldValueCollection
     */
    public function getValues(): FieldValueCollection
    {
        return $this->values;
    }

    /**
     * Get the errors.
     *
     * @return ValidationErrorCollection
     */
    public function getErrors(): ValidationErrorCollection
    {
        return $this->errors;
    }

    /**
     * Store the values in the Session table.
     */
    protected function storeSession(): void
    {
        Session::save($this->config->key, [
            'values' => $this->values->toArray(),
            'errors' => $this->errors->toArray(),
        ]);
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

        // Initialize values and errors
        $this->values = new FieldValueCollection();
        $this->errors = new ValidationErrorCollection();

        // Validate input field
        $validation = new Validation();
        $validation->validate($this, $_POST);

        // Store the input values in the Session table
        $this->storeSession();

        // Redirect back for errors
        if($this->errors->hasErrors()) {
            $redirect = $this->config->template->inputPath;
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

        $values = $this->values->toArray();

        // Send email function
        foreach ( $this->config->mail->recipients->recipients as $recipient) {
            $mail = new Mail();

            // Set mail from
            $mail->setFrom(new MailAddress(
                email: $this->config->mail->fromEmail,
                name: $this->config->mail->fromName,
            ));

            // Set mail to
            $mail->addTo(
                Template::replaceBracesValues(
                    $recipient->recipientEmail,
                    $values
                )
            );

            // Set subject
            if ($recipient->subject !== null) {
                $mail->setSubject(
                    Template::replaceBracesValues(
                        $recipient->subject,
                        $values
                    )
                );
            } else {
                $mail->setSubjectFromTemplate($recipient->subjectPath);
            }

            // Set body
            if ($recipient->mailBody !== null) {
                $mail->setBody(
                    Template::replaceBracesValues(
                        $recipient->mailBody,
                        $values
                    )
                );
            } else {
                $mail->setBodyFromTemplate($recipient->mailBodyPath);
            }

            // Set CC
            if ($recipient->recipientCcEmail !== null) {
                $mail->addCc(
                    Template::replaceBracesValues(
                        $recipient->recipientCcEmail,
                        $values
                    )
                );
            }

            // Set BCC
            if ($recipient->recipientBccEmail !== null) {
                $mail->addBcc(
                    Template::replaceBracesValues(
                        $recipient->recipientBccEmail,
                        $values
                    )
                );
            }

            if (!$mail->send()) {
                Logger::error('Failed to send email', $mail->toArray());
                wp_die('Failed to send email.', 'TOFU Mail Error', ['response' => 500]);
            }
        }

        // Redirect to the result page
        $url = $this->config->template->resultPath;
        wp_redirect($url);
        exit;
    }
}
