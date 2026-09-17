<?php

namespace TofuPlugin\Tests\Unit\Models;

use TofuPlugin\Consts;
use TofuPlugin\Helpers\Form as FormHelper;
use TofuPlugin\Models\Form;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\TemplateConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;

/**
 * The redirect flow used to mint its nonce against a bare 'input'/'confirm'
 * action, so a nonce issued for one form verified against every other form —
 * only the field name differed, and a field name is just a label an attacker
 * renames. The REST flow had always bound the form key into the action
 * (Consts::REST_NONCE_ACTION_FORMAT); these tests pin that the redirect flow
 * now does too.
 */
class FormNonceActionTest extends BaseTestCase
{
    private function makeForm(string $key): Form
    {
        $config = new FormConfig(
            key:        $key,
            name:       'Test Form',
            template:   new TemplateConfig(
                inputPath:  "/{$key}/",
                resultPath: "/{$key}/result/",
            ),
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
                allows: ['name'],
                rules:  ['name' => 'required'],
                names:  ['name' => 'Name'],
            ),
        );

        return new Form($config);
    }

    /**
     * Pull the rendered nonce out of the markup formClose() would emit, the
     * way a browser would then post it back.
     *
     * @return array<string, string>
     */
    private function postedNonce(string $key, string $action): array
    {
        $html = FormHelper::generateNonceField($key, $action);
        preg_match('/name="([^"]+)" value="([^"]+)"/', $html, $matches);

        return [$matches[1] => $matches[2]];
    }

    public function testTheRenderedNonceUsesTheDoubleUnderscoreFieldName(): void
    {
        $post = $this->postedNonce('contact', 'input');

        $this->assertSame(['__tofu_contact_nonce'], array_keys($post));
    }

    public function testAFreshlyRenderedNonceVerifies(): void
    {
        $form = $this->makeForm('contact');
        $action = sprintf(Consts::NONCE_ACTION_FORMAT, 'contact', 'input');

        $this->assertTrue($form->verifyNonceField($action, $this->postedNonce('contact', 'input')));
    }

    /**
     * The point of the change: form A's nonce must not open form B.
     */
    public function testANonceMintedForAnotherFormIsRejected(): void
    {
        $newsletter = $this->makeForm('newsletter');
        $action = sprintf(Consts::NONCE_ACTION_FORMAT, 'newsletter', 'input');

        // Harvested from the contact form's page, renamed to newsletter's field.
        $harvested = array_values($this->postedNonce('contact', 'input'))[0];
        $post = [sprintf(Consts::NONCE_FORMAT, 'newsletter') => $harvested];

        $this->assertFalse($newsletter->verifyNonceField($action, $post));
    }

    public function testANonceMintedForAnotherStepIsRejected(): void
    {
        $form = $this->makeForm('contact');
        $confirmAction = sprintf(Consts::NONCE_ACTION_FORMAT, 'contact', 'confirm');

        $this->assertFalse($form->verifyNonceField($confirmAction, $this->postedNonce('contact', 'input')));
    }

    public function testAGarbageNonceIsRejected(): void
    {
        $form = $this->makeForm('contact');
        $action = sprintf(Consts::NONCE_ACTION_FORMAT, 'contact', 'input');

        $this->assertFalse($form->verifyNonceField($action, [
            sprintf(Consts::NONCE_FORMAT, 'contact') => 'not-a-real-nonce',
        ]));
    }

    public function testAMissingNonceIsRejected(): void
    {
        $form = $this->makeForm('contact');
        $action = sprintf(Consts::NONCE_ACTION_FORMAT, 'contact', 'input');

        $this->assertFalse($form->verifyNonceField($action, ['name' => 'Taro']));
    }

    // -----------------------------------------------------------------
    // Transitional fallback — see issues/2026-09-17-13-58-21.md
    // -----------------------------------------------------------------

    /**
     * A visitor who loaded a form page before this change carries a nonce
     * under the old field name AND minted against the old bare action.
     * Rejecting it would answer their submission with a 403.
     */
    public function testAPreUpdateNonceStillVerifies(): void
    {
        $form = $this->makeForm('contact');
        $action = sprintf(Consts::NONCE_ACTION_FORMAT, 'contact', 'input');

        $legacyPost = [
            sprintf(Consts::LEGACY_NONCE_FORMAT, 'contact') => wp_create_nonce('input'),
        ];

        $this->assertTrue($form->verifyNonceField($action, $legacyPost, 'input'));
    }

    /**
     * The fallback must not become a hole of its own: without it being asked
     * for, an old-action nonce is still refused.
     */
    public function testAPreUpdateNonceIsRejectedWhenNoLegacyActionIsPassed(): void
    {
        $form = $this->makeForm('contact');
        $action = sprintf(Consts::NONCE_ACTION_FORMAT, 'contact', 'input');

        $legacyPost = [
            sprintf(Consts::LEGACY_NONCE_FORMAT, 'contact') => wp_create_nonce('input'),
        ];

        $this->assertFalse($form->verifyNonceField($action, $legacyPost));
    }

    /**
     * And the fallback must not reintroduce the cross-form hole it is there
     * to paper over: the legacy action is shared between forms, so this is
     * the one case that stays open until the fallback is removed. Pinning it
     * documents the exposure rather than hiding it.
     */
    public function testTheLegacyFallbackIsScopedToTheStepItIsGiven(): void
    {
        $form = $this->makeForm('contact');
        $action = sprintf(Consts::NONCE_ACTION_FORMAT, 'contact', 'input');

        $confirmNonce = [
            sprintf(Consts::LEGACY_NONCE_FORMAT, 'contact') => wp_create_nonce('confirm'),
        ];

        // Passing 'input' as the legacy action must not accept a 'confirm' one.
        $this->assertFalse($form->verifyNonceField($action, $confirmNonce, 'input'));
    }
}
