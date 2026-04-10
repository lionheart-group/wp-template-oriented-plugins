<?php

namespace TofuPlugin\Models;

use finfo;
use Somnambulist\Components\Validation\Factory;
use TofuPlugin\Consts;
use TofuPlugin\Helpers\Uploader;
use TofuPlugin\Rules\MaxMbRule;
use TofuPlugin\Rules\MimeTypeRule;
use TofuPlugin\Rules\RequiredFileRule;
use TofuPlugin\Structure\UploadedFile;

class Validation
{
    /**
     * Validate form input values
     *
     * @param Form $form
     * @param array<string, mixed> $postValues
     * @param array<string, mixed> $fileValues
     * @return void
     */
    public function validate(Form $form, array $postValues, array $fileValues = []): void
    {
        $values = $form->getValues();
        $errors = $form->getErrors();
        $files = $form->getFiles();

        // Merge $_POST and $_FILES to validate at once.
        // wp_unslash() is applied to POST values because WordPress adds slashes to
        // $_POST (magic_quotes behaviour). $_FILES values are not slashed by WordPress.
        $targetValues = array_merge(wp_unslash($postValues), $fileValues);

        // Get locale
        $full_locale = get_locale();
        $locale = explode('_', $full_locale)[0];

        $localeFile = null;
        switch ($locale) {
            case 'ja':
                $localeFile = __DIR__ . '/../Resources/i18n/ja.php';
                break;
            case 'de':
            case 'en':
            case 'fr':
            case 'tr':
            case 'zh':
                // Use built-in  messages, no need to load a file
                break;
            default:
                // For unsupported locales, fallback to English messages
                $locale = 'en';
                break;
        }

        // Build validation factory and register custom rules
        $factory = new Factory();
        $factory->registerLanguageMessages($locale, $localeFile);
        $factory->addRule('custom_required_file', new RequiredFileRule());
        $factory->addRule('max_mb', new MaxMbRule());
        $factory->addRule('mime_type', new MimeTypeRule());

        // Register custom error messages in 'field:rule' format
        $customMessages = [];
        foreach ($form->config->validation->messages as $field => $ruleMsgs) {
            foreach ($ruleMsgs as $rule => $message) {
                $customMessages[$field . ':' . $rule] = $message;
            }
        }
        if (!empty($customMessages)) {
            $factory->messages()->add($locale, $customMessages);
        }

        // Register custom rule messages
        $factory->messages()->add($locale, [
            'rule.custom_required_file' => __('The :attribute field is required.', 'template-oriented-form-utilities'),
            'rule.max_mb'               => __('The :attribute field must be less than :max_mb MB in size.', 'template-oriented-form-utilities'),
            'rule.mime_type'            => __('The :attribute field must be a file of an allowed type.', 'template-oriented-form-utilities'),
        ]);

        // Create validation instance
        $validation = $factory->make($targetValues, $form->config->validation->rules);

        // Set human-readable field name aliases
        foreach ($form->config->validation->names as $field => $alias) {
            $validation->setAlias($field, $alias);
        }

        $validation->setLanguage($locale)->validate();

        if ($validation->fails()) {
            foreach ($validation->errors()->firstOfAll() as $field => $message) {
                $errors->addError($field, $message);
            }
        }

        // Collect values from raw input (filtered by allows)
        foreach ($targetValues as $key => $value) {
            // If not defined in `allows`, skip to add value
            if ($form->isFieldAllowed($key, [Consts::UPLOADED_FILES_INPUT_NAME]) === false) {
                continue;
            }

            $values->addValue($key, $value);
        }

        // Clean up uploaded files
        // Remove files not exists in previous values or with different ID
        $currentFiles = $files->getAllFiles();
        $previousValues = $targetValues[Consts::UPLOADED_FILES_INPUT_NAME] ?? null;
        foreach ($currentFiles as $uploadedFile) {
            // Unset value to avoid duplication
            $values->unsetValue($uploadedFile->name);

            if (!is_array($previousValues) || !isset($previousValues[$uploadedFile->name])) {
                // If not exists in previous values, remove it
                $files->removeFile($uploadedFile->name);
                continue;
            }

            // If exists, compare ID to keep the file
            // If ID is different, remove it
            $previousFileData = $previousValues[$uploadedFile->name];
            if ($previousFileData !== $uploadedFile->getId()) {
                $files->removeFile($uploadedFile->name);
                continue;
            }
        }

        // Upload files
        foreach ($fileValues as $name => $_) {
            if (isset($fileValues[$name]) && isset($fileValues[$name]['error']) && $fileValues[$name]['error'] === UPLOAD_ERR_OK) {
                // If not defined in `allows`, skip to add value
                if ($form->isFieldAllowed($name) === false) {
                    continue;
                }

                $uploadedFile = Uploader::upload($name);
                if ($uploadedFile) {
                    $files->addFile($uploadedFile);
                    $values->unsetValue($uploadedFile->name);

                    // Add uploaded file ID as value
                    $currentValues = $values->getValue(Consts::UPLOADED_FILES_INPUT_NAME)->value ?? [];
                    if (!is_array($currentValues)) {
                        $currentValues = [];
                    }
                    $currentValues[$uploadedFile->name] = $uploadedFile->getId();
                    $values->addValue(Consts::UPLOADED_FILES_INPUT_NAME, $currentValues);
                }
            }
        }

        // After validation hook
        if (!empty($form->config->validation->after)) {
            $after = $form->config->validation->after;
            $after($form->getValues(), $errors);
        }
    }
}
