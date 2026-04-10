<?php

namespace TofuPlugin\Init;

use TofuPlugin\Consts;
use TofuPlugin\Helpers\Form as FormHelper;
use TofuPlugin\Helpers\Session;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Registers and handles the WP REST API endpoints for AJAX / headless form submission.
 *
 * Routes (per form with ajaxEnabled = true):
 *   GET  /wp-json/tofu/v1/forms/{key}/nonce   — Returns a fresh TOFU nonce for the client.
 *   POST /wp-json/tofu/v1/forms/{key}/input   — Processes the input step; returns JSON.
 *   POST /wp-json/tofu/v1/forms/{key}/confirm — Processes the confirm step; returns JSON.
 *
 * Usage in functions.php:
 * ```php
 * \TofuPlugin\Helpers\Form::register(new \TofuPlugin\Structure\FormConfig(
 *     key:         'contact',
 *     ajaxEnabled: true,
 *     corsOrigins: ['https://frontend.example.com'], // omit for same-origin only
 *     // … rest of config
 * ));
 * ```
 *
 * Client-side (same-origin):
 * ```js
 * const { nonce, field_name } = await fetch('/wp-json/tofu/v1/forms/contact/nonce')
 *     .then(r => r.json());
 *
 * const body = new FormData(document.querySelector('form'));
 * body.append(field_name, nonce);
 *
 * const res = await fetch('/wp-json/tofu/v1/forms/contact/input', {
 *     method: 'POST', body, credentials: 'include',
 * });
 * const data = await res.json();
 * // data.next: 'confirm' | 'result' — drive SPA step state directly
 * if (data.success) { showStep(data.next); }
 * else              { /* show data.errors *\/ }
 * ```
 */
class RestEndpoint
{
    /**
     * Initialize the REST endpoint.
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('rest_api_init', [static::class, 'registerRoutes']);
    }

    /**
     * Register REST routes once for all forms.
     *
     * Routes are registered once; the {key} path parameter is validated by each
     * handler, which is responsible for enforcing ajaxEnabled on the requested form.
     *
     * @return void
     */
    public static function registerRoutes(): void
    {
        // Only register routes if at least one form has ajaxEnabled.
        $ajaxForms = array_filter(
            FormHelper::getAll(),
            fn ($f) => $f->config->ajaxEnabled
        );

        if (empty($ajaxForms)) {
            return;
        }

        // Register routes once; the {key} path parameter is validated by handlers,
        // which are responsible for enforcing ajaxEnabled on the requested form.

        // GET /wp-json/tofu/v1/forms/{key}/nonce
        register_rest_route(
            Consts::REST_NAMESPACE,
            '/forms/(?P<key>[a-zA-Z0-9_\-]+)/nonce',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [static::class, 'handleNonce'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'key' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'action' => [
                        'required'          => false,
                        'default'           => 'input',
                        'sanitize_callback' => 'sanitize_text_field',
                        'enum'              => ['input', 'confirm'],
                    ],
                ],
            ]
        );

        // POST /wp-json/tofu/v1/forms/{key}/input
        register_rest_route(
            Consts::REST_NAMESPACE,
            '/forms/(?P<key>[a-zA-Z0-9_\-]+)/input',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [static::class, 'handleInput'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'key' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ]
        );

        // POST /wp-json/tofu/v1/forms/{key}/confirm
        register_rest_route(
            Consts::REST_NAMESPACE,
            '/forms/(?P<key>[a-zA-Z0-9_\-]+)/confirm',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [static::class, 'handleConfirm'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'key' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ]
        );

        // Build a formKey -> allowedOrigins map for cross-origin forms.
        $corsMap = [];
        foreach ($ajaxForms as $form) {
            if (!empty($form->config->corsOrigins)) {
                $corsMap[$form->getKey()] = $form->config->corsOrigins;
            }
        }

        if (empty($corsMap)) {
            return;
        }

        // Attach CORS headers via rest_post_dispatch so they are included on
        // OPTIONS preflight responses. WordPress does not invoke route callbacks
        // for OPTIONS — this filter fires after dispatch for all methods.
        add_filter(
            'rest_post_dispatch',
            static function ($response, $server, $request) use ($corsMap) {
                if (!($response instanceof \WP_REST_Response)) {
                    return $response;
                }

                // Only act on TOFU routes: /{namespace}/forms/{key}/...
                $pattern = '#^/' . preg_quote(Consts::REST_NAMESPACE, '#') . '/forms/([a-zA-Z0-9_\-]+)/#';
                if (!preg_match($pattern, $request->get_route(), $matches)) {
                    return $response;
                }

                $key = $matches[1];
                if (!isset($corsMap[$key])) {
                    return $response;
                }

                $origin = $request->get_header('origin');
                if (empty($origin) || !in_array($origin, $corsMap[$key], true)) {
                    return $response;
                }

                $response->header('Access-Control-Allow-Origin', esc_url_raw($origin));
                $response->header('Access-Control-Allow-Credentials', 'true');
                $response->header('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
                $response->header('Access-Control-Allow-Headers', 'Content-Type, X-WP-Nonce');
                $response->header('Vary', 'Origin');

                return $response;
            },
            10,
            3
        );
    }

