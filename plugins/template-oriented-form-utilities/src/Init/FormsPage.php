<?php

namespace TofuPlugin\Init;

use TofuPlugin\Consts;
use TofuPlugin\Helpers\Form;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailRecipientsConfig;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Read-only view of the forms the theme registered.
 *
 * There is nothing to save: configuration lives in theme code. The page
 * exists so that someone without access to the code can see which forms
 * exist, where they send mail, which fields they accept, and whether the
 * pages and mail templates they point at are actually there.
 */
class FormsPage
{
    /**
     * Badge tones: a supplementary note, or something worth a second look.
     */
    public const TONE_INFO = 'info';
    public const TONE_WARNING = 'warning';

    /**
     * Results of pathStatus() and templateStatus().
     */
    public const STATUS_OK = 'ok';
    public const STATUS_MISSING = 'missing';
    public const STATUS_UNCHECKED = 'unchecked';
    public const STATUS_NONE = 'none';

    /**
     * Tags badgeHtml() produces, for wp_kses() at output time.
     */
    public const ALLOWED_HTML = [
        'span' => ['class' => true],
    ];

    /**
     * Register the admin_menu action. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        add_action('admin_menu', [static::class, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [static::class, 'enqueueStyles']);
    }

    /**
     * Register the page under Tools.
     *
     * Shares its capability with the records page, so one filter
     * (`tofu_admin_page_capability`) governs both.
     */
    public static function addMenuPage(): void
    {
        add_management_page(
            page_title: __('Form Settings (TOFU)', 'template-oriented-form-utilities'),
            menu_title: __('Forms (TOFU)', 'template-oriented-form-utilities'),
            capability: AdminPage::capability(),
            menu_slug:  Consts::ADMIN_PAGE_SLUG,
            callback:   [static::class, 'renderPage'],
        );
    }

    /**
     * The screen ID of the page.
     *
     * @return string
     */
    public static function screenId(): string
    {
        return 'tools_page_' . Consts::ADMIN_PAGE_SLUG;
    }

    /**
     * Enqueue the page's styles on the page, and nowhere else.
     */
    public static function enqueueStyles(): void
    {
        $screen = get_current_screen();
        if ($screen === null || $screen->id !== self::screenId()) {
            return;
        }

        wp_enqueue_style(
            'template-oriented-form-utilities-admin',
            plugins_url('assets/css/admin.css', TOFU_PLUGIN_FILE),
            [],
            TOFU_VERSION
        );
    }

