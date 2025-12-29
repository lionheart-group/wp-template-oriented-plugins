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
     * Nonce key format for form submission.
     *
     * 1st parameter: Form key
     */
    public const NONCE_FORMAT = '_tofu_%s_nonce';
}
