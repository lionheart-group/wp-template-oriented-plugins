<?php

namespace TofuPlugin;

final class Consts
{
    /**
     * Query variable key for the endpoint.
     */
    public const QUERY_KEY = '_tofu_key';

    /**
     * Cookie key for storing session values.
     */
    public const SESSION_COOKIE_KEY = '_tofu_session_key';

    /**
     * Session expiry time in seconds. (24 hours)
     */
    public const SESSION_EXPIRY = 86400;

    /**
     * Nonce key format for form submission.
     *
     * 1st parameter: Form key
     */
    public const NONCE_FORMAT = '_tofu_%s_nonce';

    /**
     * Upload directory subfolder for form files.
     */
    public const UPLOAD_SUBFOLDER = 'tofu_uploads';

    /**
     * Uploaded files temporary input field name.
     */
    public const UPLOADED_FILES_INPUT_NAME = '__tofu_uploaded_files';

    /**
     * Percentage for garbage collection.
     */
    public const GARBAGE_COLLECTION_PERCENTAGE = 10;

    /**
     * reCAPTCHA form element ID format.
     */
    public const RECAPTCHA_TOKEN_FORM_ID_FORMAT = '_tofu_recaptcha_form_%s';

    /**
     * reCAPTCHA hidden input field name.
     */
    public const RECAPTCHA_TOKEN_INPUT_NAME = '_tofu_recaptcha_token';

    /**
     * reCAPTCHA hidden input field ID.
     */
    public const RECAPTCHA_TOKEN_INPUT_ID_FORMAT = '_tofu_recaptcha_token_%s';
}
