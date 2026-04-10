<?php

namespace TofuPlugin\Models;

use TofuPlugin\Consts;
use TofuPlugin\Helpers\Encryptor;
use TofuPlugin\Helpers\Form as FormHelper;
use TofuPlugin\Helpers\ReCAPTCHA;
use TofuPlugin\Helpers\Session;
use TofuPlugin\Helpers\Template;
use TofuPlugin\Helpers\Turnstile;
use TofuPlugin\Helpers\Uploader;
use TofuPlugin\Logger;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Models\Validation;
use TofuPlugin\Structure\MailAddress;
use TofuPlugin\Structure\ReCAPTCHAConfig;
use TofuPlugin\Structure\TurnstileConfig;
use TofuPlugin\Structure\UploadedFile;

class Form
{
    /**
     * Input values.
     *
     * @var FieldValueCollection
     */
    protected FieldValueCollection $values;

    /**
     * Error values.
     *
     * @var ValidationErrorCollection
     */
    protected ValidationErrorCollection $errors;

    /**
     * Uploaded files.
     *
     * @var UploadedFileCollection
     */
    protected UploadedFileCollection $files;

    /**
     * Flush session value.
     *
     * @var ?string
     */
    protected ?string $flushValue = null;

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
        $this->files = new UploadedFileCollection();

        // Load the session values from Session Table
        $sessionValues = Session::get($this->config->key);

        // Populate values and errors from session
        if ($sessionValues) {
            if (isset($sessionValues['values']) && $sessionValues['values']) {
                foreach ($sessionValues['values'] as $field => $value) {
                    // If not defined in `allows`, skip to add value
                    if ($this->isFieldAllowed($field, [Consts::UPLOADED_FILES_INPUT_NAME]) === false) {
                        continue;
                    }

                    $this->values->addValue($field, $value);
                }
            }

            if (isset($sessionValues['errors']) && $sessionValues['errors']) {
                foreach ($sessionValues['errors'] as $field => $messages) {
                    // If not defined in `allows`, skip to add value
                    if ($this->isFieldAllowed($field, [Consts::TURNSTILE_TOKEN_INPUT_NAME, Consts::RECAPTCHA_TOKEN_INPUT_NAME]) === false) {
                        continue;
                    }

                    foreach ($messages as $message) {
                        $this->errors->addError($field, $message);
                    }
                }
            }

            if (isset($sessionValues['files']) && $sessionValues['files']) {
                foreach ($sessionValues['files'] as $fileData) {
                    // If not defined in `allows`, skip to add value
                    if ($this->isFieldAllowed($fileData['name']) === false) {
                        continue;
                    }

                    $this->files->addFile(new UploadedFile(
                        id: $fileData['id'] ?? null,
                        name: $fileData['name'] ?? '',
                        fileName: $fileData['fileName'] ?? '',
                        mimeType: $fileData['mimeType'] ?? '',
                        tempName: $fileData['tempName'] ?? '',
                        size: $fileData['size'] ?? 0,
                    ));
                }
            }

            if (isset($sessionValues['flushValue'])) {
                $this->flushValue = $sessionValues['flushValue'];
            }
        }

