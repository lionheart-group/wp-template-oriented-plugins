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
    public const NONCE_FORMAT = '__tofu_%s_nonce';

    /**
     * Nonce action format for form submission.
     *
     * The form key is part of the action, not just the field name, so a
     * nonce minted for one form cannot be replayed against another. The
     * REST flow has always done this (see REST_NONCE_ACTION_FORMAT).
     *
     * 1st parameter: Form key
     * 2nd parameter: Nonce action (`input`/`confirm`)
     */
    public const NONCE_ACTION_FORMAT = '_tofu_%s_%s_nonce';

    /**
     * Field-name prefixes reserved for the plugin's own hidden inputs.
     *
     * A form declaring a field under one of these would collide with an
     * input formClose() renders. PHP keeps only the last value for a
     * duplicated name, and the plugin's input is rendered last, so the
     * form's own value would be dropped with no error at all. FormConfig
     * rejects such names at registration rather than letting that happen.
     *
     * Both spellings are reserved: the plugin's own field names all use
     * `__tofu_`, but `_tofu_` is reserved too, both because the field names
     * used it before and because nothing a form legitimately owns is named
     * that either.
     *
     * @var string[]
     */
    public const RESERVED_FIELD_PREFIXES = ['_tofu_', '__tofu_'];

    /**
     * Transitional: the hidden-input field names used before they moved to
     * the `__tofu_` prefix.
     *
     * Read as a fallback so a visitor who loaded a form page before the
     * update is not rejected on submitting it afterwards. Remove these
     * together with the fallbacks that consume them once no browser can
     * still be holding a page that old — see
     * issues/2026-09-17-13-58-21.md.
     */
    public const LEGACY_NONCE_FORMAT = '_tofu_%s_nonce';
    public const LEGACY_RECAPTCHA_TOKEN_INPUT_NAME = '_tofu_recaptcha_token';
    public const LEGACY_TURNSTILE_TOKEN_INPUT_NAME = '_tofu_turnstile_token';

    /**
     * Upload directory subfolder for form files.
     */
    public const UPLOAD_SUBFOLDER = 'tofu-uploads';

    /**
     * Upload directory subfolder for log files.
     */
    public const LOG_SUBFOLDER = 'tofu-logs';

    /**
     * Uploaded files temporary input field name.
     */
    public const UPLOADED_FILES_INPUT_NAME = '__tofu_uploaded_files';

    /**
     * Hidden input field name carrying a Form::setTemplate() override from
     * the input-page GET through to the following POST, so it can be
     * persisted to the session only once a submission actually happens.
     */
    public const TEMPLATE_OVERRIDE_INPUT_NAME = '__tofu_template_override';

    /**
     * Percentage for garbage collection.
     */
    public const GARBAGE_COLLECTION_PERCENTAGE = 10;

    /**
     * Form element ID format.
     */
    public const FORM_ID_FORMAT = '_tofu_form_%s';

    /**
     * reCAPTCHA hidden input field name.
     */
    public const RECAPTCHA_TOKEN_INPUT_NAME = '__tofu_recaptcha_token';

    /**
     * reCAPTCHA hidden input field ID.
     *
     * An element ID, not a field name, so it keeps the single-underscore
     * prefix — it shares a namespace with the theme's own element IDs, not
     * with the form's field names.
     */
    public const RECAPTCHA_TOKEN_INPUT_ID_FORMAT = '_tofu_recaptcha_token_%s';

    /**
     * Turnstile hidden input field name.
     */
    public const TURNSTILE_TOKEN_INPUT_NAME = '__tofu_turnstile_token';

    /**
     * WP REST API namespace for TOFU endpoints.
     */
    public const REST_NAMESPACE = 'tofu/v1';

    /**
     * REST API nonce action format.
     *
     * 1st parameter: Form key
     * 2nd parameter: Nonce action (e.g. `input`/`confirm`)
     */
    public const REST_NONCE_ACTION_FORMAT = '_tofu_%s_%s_rest_nonce';
}