    /**
     * GET /wp-json/tofu/v1/forms/{key}/nonce
     *
     * Returns a fresh nonce so the AJAX client can make an authenticated
     * form submission. The `action` query parameter determines which step
     * the nonce is for (`input` or `confirm`).
     *
     * Response:
     * ```json
     * { "nonce": "abc123…", "field_name": "_tofu_contact_nonce", "action": "input" }
     * ```
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function handleNonce(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $key = $request->get_param('key');

        $form = FormHelper::get($key, strict: false);
        if ($form === false || !$form->config->ajaxEnabled) {
            return new \WP_Error('tofu_form_not_found', 'Form not found.', ['status' => 404]);
        }

        $action = $request->get_param('action') ?? 'input';
        $nonceAction = $action; // 'input' or 'confirm' — matches verifyNonceField()

        $nonce = wp_create_nonce($nonceAction);
        $fieldName = sprintf(Consts::NONCE_FORMAT, $key);

        return new \WP_REST_Response([
            'nonce'      => $nonce,
            'field_name' => $fieldName,
            'action'     => $action,
        ], 200);
    }

    /**
     * POST /wp-json/tofu/v1/forms/{key}/input
     *
     * Accepts multipart/form-data (same as the traditional HTML form) plus
     * the TOFU nonce field obtained from the `/nonce` endpoint.
     *
     * Success response (HTTP 200):
     * ```json
     * { "success": true, "next": "confirm" }
     * ```
     *
     * No confirm step — goes straight to result:
     * ```json
     * { "success": true, "next": "result" }
     * ```
     *
     * Validation error response (HTTP 422):
     * ```json
     * { "success": false, "next": "input", "errors": { "name": ["Please enter your name."] } }
     * ```
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function handleInput(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $key = $request->get_param('key');

        $form = FormHelper::get($key, strict: false);
        if ($form === false || !$form->config->ajaxEnabled) {
            return new \WP_Error('tofu_form_not_found', 'Form not found.', ['status' => 404]);
        }

        static::maybeEnableCors($form->config->corsOrigins);

        // Verify nonce (must be in request body as _tofu_{key}_nonce)
        $post = $request->get_body_params();
        if ($form->verifyNonceField('input', $post) === false) {
            return new \WP_Error('tofu_nonce_failed', 'Nonce verification failed.', ['status' => 403]);
        }

        $files = $request->get_file_params();
        $result = $form->processInput($post, $files);

        if (!$result['success']) {
            if ($result['next'] === 'error') {
                return new \WP_Error('tofu_mail_error', 'Failed to send email.', ['status' => 500]);
            }
            return new \WP_REST_Response([
                'success' => false,
                'next'    => 'input',
                'errors'  => $result['errors'],
            ], 422);
        }

        return new \WP_REST_Response([
            'success' => true,
            'next'    => $result['next'],
        ], 200);
    }

    /**
     * POST /wp-json/tofu/v1/forms/{key}/confirm
     *
     * Sends emails and clears the session. Should be called after the user
     * has reviewed the confirm page.
     *
     * Success response (HTTP 200):
     * ```json
     * { "success": true, "next": "result" }
     * ```
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function handleConfirm(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $key = $request->get_param('key');

        $form = FormHelper::get($key, strict: false);
        if ($form === false || !$form->config->ajaxEnabled) {
            return new \WP_Error('tofu_form_not_found', 'Form not found.', ['status' => 404]);
        }

        static::maybeEnableCors($form->config->corsOrigins);

        $post = $request->get_body_params();
        if ($form->verifyNonceField('confirm', $post) === false) {
            return new \WP_Error('tofu_nonce_failed', 'Nonce verification failed.', ['status' => 403]);
        }

        $result = $form->processConfirm(skipVerify: false, post: $post);

        if (!$result['success']) {
            if ($result['next'] === 'error') {
                return new \WP_Error('tofu_mail_error', 'Failed to send email.', ['status' => 500]);
            }
            return new \WP_REST_Response([
                'success' => false,
                'next'    => 'input',
                'errors'  => $result['errors'],
            ], 422);
        }

        return new \WP_REST_Response([
            'success' => true,
            'next'    => 'result',
        ], 200);
    }

    /**
     * Enable CORS mode on the Session helper when cross-origin is configured.
     *
     * @param string[] $corsOrigins
     * @return void
     */
    protected static function maybeEnableCors(array $corsOrigins): void
    {
        if (!empty($corsOrigins)) {
            Session::enableCors();
        }
    }

}
