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

        // Which fields genuinely have a file waiting on the server.
        //
        // A hidden __tofu_uploaded_files input travels with the form to say
        // "keep the file I uploaded earlier", but it is client-supplied and
        // therefore only a CLAIM. A claim counts only when the session
        // actually holds a file for that field AND the ID matches, so a
        // forged input cannot make an empty field look answered.
        $sessionFiles = $files->getAllFiles();
        $claims = $targetValues[Consts::UPLOADED_FILES_INPUT_NAME] ?? null;
        $verifiedUploads = [];

        if (is_array($claims)) {
            foreach ($sessionFiles as $name => $file) {
                $claim = $claims[$name] ?? null;
                // hash_equals() raises a TypeError on non-strings, and a
                // forged claim can be any type at all.
                if (is_string($claim) && $claim !== '' && hash_equals((string) $file->getId(), $claim)) {
                    $verifiedUploads[] = $name;
                }
            }
        }

        $factory = new ValidatorFactory(new GettextTranslator($customMessages));

        $validation = $factory->make($targetValues, $form->config->validation->rules)
            ->setAliases($form->config->validation->names)
            ->setVerifiedUploads($verifiedUploads)
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

        // Drop session files the submission did not claim back.
        //
        // This reuses the set computed before validation rather than
        // repeating the ID comparison, so validation and the surviving file
        // set agree by construction — a field can no longer pass `required`
        // via an upload that this loop then deletes.
        foreach ($sessionFiles as $uploadedFile) {
            // Unset value to avoid duplication
            $values->unsetValue($uploadedFile->name);

            if (!in_array($uploadedFile->name, $verifiedUploads, true)) {
                $files->removeFile($uploadedFile->name);
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
