# React

A complete example using React hooks for a multi-step contact form.  
Works both as a component in a WordPress theme and as a standalone Next.js page.

## Table of Contents

- [Setup](#setup)
- [Custom Hook: `useTofu`](#custom-hook-usetofu)
- [Input Step Component](#input-step-component)
- [Confirm Step Component](#confirm-step-component)
- [Result Step Component](#result-step-component)
- [Root `<ContactForm>` Component](#root-contactform-component)
- [reCAPTCHA with React](#recaptcha-with-react)
- [File Upload with React](#file-upload-with-react)

---

## Setup

### WordPress (`functions.php`)

```php
add_action('init', function () {
    \TofuPlugin\Helpers\Form::register(new \TofuPlugin\Structure\FormConfig(
        key:         'contact',
        name:        'Contact Form',
        ajaxEnabled: true,
        template: new \TofuPlugin\Structure\TemplateConfig(
            inputPath:   '/contact/',
            confirmPath: '/contact/confirm/',  // remove to skip confirm step
            resultPath:  '/contact/result/',
        ),
        mail:       $mailConfig,
        validation: new \TofuPlugin\Structure\ValidationConfig(
            allows:  ['name', 'email', 'message'],
            rules:   [
                'name'    => 'required|max_len:200',
                'email'   => 'required|valid_email',
                'message' => 'required|max_len:2000',
            ],
            filters: ['name' => 'trim|sanitize_string', 'email' => 'trim|sanitize_email|lower_case', 'message' => 'trim|sanitize_string'],
            names:   ['name' => 'Name', 'email' => 'Email', 'message' => 'Message'],
        ),
    ));
});
```

### Install dependencies (if using npm)

```bash
npm install react react-dom
```

---

## Custom Hook: `useTofu`

Encapsulates nonce fetching and form submission logic.

```jsx
// hooks/useTofu.js
import { useState, useCallback } from 'react';

const BASE = (key) => `/wp-json/tofu/v1/forms/${key}`;

export function useTofu(formKey) {
    const [errors,  setErrors]  = useState({});
    const [loading, setLoading] = useState(false);

    /** Fetch a fresh nonce for the given action ('input' | 'confirm'). */
    const fetchNonce = useCallback(async (action = 'input') => {
        const res = await fetch(`${BASE(formKey)}/nonce?action=${action}`, {
            credentials: 'include',
        });
        if (!res.ok) throw new Error('Could not load security token. Reload and try again.');
        return res.json(); // { nonce, field_name, action }
    }, [formKey]);

    /**
     * Submit a form step.
     *
     * @param {'input'|'confirm'} step
     * @param {FormData|null}     formData  Pass null for the confirm step (no user data needed).
     * @returns {Promise<{success: boolean, redirect: string}>}
     */
    const submit = useCallback(async (step, formData = null) => {
        setLoading(true);
        setErrors({});

        try {
            const { nonce, field_name } = await fetchNonce(step);

            const body = formData ?? new FormData();
            body.append(field_name, nonce);

            const res  = await fetch(`${BASE(formKey)}/${step}`, {
                method: 'POST',
                body,
                credentials: 'include',
            });
            const data = await res.json();

            if (!data.success) {
                setErrors(data.errors ?? {});
            }
            return data;
        } finally {
            setLoading(false);
        }
    }, [formKey, fetchNonce]);

    return { errors, loading, submit, setErrors };
}
```

---

## Input Step Component

```jsx
// components/InputStep.jsx
import { useRef } from 'react';
import { useTofu } from '../hooks/useTofu';

function FieldErrors({ errors, field }) {
    if (!errors[field]?.length) return null;
    return (
        <ul className="field-errors">
            {errors[field].map((msg, i) => <li key={i}>{msg}</li>)}
        </ul>
    );
}

export function InputStep({ formKey, onSuccess }) {
    const formRef = useRef(null);
    const { errors, loading, submit } = useTofu(formKey);

    async function handleSubmit(e) {
        e.preventDefault();
        const data = await submit('input', new FormData(formRef.current));
        if (data.success) {
            onSuccess(data.redirect);
        }
    }

    return (
        <form ref={formRef} onSubmit={handleSubmit} noValidate>
            <div className="field">
                <label htmlFor="name">Name *</label>
                <input type="text" id="name" name="name" />
                <FieldErrors errors={errors} field="name" />
            </div>

            <div className="field">
                <label htmlFor="email">Email *</label>
                <input type="email" id="email" name="email" />
                <FieldErrors errors={errors} field="email" />
            </div>

            <div className="field">
                <label htmlFor="message">Message *</label>
                <textarea id="message" name="message" rows={5} />
                <FieldErrors errors={errors} field="message" />
            </div>

            <button type="submit" disabled={loading}>
                {loading ? 'Sending…' : 'Next'}
            </button>
        </form>
    );
}
```

---

## Confirm Step Component

```jsx
// components/ConfirmStep.jsx
import { useTofu } from '../hooks/useTofu';

export function ConfirmStep({ formKey, values, onSuccess, onBack }) {
    const { errors, loading, submit } = useTofu(formKey);

    async function handleConfirm() {
        const data = await submit('confirm');
        if (data.success) {
            onSuccess(data.redirect);
        }
    }

    return (
        <div>
            <h2>Please confirm your details</h2>

            <dl>
                <dt>Name</dt>    <dd>{values.name}</dd>
                <dt>Email</dt>   <dd>{values.email}</dd>
                <dt>Message</dt> <dd>{values.message}</dd>
            </dl>

            {errors._session && (
                <p className="error">Session expired. Please go back and resubmit.</p>
            )}

            <button onClick={onBack}>← Back</button>
            <button onClick={handleConfirm} disabled={loading}>
                {loading ? 'Sending…' : 'Confirm & Send'}
            </button>
        </div>
    );
}
```

---

## Result Step Component

```jsx
// components/ResultStep.jsx
export function ResultStep() {
    return (
        <div>
            <h2>Thank you!</h2>
            <p>Your message has been sent successfully.</p>
        </div>
    );
}
```

---

## Root `<ContactForm>` Component

Manages the `step` state and stores submitted values for the confirm page.

```jsx
// components/ContactForm.jsx
import { useState } from 'react';
import { InputStep }   from './InputStep';
import { ConfirmStep } from './ConfirmStep';
import { ResultStep }  from './ResultStep';

const FORM_KEY = 'contact';

export function ContactForm() {
    // 'input' | 'confirm' | 'result'
    const [step,     setStep]     = useState('input');
    // store values to display on confirm page
    const [values,   setValues]   = useState({});
    // the redirect URL returned by the /input endpoint
    const [redirect, setRedirect] = useState(null);

    function handleInputSuccess(redirectUrl) {
        // If WP returns a confirm URL, show confirm step
        if (redirectUrl && redirectUrl !== window.location.pathname) {
            // Capture current form values to display in confirm step
            const form = document.querySelector('form');
            if (form) {
                const fd = new FormData(form);
                setValues(Object.fromEntries(fd.entries()));
            }
            setRedirect(redirectUrl);
            setStep('confirm');
        } else {
            setStep('result');
        }
    }

    function handleConfirmSuccess() {
        setStep('result');
    }

    return (
        <div className="contact-form">
            {step === 'input' && (
                <InputStep
                    formKey={FORM_KEY}
                    onSuccess={handleInputSuccess}
                />
            )}
            {step === 'confirm' && (
                <ConfirmStep
                    formKey={FORM_KEY}
                    values={values}
                    onSuccess={handleConfirmSuccess}
                    onBack={() => setStep('input')}
                />
            )}
            {step === 'result' && <ResultStep />}
        </div>
    );
}
```

---

## reCAPTCHA with React

Install the helper library:

```bash
npm install react-google-recaptcha-v3
```

Wrap your app with the provider and execute the token before submission:

```jsx
// app.jsx (or _app.jsx in Next.js)
import { GoogleReCaptchaProvider } from 'react-google-recaptcha-v3';

export default function App() {
    return (
        <GoogleReCaptchaProvider reCaptchaKey="YOUR_SITE_KEY">
            <ContactForm />
        </GoogleReCaptchaProvider>
    );
}
```

```jsx
// In InputStep.jsx
import { useGoogleReCaptcha } from 'react-google-recaptcha-v3';

export function InputStep({ formKey, onSuccess }) {
    const { executeRecaptcha } = useGoogleReCaptcha();
    const { errors, loading, submit } = useTofu(formKey);

    async function handleSubmit(e) {
        e.preventDefault();
        const body = new FormData(e.target);

        if (executeRecaptcha) {
            const token = await executeRecaptcha('submit');
            body.append('_tofu_recaptcha_token', token);
        }

        const data = await submit('input', body);
        if (data.success) onSuccess(data.redirect);
    }

    // … rest of component
}
```

---

## File Upload with React

```jsx
import { useState, useRef } from 'react';

export function InputStep({ formKey, onSuccess }) {
    const formRef = useRef(null);
    const [fileName, setFileName] = useState('');
    const { errors, loading, submit } = useTofu(formKey);

    async function handleSubmit(e) {
        e.preventDefault();
        const data = await submit('input', new FormData(formRef.current));
        if (data.success) onSuccess(data.redirect);
    }

    return (
        <form ref={formRef} onSubmit={handleSubmit} noValidate>
            {/* text fields … */}

            <div className="field">
                <label htmlFor="attachment">Attachment</label>
                <input
                    type="file"
                    id="attachment"
                    name="attachment"
                    accept=".pdf,image/*"
                    onChange={(e) => setFileName(e.target.files[0]?.name ?? '')}
                />
                {fileName && <span>Selected: {fileName}</span>}
                {errors.attachment?.map((msg, i) => <p key={i} className="error">{msg}</p>)}
            </div>

            <button type="submit" disabled={loading}>
                {loading ? 'Uploading…' : 'Next'}
            </button>
        </form>
    );
}
```

> **Note** — File validation rules in `ValidationConfig`:
> ```php
> rules: ['attachment' => 'custom_required_file|max_mb:5|mime_type:application/pdf,image/jpeg'],
> ```

---

## Next.js notes

- Use `credentials: 'include'` in all fetch calls (already in `useTofu`)
- For cross-origin (WP on a different domain), see the [Headless setup guide](./headless.md)
- Add your WP REST API domain to `next.config.js` `rewrites` if you want to proxy requests
  and avoid CORS entirely:
  ```js
  // next.config.js
  module.exports = {
      async rewrites() {
          return [
              { source: '/wp-json/:path*', destination: 'https://wp.example.com/wp-json/:path*' },
          ];
      },
  };
  ```
  With a proxy you can omit `corsOrigins` from `FormConfig` (same-origin from the browser's perspective).
