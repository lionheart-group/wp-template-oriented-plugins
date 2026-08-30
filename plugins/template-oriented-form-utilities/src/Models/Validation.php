<?php

namespace TofuPlugin\Models;

use TofuPlugin\Consts;
use TofuPlugin\Helpers\Uploader;
use TofuPlugin\Validation\GettextTranslator;
use TofuPlugin\Validation\ValidatorFactory;

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

        // Flatten the per-field overrides to the 'field:rule' keys the
        // message resolver looks up first. The rule name is the bare one the
        // form author wrote, e.g. 'name:max', never 'name:max:200'.
        $customMessages = [];
        foreach ($form->config->validation->messages as $field => $ruleMsgs) {
            foreach ($ruleMsgs as $rule => $message) {
                $customMessages[$field . ':' . $rule] = $message;
            }
        }

        $factory = new ValidatorFactory(new GettextTranslator($customMessages));

        $validation = $factory->make($targetValues, $form->config->validation->rules)
            ->setAliases($form->config->validation->names)
            ->validate();

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
