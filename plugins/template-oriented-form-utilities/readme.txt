=== TOFU (Template-Oriented Form Utilities) ===
Contributors: lionheartgroup
Tags: forms, utilities, template-oriented
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.0.5
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Template-Oriented Form Utilities is a WordPress plugin that simplifies form creation, validation, and processing via template-based data management.

== Description ==

Template-Oriented Form Utilities (TOFU) is designed to streamline the process of creating and managing forms within WordPress themes and plugins. By adopting a template-oriented approach, TOFU allows developers to define form structures and behaviors using reusable templates, making it easier to manage/maintain with team collaboration through version control systems.

GitHub and documentation for this plugin can be found at:

[https://github.com/lionheart-group/template-oriented-form-utilities](https://github.com/lionheart-group/template-oriented-form-utilities)

== External services ==

This plugin relies on third-party services to protect your website from spam and automated attacks. Depending on your configuration, this plugin connects to the following services:

= Google reCAPTCHA =
* **Purpose:** Protecting forms from spam and bot abuse.
* **When data is sent:** When a page containing a reCAPTCHA-protected form is loaded or submitted.
* **Data sent:** IP address, mouse movements, browser/device information, and duration of stay.
* **Service Provider:** Google LLC.
* **Links:** [Google Privacy Policy](https://policies.google.com/privacy), [Google Terms of Service](https://policies.google.com/terms).

= Cloudflare Turnstile =
* **Purpose:** Privacy-focused alternative for bot protection and spam prevention.
* **When data is sent:** When a user interacts with a form protected by Turnstile.
* **Data sent:** Browser/device characteristics and interaction data (privacy-friendly, does not use cookies for tracking).
* **Service Provider:** Cloudflare, Inc.
* **Links:** [Cloudflare Privacy Policy](https://www.cloudflare.com/privacypolicy/), [Cloudflare Website Terms](https://www.cloudflare.com/website-terms/).

== Installation ==

1. From the WP admin panel, click "Plugins" -> "Add new".
2. In the browser input box, type "Template-Oriented Form Utilities".
3. Select the "Template-Oriented Form Utilities" plugin and click "Install".
4. Activate the plugin.

OR…

1. Download the plugin from this page.
2. Save the .zip file to a location on your computer.
3. Open the WP admin panel, and click "Plugins" -> "Add new".
4. Click "upload".. then browse to the .zip file downloaded from this page.
5. Click "Install".. and then "Activate plugin".

== Frequently Asked Questions ==



== Screenshots ==



== Changelog ==

* v0.0.1 - Initial release.
* v0.0.2 - Arranged required PHP version to 8.1, added external services section to the readme.
* v0.0.3
    - Implemented Ajax form submission and validation with reCAPTCHA and Turnstile support.
    - Fixed recaptcha issue when embedded multiple forms on the same page.
    - Replaced validation library from wixel/gump to somnambulist/validation.
* v0.0.4
    - Added optional database recording of form submissions (`saveToDatabase`), encrypted at
      rest, with an admin screen to view recorded data.
    - Added `Form::setTemplate()` to override a form's input/confirm/result URLs per visitor
      session, so a single registered form can be embedded on multiple pages (e.g. post
      templates); added `FormConfig::$dynamicTemplate` for forms configured this way.
* v0.0.5
    - The AJAX nonce endpoint (`GET /wp-json/tofu/v1/forms/{key}/nonce`) now also returns the
      reCAPTCHA/Turnstile site key when enabled, so cross-origin/headless clients no longer need
      to hardcode it separately from the server-side configuration.


== Upgrade Notice ==

= 0.0.3 =
**Breaking changes from v0.0.2:**

1. **Validation rules renamed** (somnambulist/validation replaces wixel/gump):
   - `required_file` → `custom_required_file`
   - `max_len` → `max`
   - `min_len` → `min`
   - `valid_email` → `email`
   - `numeric` stays the same
   - `filters` parameter has been removed from `ValidationConfig` — sanitize input in the `after` hook if needed.

2. **reCAPTCHA / Turnstile configuration moved to plugin level:**
   - Remove `recaptcha: new ReCAPTCHAConfig(...)` and `turnstile: new TurnstileConfig(...)` from `FormConfig`.
   - Call `Form::setRecaptcha(new ReCAPTCHAConfig(...))` once before registering forms.
   - Call `Form::setTurnstile(new TurnstileConfig(...))` once before registering forms.
   - Replace with `recaptchaEnabled: true` or `turnstileEnabled: true` in each `FormConfig`.

3. **Confirm step requires explicit opt-in:**
   - `confirmStep: true` must now be set explicitly in `FormConfig` to enable the confirm step.
   - For the traditional redirect flow, also set `template->confirmPath`.
   - `Form::embedScript()` is no longer needed on the confirm page template.