    /**
     * Render the page.
     */
    public static function renderPage(): void
    {
        if (!current_user_can(AdminPage::capability())) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'template-oriented-form-utilities'));
        }

        $recaptcha = Form::getRecaptchaConfig();
        $turnstile = Form::getTurnstileConfig();
        $forms = Form::getAll();
        ?>
        <div class="wrap tofu-page">
            <h1><?php echo esc_html__('Form Settings (TOFU)', 'template-oriented-form-utilities'); ?></h1>

            <p><?php echo esc_html__('This screen is read-only. TOFU is configured in the theme\'s PHP code, and nothing is stored in the database.', 'template-oriented-form-utilities'); ?></p>

            <h2><?php echo esc_html__('Bot protection', 'template-oriented-form-utilities'); ?></h2>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('reCAPTCHA', 'template-oriented-form-utilities'); ?></th>
                        <td>
                            <?php if ($recaptcha === null) : ?>
                                <?php echo esc_html__('Not registered', 'template-oriented-form-utilities'); ?>
                            <?php else : ?>
                                <?php echo esc_html__('Registered', 'template-oriented-form-utilities'); ?>
                                <p class="description">
                                    <?php echo esc_html__('Site key:', 'template-oriented-form-utilities'); ?>
                                    <code><?php echo esc_html($recaptcha->siteKey); ?></code><br>
                                    <?php echo esc_html__('Threshold:', 'template-oriented-form-utilities'); ?>
                                    <code><?php echo esc_html((string) $recaptcha->threshold); ?></code>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Turnstile', 'template-oriented-form-utilities'); ?></th>
                        <td>
                            <?php if ($turnstile === null) : ?>
                                <?php echo esc_html__('Not registered', 'template-oriented-form-utilities'); ?>
                            <?php else : ?>
                                <?php echo esc_html__('Registered', 'template-oriented-form-utilities'); ?>
                                <p class="description">
                                    <?php echo esc_html__('Site key:', 'template-oriented-form-utilities'); ?>
                                    <code><?php echo esc_html($turnstile->siteKey); ?></code>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if ($forms === []) : ?>
                <h2><?php echo esc_html__('Forms', 'template-oriented-form-utilities'); ?></h2>
                <p><?php echo esc_html__('No forms are registered.', 'template-oriented-form-utilities'); ?></p>
            <?php else : ?>
                <?php foreach ($forms as $form) : ?>
                    <?php self::renderForm($form->config, $recaptcha !== null, $turnstile !== null); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * One form's section.
     *
     * @param FormConfig $config
     * @param bool $recaptchaRegistered
     * @param bool $turnstileRegistered
     */
    protected static function renderForm(FormConfig $config, bool $recaptchaRegistered, bool $turnstileRegistered): void
    {
        $pageExists = [static::class, 'pageExists'];
        $templateExists = [static::class, 'templateExists'];
        $template = $config->template;
        $mail = $config->mail;
        $validation = $config->validation;
        $warnings = self::warnings($config, $pageExists, $templateExists, $recaptchaRegistered, $turnstileRegistered);

        $pages = [
            [__('Input page', 'template-oriented-form-utilities'), $template?->inputPath],
            [__('Confirm page', 'template-oriented-form-utilities'), $template?->confirmPath],
            [__('Result page', 'template-oriented-form-utilities'), $template?->resultPath],
        ];
        ?>
        <h2 class="tofu-form-title">
            <?php echo esc_html($config->name); ?>
            <code><?php echo esc_html($config->key); ?></code>
        </h2>

        <h3><?php echo esc_html__('Pages', 'template-oriented-form-utilities'); ?></h3>

        <table class="form-table" role="presentation">
            <tbody>
                <?php foreach ($pages as [$label, $path]) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td class="tofu-url">
                            <?php self::renderPath($path, self::pathStatus($path, $config->dynamicTemplate, $pageExists)); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3><?php echo esc_html__('Mail', 'template-oriented-form-utilities'); ?></h3>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('From', 'template-oriented-form-utilities'); ?></th>
                    <td><?php echo esc_html(self::addressForDisplay($mail->fromEmail, $mail->fromName)); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Return-Path', 'template-oriented-form-utilities'); ?></th>
                    <td><?php echo esc_html($mail->returnPath ?? '—'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Recipients', 'template-oriented-form-utilities'); ?></th>
                    <td>
                        <?php if ($mail->recipients->recipients === []) : ?>
                            —
                        <?php else : ?>
                            <table class="wp-list-table widefat fixed striped tofu-table">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php echo esc_html__('To', 'template-oriented-form-utilities'); ?></th>
                                        <th scope="col"><?php echo esc_html__('CC', 'template-oriented-form-utilities'); ?></th>
                                        <th scope="col"><?php echo esc_html__('BCC', 'template-oriented-form-utilities'); ?></th>
                                        <th scope="col"><?php echo esc_html__('Subject', 'template-oriented-form-utilities'); ?></th>
                                        <th scope="col"><?php echo esc_html__('Body', 'template-oriented-form-utilities'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mail->recipients->recipients as $recipient) : ?>
                                        <?php self::renderRecipientRow($recipient); ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <h3><?php echo esc_html(_x('Fields', 'form settings section', 'template-oriented-form-utilities')); ?></h3>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('Allowed fields', 'template-oriented-form-utilities'); ?></th>
                    <td>
                        <?php if ($validation->allows === []) : ?>
                            —
                        <?php else : ?>
                            <table class="wp-list-table widefat fixed striped tofu-table">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php echo esc_html__('Field', 'template-oriented-form-utilities'); ?></th>
                                        <th scope="col"><?php echo esc_html__('Label', 'template-oriented-form-utilities'); ?></th>
                                        <th scope="col"><?php echo esc_html__('Rules', 'template-oriented-form-utilities'); ?></th>
                                        <th scope="col"><?php echo esc_html__('Recorded', 'template-oriented-form-utilities'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($validation->allows as $field) : ?>
                                        <?php self::renderFieldRow($config, (string) $field); ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('"after" hook', 'template-oriented-form-utilities'); ?></th>
                    <td><?php self::renderYesNo($validation->after !== null); ?></td>
                </tr>
            </tbody>
        </table>

        <h3><?php echo esc_html__('Features', 'template-oriented-form-utilities'); ?></h3>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('Save to database', 'template-oriented-form-utilities'); ?></th>
                    <td><?php self::renderEnabled($config->saveToDatabase); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Confirm step', 'template-oriented-form-utilities'); ?></th>
                    <td><?php self::renderEnabled($config->hasConfirmStep()); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('reCAPTCHA', 'template-oriented-form-utilities'); ?></th>
                    <td><?php self::renderEnabled($config->recaptchaEnabled); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Turnstile', 'template-oriented-form-utilities'); ?></th>
                    <td><?php self::renderEnabled($config->turnstileEnabled); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('AJAX (REST API)', 'template-oriented-form-utilities'); ?></th>
                    <td><?php self::renderEnabled($config->ajaxEnabled); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('CORS origins', 'template-oriented-form-utilities'); ?></th>
                    <td class="tofu-url">
                        <?php if ($config->corsOrigins === []) : ?>
                            —
                        <?php else : ?>
                            <?php foreach ($config->corsOrigins as $origin) : ?>
                                <code><?php echo esc_html((string) $origin); ?></code><br>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Dynamic template', 'template-oriented-form-utilities'); ?></th>
                    <td><?php self::renderEnabled($config->dynamicTemplate); ?></td>
                </tr>
            </tbody>
        </table>

        <h3><?php echo esc_html__('Checks', 'template-oriented-form-utilities'); ?></h3>

        <?php if ($warnings === []) : ?>
            <p><?php echo wp_kses(self::badgeHtml(self::TONE_INFO, __('No problems found', 'template-oriented-form-utilities')), self::ALLOWED_HTML); ?></p>
        <?php else : ?>
            <ul class="tofu-warnings">
                <?php foreach ($warnings as $warning) : ?>
                    <li><?php echo wp_kses(self::badgeHtml(self::TONE_WARNING, __('Warning', 'template-oriented-form-utilities')), self::ALLOWED_HTML); ?> <?php echo esc_html($warning); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php
    }

    /**
     * @param MailRecipientsConfig $recipient
     */
    protected static function renderRecipientRow(MailRecipientsConfig $recipient): void
    {
        ?>
        <tr>
            <td class="tofu-url"><?php echo esc_html($recipient->recipientEmail); ?></td>
            <td class="tofu-url"><?php echo esc_html($recipient->recipientCcEmail ?? '—'); ?></td>
            <td class="tofu-url"><?php echo esc_html($recipient->recipientBccEmail ?? '—'); ?></td>
            <td>
                <?php if ($recipient->subject !== null) : ?>
                    <?php echo esc_html($recipient->subject); ?>
                <?php else : ?>
                    <?php self::renderTemplate($recipient->subjectPath); ?>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($recipient->mailBody !== null) : ?>
                    <?php echo esc_html__('Inline text', 'template-oriented-form-utilities'); ?>
                <?php else : ?>
                    <?php self::renderTemplate($recipient->mailBodyPath); ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * @param FormConfig $config
     * @param string $field
     */
    protected static function renderFieldRow(FormConfig $config, string $field): void
    {
        $validation = $config->validation;
        $label = $validation->names[$field] ?? null;
        $rules = self::ruleList($validation->rules[$field] ?? null);
        ?>
        <tr>
            <td><code><?php echo esc_html($field); ?></code></td>
            <td><?php echo esc_html(is_scalar($label) && (string) $label !== '' ? (string) $label : '—'); ?></td>
            <td class="tofu-url">
                <?php if ($rules === []) : ?>
                    —
                <?php else : ?>
                    <?php foreach ($rules as $rule) : ?>
                        <code><?php echo esc_html($rule); ?></code>
                    <?php endforeach; ?>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!$config->saveToDatabase) : ?>
                    —
                <?php else : ?>
                    <?php self::renderYesNo($validation->records === [] || in_array($field, $validation->records, true)); ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * A page path with its existence badge.
     *
     * @param ?string $path
     * @param string $status One of the STATUS_* constants.
     */
    protected static function renderPath(?string $path, string $status): void
    {
        if ($status === self::STATUS_NONE) {
            echo '—';
            return;
        }

        $url = self::resolveUrl((string) $path);
        if ($url !== null) {
            printf('<a href="%s">%s</a>', esc_url($url), esc_html((string) $path));
        } else {
            echo '<code>' . esc_html((string) $path) . '</code>';
        }

        if ($status === self::STATUS_MISSING) {
            echo ' ' . wp_kses(self::badgeHtml(self::TONE_WARNING, __('Page not found', 'template-oriented-form-utilities')), self::ALLOWED_HTML);
        } elseif ($status === self::STATUS_UNCHECKED) {
            echo ' ' . wp_kses(self::badgeHtml(self::TONE_INFO, __('Can\'t be checked', 'template-oriented-form-utilities')), self::ALLOWED_HTML);
        }
    }

    /**
     * A template slug with its existence badge.
     *
     * @param ?string $slug
     */
    protected static function renderTemplate(?string $slug): void
    {
        $status = self::templateStatus($slug, [static::class, 'templateExists']);
        if ($status === self::STATUS_NONE) {
            echo '—';
            return;
        }

        echo '<code>' . esc_html((string) $slug) . '</code>';

        if ($status === self::STATUS_MISSING) {
            echo ' ' . wp_kses(self::badgeHtml(self::TONE_WARNING, __('Template not found', 'template-oriented-form-utilities')), self::ALLOWED_HTML);
        }
    }

    /**
     * @param bool $value
     */
    protected static function renderYesNo(bool $value): void
    {
        echo $value
            ? esc_html__('Yes', 'template-oriented-form-utilities')
            : esc_html__('No', 'template-oriented-form-utilities');
    }

    /**
     * @param bool $value
     */
    protected static function renderEnabled(bool $value): void
    {
        echo $value
            ? esc_html__('Enabled', 'template-oriented-form-utilities')
            : esc_html__('Disabled', 'template-oriented-form-utilities');
    }

    /**
     * Whether a page or post answers at the URL.
     *
     * The front page always exists, whatever show_on_front says.
     *
     * @param string $url
     * @return bool
     */
    public static function pageExists(string $url): bool
    {
        if (untrailingslashit($url) === untrailingslashit(home_url())) {
            return true;
        }

        return url_to_postid($url) > 0;
    }

    /**
     * Whether the theme has the template get_template_part() would load.
     *
     * @param string $slug
     * @return bool
     */
    public static function templateExists(string $slug): bool
    {
        return locate_template($slug . '.php') !== '';
    }

    /**
     * Problems worth a second look, as sentences.
     *
     * @param FormConfig $config
     * @param callable(string): bool $pageExists
     * @param callable(string): bool $templateExists
     * @param bool $recaptchaRegistered
     * @param bool $turnstileRegistered
     * @return string[]
     */
    public static function warnings(
        FormConfig $config,
        callable $pageExists,
        callable $templateExists,
        bool $recaptchaRegistered,
        bool $turnstileRegistered,
    ): array {
        $warnings = [];
        $template = $config->template;

        $pages = [
            /* translators: %s: page path, e.g. /contact/ */
            [__('Input page not found: %s', 'template-oriented-form-utilities'), $template?->inputPath],
            /* translators: %s: page path, e.g. /contact/confirm/ */
            [__('Confirm page not found: %s', 'template-oriented-form-utilities'), $template?->confirmPath],
            /* translators: %s: page path, e.g. /contact/result/ */
            [__('Result page not found: %s', 'template-oriented-form-utilities'), $template?->resultPath],
        ];
        foreach ($pages as [$message, $path]) {
            if (self::pathStatus($path, $config->dynamicTemplate, $pageExists) === self::STATUS_MISSING) {
                $warnings[] = sprintf($message, (string) $path);
            }
        }

        foreach ($config->mail->recipients->recipients as $recipient) {
            $slugs = [];
            if ($recipient->subject === null) {
                $slugs[] = $recipient->subjectPath;
            }
            if ($recipient->mailBody === null) {
                $slugs[] = $recipient->mailBodyPath;
            }

            foreach ($slugs as $slug) {
                if (self::templateStatus($slug, $templateExists) === self::STATUS_MISSING) {
                    /* translators: %s: template slug, e.g. form/contact/admin-body */
                    $warnings[] = sprintf(__('Mail template not found: %s', 'template-oriented-form-utilities'), (string) $slug);
                }
            }
        }

        if ($config->recaptchaEnabled && !$recaptchaRegistered) {
            $warnings[] = __('reCAPTCHA is enabled, but no reCAPTCHA configuration is registered (Form::setRecaptcha()).', 'template-oriented-form-utilities');
        }

        if ($config->turnstileEnabled && !$turnstileRegistered) {
            $warnings[] = __('Turnstile is enabled, but no Turnstile configuration is registered (Form::setTurnstile()).', 'template-oriented-form-utilities');
        }

        $unknown = self::unknownPlaceholders($config);
        if ($unknown !== []) {
            $list = implode(', ', array_map(static fn (string $name): string => '{' . $name . '}', $unknown));
            $warnings[] = $config->validation->after !== null
                /* translators: %s: comma-separated placeholders, e.g. {company}, {zip} */
                ? sprintf(__('Placeholders that are not allowed fields: %s. They stay as they are in the mail unless the "after" hook sets them.', 'template-oriented-form-utilities'), $list)
                /* translators: %s: comma-separated placeholders, e.g. {company}, {zip} */
                : sprintf(__('Placeholders that are not allowed fields: %s. They stay as they are in the mail.', 'template-oriented-form-utilities'), $list);
        }

        return $warnings;
    }

    /**
     * The field names of the `{field}` placeholders in a text.
     *
     * Mirrors Template::replaceBracesValues(), which allows whitespace
     * inside the braces (`{ email }`). There are no reserved names: only
     * submitted values are substituted.
     *
     * @param string $text
     * @return string[] Unique, in order of appearance.
     */
    public static function placeholders(string $text): array
    {
        if (preg_match_all('/\{\s*([^{}\s]+)\s*\}/u', $text, $matches) === false) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * Placeholders in the mail that no allowed field fills.
     *
     * Covers every text processConfirm() runs through
     * Template::replaceBracesValues(): To, CC, BCC, the subject and the
     * inline body. Template files (subjectPath, mailBodyPath) are rendered
     * by PHP, not substituted, so they are not scanned.
     *
     * @param FormConfig $config
     * @return string[]
     */
    public static function unknownPlaceholders(FormConfig $config): array
    {
        $allows = array_map('strval', $config->validation->allows);
        $unknown = [];

        foreach ($config->mail->recipients->recipients as $recipient) {
            $texts = [
                $recipient->recipientEmail,
                $recipient->recipientCcEmail,
                $recipient->recipientBccEmail,
                $recipient->subject,
                $recipient->mailBody,
            ];

            foreach ($texts as $text) {
                foreach (self::placeholders((string) $text) as $name) {
                    if (!in_array($name, $allows, true) && !in_array($name, $unknown, true)) {
                        $unknown[] = $name;
                    }
                }
            }
        }

        return $unknown;
    }

    /**
     * Whether the page at a configured path exists.
     *
     * @param ?string $path The configured path or URL.
     * @param bool $dynamic Whether the form's templates come from Form::setTemplate().
     * @param callable(string): bool $pageExists Receives an absolute URL on the site.
     * @return string One of the STATUS_* constants.
     */
    public static function pathStatus(?string $path, bool $dynamic, callable $pageExists): string
    {
        if ($path === null || $path === '') {
            return self::STATUS_NONE;
        }

        if ($dynamic) {
            return self::STATUS_UNCHECKED;
        }

        $url = self::resolveUrl($path);
        if ($url === null) {
            return self::STATUS_UNCHECKED;
        }

        return $pageExists($url) ? self::STATUS_OK : self::STATUS_MISSING;
    }

    /**
     * The absolute URL a configured path redirects to, or null when it
     * cannot be checked (a query string, another host, a relative path).
     *
     * A root-relative path is resolved against the host, not the home URL,
     * because that is where the browser goes when TOFU redirects to it.
     *
     * @param string $path
     * @return ?string
     */
    public static function resolveUrl(string $path): ?string
    {
        if ($path === '' || str_contains($path, '?')) {
            return null;
        }

        $home = wp_parse_url(home_url());
        $parts = wp_parse_url($path);
        if (!is_array($home) || !isset($home['host']) || !is_array($parts)) {
            return null;
        }

        $scheme = $home['scheme'] ?? 'http';
        $origin = $scheme . '://' . $home['host'] . (isset($home['port']) ? ':' . $home['port'] : '');

        if (isset($parts['host'])) {
            $port = $parts['port'] ?? null;
            if (strtolower($parts['host']) !== strtolower($home['host']) || $port !== ($home['port'] ?? null)) {
                return null;
            }

            return isset($parts['scheme']) ? $path : $scheme . ':' . $path;
        }

        return str_starts_with($path, '/') ? $origin . $path : null;
    }

    /**
     * Whether a mail template exists.
     *
     * @param ?string $slug The slug passed to get_template_part().
     * @param callable(string): bool $templateExists
     * @return string One of STATUS_OK, STATUS_MISSING or STATUS_NONE.
     */
    public static function templateStatus(?string $slug, callable $templateExists): string
    {
        if ($slug === null || $slug === '') {
            return self::STATUS_NONE;
        }

        return $templateExists($slug) ? self::STATUS_OK : self::STATUS_MISSING;
    }

    /**
     * A field's rules, one entry per rule, in the order they run.
     *
     * The string form is split on `|` the way RuleParser splits it; the
     * array form is written back as `name:param,param`.
     *
     * @param mixed $definition
     * @return string[]
     */
    public static function ruleList(mixed $definition): array
    {
        if ($definition === null) {
            return [];
        }

        if (is_string($definition)) {
            return array_values(array_filter(array_map('trim', explode('|', $definition)), static fn (string $rule): bool => $rule !== ''));
        }

        if (!is_array($definition)) {
            return [self::shortType($definition)];
        }

        $rules = [];
        foreach ($definition as $name => $value) {
            if (is_int($name)) {
                $rules[] = is_string($value) ? trim($value) : self::shortType($value);
                continue;
            }

            if ($value === null || $value === true) {
                $rules[] = $name;
            } elseif (is_scalar($value)) {
                $rules[] = $name . ':' . (is_bool($value) ? 'false' : (string) $value);
            } elseif (is_array($value)) {
                $params = [];
                foreach ($value as $key => $param) {
                    $param = is_scalar($param) ? (string) $param : self::shortType($param);
                    $params[] = is_string($key) ? $key . '=' . $param : $param;
                }
                $rules[] = $name . ':' . implode(',', $params);
            } else {
                $rules[] = $name;
            }
        }

        return $rules;
    }

    /**
     * `Name <email>`, or the address alone.
     *
     * @param string $email
     * @param string $name
     * @return string
     */
    public static function addressForDisplay(string $email, string $name): string
    {
        return $name !== '' ? sprintf('%s <%s>', $name, $email) : $email;
    }

    /**
     * @param string $tone One of the TONE_* constants.
     * @param string $label
     * @return string
     */
    public static function badgeHtml(string $tone, string $label): string
    {
        $tone = $tone === self::TONE_WARNING ? self::TONE_WARNING : self::TONE_INFO;

        return sprintf('<span class="tofu-badge tofu-badge--%s">%s</span>', esc_attr($tone), esc_html($label));
    }

    /**
     * The class name without its namespace, for rule objects and closures.
     *
     * @param mixed $value
     * @return string
     */
    protected static function shortType(mixed $value): string
    {
        $type = get_debug_type($value);
        $position = strrpos($type, '\\');

        return $position === false ? $type : substr($type, $position + 1);
    }
}
