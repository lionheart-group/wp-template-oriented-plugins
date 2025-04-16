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
     * Nonce key format for form submission.
     *
     * 1st parameter: Form key
     */
    public const NONCE_FORMAT = '_tofu_%s_nonce';

    /**
     * Transient key format for storing session values.
     *
     * 1st parameter: Form key
     * 2nd parameter: Unique identifier for the session
     */
    public const TRANSIENT_FORMAT = 'tofu_field_%s_%s';
}
