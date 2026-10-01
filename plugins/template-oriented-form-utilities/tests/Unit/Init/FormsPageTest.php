<?php

namespace TofuPlugin\Tests\Unit\Init;

use TofuPlugin\Init\FormsPage;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\TemplateConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;

class FormsPageTest extends BaseTestCase
{
    /**
     * @param MailRecipientsConfig[] $recipients
     */
    private function makeConfig(
        array $recipients = [],
        ?TemplateConfig $template = null,
        bool $dynamicTemplate = false,
        bool $recaptchaEnabled = false,
        bool $turnstileEnabled = false,
        ?\Closure $after = null,
    ): FormConfig {
        return new FormConfig(
            key: 'contact',
            name: 'Contact',
            template: $template ?? new TemplateConfig(
                inputPath: '/contact/',
                resultPath: '/contact/result/',
                confirmPath: '/contact/confirm/',
            ),
            mail: new MailConfig(
                fromEmail: 'noreply@example.com',
                fromName: 'Site',
                recipients: new MailRecipientsCollection($recipients !== [] ? $recipients : [
                    new MailRecipientsConfig(recipientEmail: 'admin@example.com', subject: 'New enquiry', mailBody: 'Body'),
                ]),
            ),
            validation: new ValidationConfig(
                allows: ['name', 'email'],
                rules: ['name' => 'required', 'email' => 'required|email'],
                names: ['name' => 'Name', 'email' => 'Email'],
                after: $after,
            ),
            recaptchaEnabled: $recaptchaEnabled,
            turnstileEnabled: $turnstileEnabled,
            dynamicTemplate: $dynamicTemplate,
        );
    }

    public function testScreenId(): void
    {
        $this->assertSame('tools_page_template-oriented-form-utilities', FormsPage::screenId());
    }

    public function testRegisterHooksTheMenuAndTheStyles(): void
    {
        FormsPage::register();

        $this->assertSame(10, has_action('admin_menu', [FormsPage::class, 'addMenuPage']));
        $this->assertSame(10, has_action('admin_enqueue_scripts', [FormsPage::class, 'enqueueStyles']));
    }

    public function testBadgeHtmlIsEscapedAndSurvivesKses(): void
    {
        $html = FormsPage::badgeHtml(FormsPage::TONE_WARNING, '<b>Page</b> not found');

        $this->assertSame('<span class="tofu-badge tofu-badge--warning">&lt;b&gt;Page&lt;/b&gt; not found</span>', $html);
        $this->assertSame($html, wp_kses($html, FormsPage::ALLOWED_HTML));
    }

    public function testAnUnknownToneIsInfo(): void
    {
        $this->assertStringContainsString('tofu-badge--info', FormsPage::badgeHtml('"><script>', 'x'));
    }

    public function testPlaceholders(): void
    {
        $this->assertSame(['email', 'name'], FormsPage::placeholders('{email}, { name } and {email} again'));
        $this->assertSame([], FormsPage::placeholders('admin@example.com'));
        $this->assertSame([], FormsPage::placeholders('{} and { }'));
    }

    public function testUnknownPlaceholdersCoverEveryReplacedText(): void
    {
        $config = $this->makeConfig([
            new MailRecipientsConfig(
                recipientEmail: '{email}',
                recipientCcEmail: '{cc}',
                recipientBccEmail: '{ bcc }',
                subject: 'From {name} at {company}',
                mailBody: 'Hello {name}, {zip}',
            ),
            new MailRecipientsConfig(recipientEmail: 'admin@example.com', subjectPath: 'mail/{ignored}', mailBodyPath: 'mail/{ignored}'),
        ]);

        $this->assertSame(['cc', 'bcc', 'company', 'zip'], FormsPage::unknownPlaceholders($config));
    }

    public function testNoUnknownPlaceholdersWhenEveryOneIsAllowed(): void
    {
        $config = $this->makeConfig([
            new MailRecipientsConfig(recipientEmail: '{email}', subject: 'Thanks, {name}', mailBodyPath: 'mail/body'),
        ]);

        $this->assertSame([], FormsPage::unknownPlaceholders($config));
    }

    public function testPathStatusOfAnExistingPage(): void
    {
        $checked = [];
        $exists = function (string $url) use (&$checked): bool {
            $checked[] = $url;
            return true;
        };

        $this->assertSame(FormsPage::STATUS_OK, FormsPage::pathStatus('/contact/', false, $exists));
        $this->assertSame(FormsPage::STATUS_OK, FormsPage::pathStatus('http://example.com/contact/', false, $exists));
        $this->assertSame(['http://example.com/contact/', 'http://example.com/contact/'], $checked);
    }