        Logger::info('Form initialized', [
            'key' => $this->config->key,
            'name' => $this->config->name,
            'session' => $sessionValues,
            'values' => $this->values->toArray(),
            'errors' => $this->errors->toArray(),
            'files' => $this->files->toArray(),
            'flushValue' => $this->flushValue,
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
     * Get the uploaded files.
     *
     * @return UploadedFileCollection
     */
    public function getFiles(): UploadedFileCollection
    {
        return $this->files;
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
     * Check if reCAPTCHA is configured
     *
     * @return bool
     */
    public function hasRecaptcha(): bool
    {
        return $this->config->recaptcha !== null;
    }

    /**
     * Get the reCAPTCHA configuration
     *
     * @return ?ReCAPTCHAConfig
     */
    public function getRecaptchaConfig(): ?ReCAPTCHAConfig
    {
        return $this->config->recaptcha;
    }

    /**
     * Check if Turnstile is configured
     *
     * @return bool
     */
    public function hasTurnstile(): bool
    {
        return $this->config->turnstile !== null;
    }

    /**
     * Get the Turnstile configuration
     *
     * @return ?TurnstileConfig
     */
    public function getTurnstileConfig(): ?TurnstileConfig
    {
        return $this->config->turnstile;
    }

    /**
     * Check the specified field name is allowed to store value in the session.
     *
     * @param string $field The field name.
     * @return bool
     */
    public function isFieldAllowed(string $field, array $allowsList = []): bool
    {
        if (is_array($allowsList) && !empty($allowsList) && in_array($field, $allowsList, true)) {
            return true;
        }

        return in_array($field, $this->config->validation->allows, true);
    }

    /**
     * Store the values in the Session table.
     */
    protected function storeSession(?string $flushValue = null): void
    {
        Session::save($this->config->key, [
            'values' => $this->values->toArray(),
            'errors' => $this->errors->toArray(),
            'files' => $this->files->toArray(),
            'flushValue' => $flushValue,
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
     * Verify nonce field.
     *
     * @param string $action The nonce action ('input' or 'confirm').
     * @param array  $post   POST data to read the nonce from. Defaults to $_POST.
     * @return bool
     */
    public function verifyNonceField(string $action, array $post = []): bool
    {
        $nonceKey = sprintf(Consts::NONCE_FORMAT, $this->config->key);

        if (empty($post)) {
            $post = $_POST;
        }

        $nonce = $post[$nonceKey] ?? null;

        // If nonce is missing or not a string, return false
        if (empty($nonce) || !is_string($nonce)) {
            return false;
        }

        return wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), $action);
    }

    /**
     * Input action.
     * Validate the input and store the input data.
     *
     * @return void
     */
    public function actionInput(): void
    {
        if ($this->verifyNonceField('input') === false) {
            wp_die('Nonce verification failed.', 'TOFU Nonce Error', ['response' => 403]);
        }

        $result = $this->processInput($_POST, $_FILES);

        if (!$result['success']) {
            if ($result['next'] === 'error') {
                wp_die('Failed to send email.', 'TOFU Mail Error', ['response' => 500]);
            }
            $this->redirect('input');
        }

        $this->redirect($result['next']);
    }

    /**
     * Process the input step: validate, run bot-checks, store session.
     *
     * Returns a result array instead of redirecting, making it usable from
     * both the traditional redirect flow and the REST API endpoint.
     *
     * @param array $post  POST field values (typically $_POST).
     * @param array $files Uploaded file data (typically $_FILES).
     * @return array{
     *   success: bool,
     *   errors:  array<string, string[]>,
     *   next:    'confirm'|'result'|'input'|'error'
     * }
     */
    public function processInput(array $post, array $files): array
    {
        // Reset values and errors from any previous attempt
        $this->values = new FieldValueCollection();
        $this->errors = new ValidationErrorCollection();

        // Validate fields
        $validation = new Validation();
        $validation->validate($this, $post, $files);

        // Bot-protection checks
        $this->verifyRecaptcha($post);
        $this->verifyTurnstile($post);

        // Persist values (and any errors) to session
        $this->storeSession();

        if ($this->errors->hasErrors()) {
            return ['success' => false, 'errors' => $this->errors->toArray(), 'next' => 'input'];
        }

        // No confirmation step — run confirm processing inline
        if (!$this->config->hasConfirmStep()) {
            $confirmResult = $this->processConfirm(skipVerify: true);
            if (!$confirmResult['success']) {
                return $confirmResult;
            }
            return ['success' => true, 'errors' => [], 'next' => 'result'];
        }

        return ['success' => true, 'errors' => [], 'next' => 'confirm'];
    }

    /**
     * Verify reCAPTCHA token.
     *
     * @param array $post POST data to read the token from. Defaults to $_POST.
     * @return bool
     */
    public function verifyRecaptcha(array $post = []): bool
    {
        if ($this->config->recaptcha === null) {
            return true;
        }

        if (empty($post)) {
            $post = $_POST;
        }

        // Verification type and sanitize input
        $token = $post[Consts::RECAPTCHA_TOKEN_INPUT_NAME] ?? '';
        if (empty($token) || !is_string($token)) {
            $this->errors->addError(Consts::RECAPTCHA_TOKEN_INPUT_NAME, 'reCAPTCHA token is missing.');
            return false;
        }
        $token = sanitize_text_field(wp_unslash($token));

        // Verify the token
        $isValidRecaptcha = ReCAPTCHA::verifyToken($this->config->recaptcha, $token);
        if (!$isValidRecaptcha) {
            foreach (ReCAPTCHA::getErrors() as $errorMessage) {
                $this->errors->addError(Consts::RECAPTCHA_TOKEN_INPUT_NAME, $errorMessage);
            }
            return false;
        }
        return true;
    }

    /**
     * Verify Turnstile token.
     *
     * @param array $post POST data to read the token from. Defaults to $_POST.
     * @return bool
     */
    public function verifyTurnstile(array $post = []): bool
    {
        if ($this->config->turnstile === null) {
            return true;
        }

        if (empty($post)) {
            $post = $_POST;
        }

        // Verification type and sanitize input
        $token = $post[Consts::TURNSTILE_TOKEN_INPUT_NAME] ?? '';
        if (empty($token) || !is_string($token)) {
            $this->errors->addError(Consts::TURNSTILE_TOKEN_INPUT_NAME, 'Turnstile token is missing.');
            return false;
        }
        $token = sanitize_text_field(wp_unslash($token));

        // Verify the token
        $isValidTurnstile = Turnstile::verifyToken($this->config->turnstile, $token);
        if (!$isValidTurnstile) {
            foreach (Turnstile::getErrors() as $errorMessage) {
                $this->errors->addError(Consts::TURNSTILE_TOKEN_INPUT_NAME, $errorMessage);
            }
            return false;
        }
        return true;
    }

    /**
     * Verify session-stored input values and update validation errors.
     *
     * Uses the current field values loaded from the session to run validation
     * and populate the internal error collection. Returns false if any
     * validation errors are present in the session data, or true otherwise.
     *
     * @return bool True when the session data passes validation, false when errors exist.
     */
    public function verifySession(): bool
    {
        // Validate input field
        $validation = new Validation();
        $validation->validate($this, $this->values->toArray());

        return !$this->errors->hasErrors();
    }

    /**
     * Confirm action.
     * Send emails and clear the session data.
     *
     * @param bool $skipVerify Whether to skip nonce/session/bot-check verification.
     * @return void
     */
    public function actionConfirm(bool $skipVerify = false): void
    {
        if ($skipVerify === false && $this->verifyNonceField('confirm') === false) {
            wp_die('Nonce verification failed.', 'TOFU Nonce Error', ['response' => 403]);
        }

        $result = $this->processConfirm($skipVerify);

        if (!$result['success']) {
            if ($result['next'] === 'error') {
                wp_die('Failed to send email.', 'TOFU Mail Error', ['response' => 500]);
            }
            // Store errors and redirect back to input
            $this->storeSession();
            $this->redirect('input');
        }

        $this->redirect('result');
    }

    /**
     * Process the confirm step: verify session, send emails, store flush value.
     *
     * Returns a result array instead of redirecting/dying, making it usable
     * from both the traditional redirect flow and the REST API endpoint.
     *
     * @param bool  $skipVerify Skip session verification (used
     *                          when confirm is called inline from processInput).
     * @return array{
     *   success: bool,
     *   errors:  array<string, string[]>,
     *   next:    'result'|'input'|'error'
     * }
     */
    public function processConfirm(bool $skipVerify = false): array
    {
        if (!$skipVerify) {
            // Validate session-stored values
            $this->verifySession();

            if ($this->errors->hasErrors()) {
                return ['success' => false, 'errors' => $this->errors->toArray(), 'next' => 'input'];
            }
        }

        $values = $this->values->toArray();

        // Send email to every configured recipient
        foreach ($this->config->mail->recipients->recipients as $recipient) {
            $mail = new Mail();

            $mail->setFrom(new MailAddress(
                email: $this->config->mail->fromEmail,
                name: $this->config->mail->fromName,
            ));

            $mail->addTo(
                Template::replaceBracesValues($recipient->recipientEmail, $values)
            );

            if ($recipient->subject !== null) {
                $mail->setSubject(
                    Template::replaceBracesValues($recipient->subject, $values)
                );
            } else {
                $mail->setSubjectFromTemplate($recipient->subjectPath);
            }

            if ($recipient->mailBody !== null) {
                $mail->setBody(
                    Template::replaceBracesValues($recipient->mailBody, $values)
                );
            } else {
                $mail->setBodyFromTemplate($recipient->mailBodyPath);
            }

            if ($recipient->recipientCcEmail !== null) {
                $mail->addCc(
                    Template::replaceBracesValues($recipient->recipientCcEmail, $values)
                );
            }

            if ($recipient->recipientBccEmail !== null) {
                $mail->addBcc(
                    Template::replaceBracesValues($recipient->recipientBccEmail, $values)
                );
            }

            foreach ($this->files->getAllFiles() as $uploadedFile) {
                $mail->addAttachment($uploadedFile->fileName, $uploadedFile->tempName);
            }

            if (!$mail->send()) {
                Logger::error('Failed to send email', $mail->toArray());
                return ['success' => false, 'errors' => [], 'next' => 'error'];
            }
        }

        // Delete temporary uploaded files
        foreach ($this->files->getAllFiles() as $uploadedFile) {
            $tempPath = $uploadedFile->tempName;
            if (file_exists($tempPath)) {
                wp_delete_file($tempPath);
            }
        }

        // Store flush value so the result page can verify the submission completed
        $this->storeSession(Encryptor::encrypt([
            'form_key'  => $this->config->key,
            'timestamp' => time(),
        ]));

        return ['success' => true, 'errors' => [], 'next' => 'result'];
    }

    public function verifySubmit(): bool
    {
        if ($this->flushValue === null) {
            return false;
        }
        // Clear the session data
        Session::clear($this->config->key);

        $sessionData = Encryptor::decrypt($this->flushValue);
        if ($sessionData === false || !is_array($sessionData)) {
            return false;
        }

        if (!isset($sessionData['form_key']) || $sessionData['form_key'] !== $this->config->key) {
            return false;
        }

        $timestamp = isset($sessionData['timestamp']) ? (int)$sessionData['timestamp'] : 0;
        if (time() - $timestamp > 3600) { // 1 hour expiry
            return false;
        }

        return true;
    }

    public function redirect(string $action): void
    {
        if ($this->config->template === null) {
            wp_die('Template is not configured for this form.', 'TOFU Form Config Error', ['response' => 500]);
        }

        // Check if the action is valid
        if (!in_array($action, ['input', 'confirm', 'result'])) {
            wp_die('Invalid action.', 'TOFU Form Action Error', ['response' => 400]);
        }

        switch ($action) {
            case 'input':
                $redirectUrl = $this->config->template->inputPath;
                break;
            case 'confirm':
                $redirectUrl = $this->config->template->confirmPath;
                break;
            case 'result':
                $redirectUrl = $this->config->template->resultPath;
                break;
            default:
                $redirectUrl = null;
                break;
        }

        if ($redirectUrl === null) {
            wp_die('Redirect URL is not configured.', 'TOFU Form Action Error', ['response' => 500]);
        }

        wp_safe_redirect($redirectUrl);
        exit;
    }
}
