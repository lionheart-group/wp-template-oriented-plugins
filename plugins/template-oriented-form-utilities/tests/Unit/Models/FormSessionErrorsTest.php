<?php

namespace TofuPlugin\Tests\Unit\Models;

use TofuPlugin\Consts;
use TofuPlugin\Helpers\Encryptor;
use TofuPlugin\Models\Form;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;

/**
 * Pins that validation errors are shown once.
 *
 * Errors used to stay in the session until the form was sent, so every reload
 * of the input page showed the same errors again. Now the session loses its
 * errors on the first GET that reaches `template_include`, while that
 * request's templates still see them in memory. A request redirected during
 * `template_redirect` (redirect_canonical() and the like) exits before that
 * hook, so it must not clear them.
 */
class FormSessionErrorsTest extends BaseTestCase
{
    /** @var object */
    private $originalWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalWpdb = $GLOBALS['wpdb'];
        $_COOKIE[Consts::SESSION_COOKIE_KEY] = 'existing-session-key';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $GLOBALS['wpdb'] = $this->originalWpdb;
        unset($_COOKIE[Consts::SESSION_COOKIE_KEY], $_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    /**
     * Replace $wpdb with one that holds a single session row and records
     * every write to it.
     *
     * @param array<string, mixed> $session
     */
    private function useSession(array $session): object
    {
        $wpdb = new class (Encryptor::encrypt($session)) {
            public $prefix = 'wp_';
            public $insert_id = 0;
            /** @var array<int, array<string, mixed>> */
            public array $writes = [];

            public function __construct(public string $row)
            {
            }

            public function insert($table, $data, $format = null)
            {
                $this->writes[] = $data;
                return 1;
            }

            public function update($table, $data, $where, $format = null, $where_format = null)
            {
                $this->writes[] = $data;
                return 1;
            }

            public function prepare($query, ...$args)
            {
                return $query;
            }

            public function get_var($query, $x = 0, $y = 0)
            {
                // Session::exists() counts the row; Session::get() reads it.
                return str_contains($query, 'COUNT(*)') ? '1' : $this->row;
            }

            public function get_results($query, $output = OBJECT)
            {
                return [];
            }
        };

        $GLOBALS['wpdb'] = $wpdb;

        return $wpdb;
    }

    private function makeForm(): Form
    {
        return new Form(new FormConfig(
            key:        'contact',
            name:       'Contact Form',
            template:   null,
            mail:       new MailConfig(
                fromEmail:  'noreply@example.com',
                fromName:   'Test',
                recipients: new MailRecipientsCollection([
                    new MailRecipientsConfig(
                        recipientEmail: 'admin@example.com',
                        subject:        'Test',
                        mailBody:       'Test body',
                    ),
                ]),
            ),
            validation: new ValidationConfig(
                allows: ['name', 'email'],
                rules:  ['name' => 'required', 'email' => 'required|email'],
                names:  ['name' => 'Name', 'email' => 'Email'],
            ),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function savedSession(object $wpdb): array
    {
        $this->assertCount(1, $wpdb->writes, 'the session must be saved exactly once');

        return Encryptor::decrypt($wpdb->writes[0]['session_value']);
    }

    public function testFirstGetKeepsErrorsInMemoryAndDropsThemFromTheSession(): void
    {
        $wpdb = $this->useSession([
            'values' => ['email' => 'not-an-email'],
            'errors' => ['name' => ['Name is required.'], 'email' => ['Email is invalid.']],
        ]);

        $form = $this->makeForm();
        do_action('template_redirect');
        $template = apply_filters('template_include', '/theme/page-contact.php');

        $this->assertSame('/theme/page-contact.php', $template, 'the template must be handed back unchanged');
        $this->assertSame(['Name is required.'], $form->getErrors()->getFieldErrorMessages('name'));

        $saved = $this->savedSession($wpdb);
        $this->assertSame([], $saved['errors']);
        $this->assertSame(['email' => 'not-an-email'], $saved['values']);
    }

    public function testPostDoesNotClearTheErrors(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $wpdb = $this->useSession([
            'values' => ['name' => ''],
            'errors' => ['name' => ['Name is required.']],
        ]);

        $this->makeForm();
        do_action('template_redirect');
        apply_filters('template_include', '/theme/page-contact.php');

        $this->assertSame([], $wpdb->writes);
    }

    public function testHeadDoesNotClearTheErrors(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $wpdb = $this->useSession([
            'values' => ['name' => ''],
            'errors' => ['name' => ['Name is required.']],
        ]);

        $this->makeForm();
        do_action('template_redirect');
        apply_filters('template_include', '/theme/page-contact.php');

        $this->assertSame([], $wpdb->writes);
    }

    public function testSessionWithoutErrorsIsNotSavedAgain(): void
    {
        $wpdb = $this->useSession([
            'values' => ['name' => 'Taro'],
            'errors' => [],
        ]);

        $this->makeForm();
        do_action('template_redirect');
        apply_filters('template_include', '/theme/page-contact.php');

        $this->assertSame([], $wpdb->writes);
    }

    public function testRequestRedirectedBeforeTheTemplateKeepsTheErrors(): void
    {
        // e.g. /contact -> /contact/ by redirect_canonical() on
        // template_redirect: WordPress exits before template_include, so the
        // errors must still be there for the page the visitor lands on.
        $wpdb = $this->useSession([
            'values' => ['name' => ''],
            'errors' => ['name' => ['Name is required.']],
        ]);

        $this->makeForm();
        do_action('template_redirect');

        $this->assertSame([], $wpdb->writes);
    }
}
