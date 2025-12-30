<?php

namespace TofuPlugin;

final class Consts
{
    /**
     * Text domain for translations.
     */
    public const TEXT_DOMAIN = 'template-oriented-form-utilities';

    /**
     * Query variable key for the endpoint.
     */
    public const QUERY_KEY = '_tofu_key';

    /**
     * Cookie key for storing session values.
     */
    public const SESSION_COOKIE_KEY = '_tofu_session_key';

    /**
     * Session expuiry time in seconds. (24 hours)
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
}
