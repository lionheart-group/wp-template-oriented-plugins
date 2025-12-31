<?php

namespace TofuPlugin\Models;

use finfo;
use GUMP;
use TofuPlugin\Consts;
use TofuPlugin\Helpers\Uploader;
use TofuPlugin\Structure\UploadedFile;

class Validation
{
    public function validate(Form $form, array $targetValues): void
    {
        $values = $form->getValues();
        $errors = $form->getErrors();
        $files = $form->getFiles();

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
            $values->addValue($key, $value);
        }

        // Session stored uploaded files
        if (isset($targetValues[Consts::UPLOADED_FILES_INPUT_NAME]) && is_array($targetValues[Consts::UPLOADED_FILES_INPUT_NAME])) {
            foreach ($targetValues[Consts::UPLOADED_FILES_INPUT_NAME] as $fileData) {
                $uploadedFile = new UploadedFile(
                    name: $fileData['name'] ?? '',
                    fileName: $fileData['fileName'] ?? '',
                    mimeType: $fileData['mimeType'] ?? '',
                    tempName: $fileData['tempName'] ?? '',
                    size: (int)($fileData['size'] ?? 0),
                );
                $files->addFile($uploadedFile);
                $values->unsetValue($uploadedFile->name);
            }
        }

        // Upload files
        foreach ($targetValues as $name => $_) {
            if (isset($_FILES[$name]) && isset($_FILES[$name]['error']) && $_FILES[$name]['error'] === UPLOAD_ERR_OK) {
                $uploadedFile = Uploader::upload($name);
                if ($uploadedFile) {
                    $files->addFile($uploadedFile);
                    $values->unsetValue($uploadedFile->name);
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
            if (isset($uploadedFiles[$field]) && is_array($uploadedFiles[$field])) {
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
        if (!isset($params) || empty($params)) {
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
