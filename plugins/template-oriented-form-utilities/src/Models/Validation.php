<?php

namespace TofuPlugin\Models;

use finfo;
use GUMP;
use TofuPlugin\Consts;
use TofuPlugin\Helpers\Uploader;
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
     * @throws \RuntimeException
     */
    public function validate(Form $form, array $postValues, array $fileValues = []): void
    {
        $values = $form->getValues();
        $errors = $form->getErrors();
        $files = $form->getFiles();

        // Merge $_POST and $_FILES to validate at once
        $targetValues = array_merge($postValues, $fileValues);

        // Get locale
        $full_locale = get_locale();
        $locale = explode('_', $full_locale)[0];

        // Validate input values
        $gump = new GUMP($locale);
        $gump->set_fields_error_messages($form->config->validation->messages);
        $gump->set_field_names($form->config->validation->names);

        // Sanitize and validate
        $sanitizedData = $gump->filter($targetValues, $form->config->validation->filters);
        $isValid = $gump->validate($targetValues, $form->config->validation->rules);

        if ($isValid !== true) {
            // Collect errors
            $gumpErrors = $gump->get_errors_array();
            foreach ($gumpErrors as $field => $message) {
                $errors->addError($field, $message);
            }
        }

        if (!is_array($sanitizedData)) {
            throw new \RuntimeException('Validation failed: sanitized data is not an array.');
        }

        // Collect sanitized values
        foreach ($sanitizedData as $key => $value) {
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

/**
 * Custom validation class for form inputs using GUMP library.
 */
GUMP::add_validator(
    'custom_required_file',
    function ($field, array $input, array $params) {
        // If uploaded file is exists, return true
        if (isset($input[$field]) && is_array($input[$field]) && $input[$field]['error'] === \UPLOAD_ERR_OK) {
            return true;
        }

        // If session stored file is exists, return true
        if (isset($input[Consts::UPLOADED_FILES_INPUT_NAME])) {
            $uploadedFiles = $input[Consts::UPLOADED_FILES_INPUT_NAME];
            if (isset($uploadedFiles[$field]) && !empty($uploadedFiles[$field])) {
                return true;
            }
        }

        return false;
    },
    __('The {field} field is required.', 'template-oriented-form-utilities')
);

// Validate file size in MB
GUMP::add_validator(
    'max_mb',
    function ($field, array $input, array $params) {
        if (!isset($params[0]) || empty($params[0]) || !is_numeric($params[0])) {
            throw new \InvalidArgumentException('Max MB parameter is required for max_mb validation.');
        }

        // If not exists, skip
        if (!isset($input[$field]) || empty($input[$field]['tmp_name'])) {
            return true;
        }

        $maxMb = (int)$params[0];
        $fileSizeInBytes = $input[$field]['size'];
        $fileSizeInMb = $fileSizeInBytes / (1024 * 1024);
        return $fileSizeInMb <= $maxMb;
    },
    __('The {field} field must be less than {param[0]} MB in size.', 'template-oriented-form-utilities')
);

// Validate file mime type
GUMP::add_validator(
    'mime_type',
    function ($field, array $input, array $params) {
        if (empty($params)) {
            throw new \InvalidArgumentException('Mime type parameters are required for mime_type validation.');
        }

        // If not exists, skip
        if (!isset($input[$field]) || empty($input[$field]['tmp_name'])) {
            return true;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $fileMimeType = $finfo->file($input[$field]['tmp_name']);

        return in_array($fileMimeType, $params);
    },
    __('The {field} field must be a file of type: {param}.', 'template-oriented-form-utilities')
);