    public function testPathStatusOfAMissingPage(): void
    {
        $this->assertSame(FormsPage::STATUS_MISSING, FormsPage::pathStatus('/contact/', false, fn () => false));
    }

    public function testPathStatusOfADynamicTemplateIsUnchecked(): void
    {
        $this->assertSame(FormsPage::STATUS_UNCHECKED, FormsPage::pathStatus('/contact/', true, fn () => false));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function uncheckablePaths(): array
    {
        return [
            'query string'  => ['/?page_id=2'],
            'external host' => ['https://forms.example.org/contact/'],
            'other port'    => ['http://example.com:8080/contact/'],
            'relative'      => ['contact/'],
        ];
    }

    /**
     * @dataProvider uncheckablePaths
     */
    public function testPathStatusOfAnUncheckablePath(string $path): void
    {
        $this->assertSame(FormsPage::STATUS_UNCHECKED, FormsPage::pathStatus($path, false, function (): bool {
            $this->fail('The page lookup must not run for a path that cannot be checked.');
        }));
    }

    public function testPathStatusOfNoPath(): void
    {
        $this->assertSame(FormsPage::STATUS_NONE, FormsPage::pathStatus(null, false, fn () => true));
        $this->assertSame(FormsPage::STATUS_NONE, FormsPage::pathStatus('', true, fn () => true));
    }

    public function testTemplateStatus(): void
    {
        $this->assertSame(FormsPage::STATUS_OK, FormsPage::templateStatus('form/contact/admin-body', fn (string $slug) => $slug === 'form/contact/admin-body'));
        $this->assertSame(FormsPage::STATUS_MISSING, FormsPage::templateStatus('form/missing', fn () => false));
        $this->assertSame(FormsPage::STATUS_NONE, FormsPage::templateStatus(null, fn () => true));
    }

    public function testWarningsAreEmptyForAHealthyForm(): void
    {
        $this->assertSame([], FormsPage::warnings($this->makeConfig(), fn () => true, fn () => true, false, false));
    }

    public function testWarningsListEveryProblem(): void
    {
        $config = $this->makeConfig(
            recipients: [
                new MailRecipientsConfig(recipientEmail: '{email}', subject: 'From {company}', mailBodyPath: 'form/missing-body'),
            ],
            recaptchaEnabled: true,
            turnstileEnabled: true,
        );

        $warnings = FormsPage::warnings($config, fn (string $url) => $url !== 'http://example.com/contact/confirm/', fn () => false, false, false);

        $this->assertSame([
            'Confirm page not found: /contact/confirm/',
            'Mail template not found: form/missing-body',
            'reCAPTCHA is enabled, but no reCAPTCHA configuration is registered (Form::setRecaptcha()).',
            'Turnstile is enabled, but no Turnstile configuration is registered (Form::setTurnstile()).',
            'Placeholders that are not allowed fields: {company}. They stay as they are in the mail.',
        ], $warnings);
    }

    public function testRegisteredBotProtectionIsNotAWarning(): void
    {
        $config = $this->makeConfig(recaptchaEnabled: true, turnstileEnabled: true);

        $this->assertSame([], FormsPage::warnings($config, fn () => true, fn () => true, true, true));
    }

    public function testDynamicTemplatesAreNotReportedMissing(): void
    {
        $config = $this->makeConfig(dynamicTemplate: true);

        $this->assertSame([], FormsPage::warnings($config, fn () => false, fn () => true, false, false));
    }

    public function testTheAfterHookIsMentionedForUnknownPlaceholders(): void
    {
        $config = $this->makeConfig(
            recipients: [new MailRecipientsConfig(recipientEmail: 'admin@example.com', subject: '{company}', mailBody: 'Body')],
            after: function (): void {},
        );

        $this->assertSame(
            ['Placeholders that are not allowed fields: {company}. They stay as they are in the mail unless the "after" hook sets them.'],
            FormsPage::warnings($config, fn () => true, fn () => true, false, false)
        );
    }

    public function testRuleList(): void
    {
        $this->assertSame(['required', 'max:100'], FormsPage::ruleList('required| max:100|'));
        $this->assertSame(['required', 'max:200', 'regex:/^(a|b)$/', 'in:a,b', 'nullable'], FormsPage::ruleList([
            'required',
            'max' => 200,
            'regex' => '/^(a|b)$/',
            'in' => ['a', 'b'],
            'nullable' => true,
        ]));
        $this->assertSame([], FormsPage::ruleList(null));
    }

    public function testAddressForDisplay(): void
    {
        $this->assertSame('Site <noreply@example.com>', FormsPage::addressForDisplay('noreply@example.com', 'Site'));
        $this->assertSame('noreply@example.com', FormsPage::addressForDisplay('noreply@example.com', ''));
    }
}
