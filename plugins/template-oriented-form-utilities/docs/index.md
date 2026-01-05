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

Here's a complete example showing how to set up a form with all configurations:

[Example configuration](settings/example.md)

## Pages

[Page Templates instructions](pages/index.md)

TOFU uses separate templates for each step of the form process:

- [Input Page Template](pages/input.md)
- [Confirm Page Template](pages/confirm.md)
- [Result Page Template](pages/result.md)
