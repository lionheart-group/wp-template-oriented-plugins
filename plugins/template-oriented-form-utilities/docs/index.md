# TOFU (Template Oriented File Utility) documentation

## Settings

TOFU can be configured in your WordPress theme's `functions.php` file using the following settings:

- [FormConfig](settings/formconfig.md)
  - [TemplateConfig](settings/templateconfig.md)
  - [MailConfig](settings/mailconfig.md)
    - [MailRecipientsCollection](settings/mailrecipientscollection.md)
    - [MailRecipientsConfig](settings/mailrecipientsconfig.md)
  - [ValidationConfig](settings/validationconfig.md)
  - [ReCAPTCHAConfig](settings/recaptchaconfig.md)
  - [TurnstileConfig](settings/turnstileconfig.md)

Here's a complete example showing how to set up a form with all configurations:

[Example configuration](settings/example.md)

## Pages

[Page Templates instructions](pages/index.md)

TOFU uses separate templates for each step of the form process:

- [Input Page Template](pages/input.md)
- [Confirm Page Template](pages/confirm.md)
- [Result Page Template](pages/result.md)
- [Embedding One Form on Many Pages](pages/multi-page-embeds.md)

## AJAX / Headless Mode

[AJAX / Headless Mode overview](ajax/index.md)

TOFU includes an opt-in WP REST API layer for JavaScript-driven or headless frontends:

- [Vanilla JavaScript](ajax/vanilla-js.md)
- [React](ajax/react.md)
- [Vue 3](ajax/vue.md)
- [Headless / Cross-Origin Setup](ajax/headless.md)

