# Vue 3

A complete example using the Vue 3 Composition API for a multi-step contact form.
Works both as a component in a WordPress theme and as a standalone Nuxt 3 page.

## Table of Contents

- [Setup](#setup)
- [Composable: `useTofu`](#composable-usetofu)
- [Input Step](#input-step)
- [Confirm Step](#confirm-step)
- [Result Step](#result-step)
- [Root `ContactForm` Component](#root-contactform-component)
- [reCAPTCHA with Vue](#recaptcha-with-vue)
- [File Upload with Vue](#file-upload-with-vue)
- [Nuxt 3 Notes](#nuxt-3-notes)

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
                'name'    => 'required|max:200',
                'email'   => 'required|email',
                'message' => 'required|max:2000',
            ],
            names:   ['name' => 'Name', 'email' => 'Email', 'message' => 'Message'],
        ),
    ));
});
```

### Install dependencies (if using npm)

```bash
npm install vue
# or for Nuxt:
npx nuxi init my-app
```

---

## Composable: `useTofu`

```js
// composables/useTofu.js
import { ref } from 'vue';

export function useTofu(formKey) {
    const errors  = ref({});
    const loading = ref(false);

    const BASE = `/wp-json/tofu/v1/forms/${formKey}`;

    /** Fetch a fresh nonce for the given action ('input' | 'confirm'). */
    async function fetchNonce(action = 'input') {
        const res = await fetch(`${BASE}/nonce?action=${action}`, {
            credentials: 'include',
        });
        if (!res.ok) throw new Error('Could not load security token. Reload and try again.');
        return res.json(); // { nonce, field_name, action }
    }

    /**
     * Submit a form step.
     *
     * @param {'input'|'confirm'} step
     * @param {FormData|null}     formData  Pass null for the confirm step.
     * @returns {Promise<{success: boolean, next: 'confirm'|'result'|'input', errors?: object}>}
     */
    async function submit(step, formData = null) {
        loading.value = true;
        errors.value  = {};

        try {
            const { nonce, field_name } = await fetchNonce(step);

            const body = formData ?? new FormData();
            body.append(field_name, nonce);

            const res  = await fetch(`${BASE}/${step}`, {
                method: 'POST',
                body,
                credentials: 'include',
            });
            const data = await res.json();

            if (!data.success) {
                errors.value = data.errors ?? {};
            }
            return data;
        } finally {
            loading.value = false;
        }
    }

    return { errors, loading, submit };
}
```

---

## Input Step

```vue
<!-- components/InputStep.vue -->
<template>
    <form @submit.prevent="handleSubmit" novalidate>
        <div class="field">
            <label for="name">Name *</label>
            <input v-model="fields.name" type="text" id="name" name="name" />
            <ul v-if="errors.name" class="field-errors">
                <li v-for="(msg, i) in errors.name" :key="i">{{ msg }}</li>
            </ul>
        </div>

        <div class="field">
            <label for="email">Email *</label>
            <input v-model="fields.email" type="email" id="email" name="email" />
            <ul v-if="errors.email" class="field-errors">
                <li v-for="(msg, i) in errors.email" :key="i">{{ msg }}</li>
            </ul>
        </div>

        <div class="field">
            <label for="message">Message *</label>
            <textarea v-model="fields.message" id="message" name="message" rows="5" />
            <ul v-if="errors.message" class="field-errors">
                <li v-for="(msg, i) in errors.message" :key="i">{{ msg }}</li>
            </ul>
        </div>

        <button type="submit" :disabled="loading">
            {{ loading ? 'Sending…' : 'Next' }}
        </button>
    </form>
</template>

<script setup>
import { reactive } from 'vue';
import { useTofu }  from '../composables/useTofu';

const props = defineProps({ formKey: String });
const emit  = defineEmits(['success']);

const fields = reactive({ name: '', email: '', message: '' });
const { errors, loading, submit } = useTofu(props.formKey);

async function handleSubmit() {
    const body = new FormData();
    for (const [key, val] of Object.entries(fields)) {
        body.append(key, val);
    }
    const data = await submit('input', body);
    if (data.success) {
        emit('success', { next: data.next, values: { ...fields } });
    }
}
</script>
```

---

## Confirm Step

```vue
<!-- components/ConfirmStep.vue -->
<template>
    <div>
        <h2>Please confirm your details</h2>

        <dl>
            <dt>Name</dt>    <dd>{{ values.name }}</dd>
            <dt>Email</dt>   <dd>{{ values.email }}</dd>
            <dt>Message</dt> <dd>{{ values.message }}</dd>
        </dl>

        <p v-if="errors._session" class="error">
            Session expired. Please go back and resubmit.
        </p>

        <button @click="$emit('back')">← Back</button>
        <button @click="handleConfirm" :disabled="loading">
            {{ loading ? 'Sending…' : 'Confirm & Send' }}
        </button>
    </div>
</template>

<script setup>
import { useTofu } from '../composables/useTofu';

const props = defineProps({ formKey: String, values: Object });
const emit  = defineEmits(['success', 'back']);

const { errors, loading, submit } = useTofu(props.formKey);

async function handleConfirm() {
    const data = await submit('confirm');
    if (data.success) emit('success');
}
</script>
```

---

## Result Step

```vue
<!-- components/ResultStep.vue -->
<template>
    <div>
        <h2>Thank you!</h2>
        <p>Your message has been sent successfully.</p>
    </div>
</template>
```

---

## Root `ContactForm` Component

```vue
<!-- components/ContactForm.vue -->
<template>
    <div class="contact-form">
        <InputStep
            v-if="step === 'input'"
            :formKey="FORM_KEY"
            @success="onInputSuccess"
        />
        <ConfirmStep
            v-else-if="step === 'confirm'"
            :formKey="FORM_KEY"
            :values="submittedValues"
            @success="step = 'result'"
            @back="step = 'input'"
        />
        <ResultStep v-else />
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import InputStep   from './InputStep.vue';
import ConfirmStep from './ConfirmStep.vue';
import ResultStep  from './ResultStep.vue';

const FORM_KEY = 'contact';

const step            = ref('input');
const submittedValues = reactive({});

function onInputSuccess({ next, values }) {
    Object.assign(submittedValues, values);
    step.value = next;
}
</script>
```

---

## reCAPTCHA with Vue

Install the helper library:

```bash
npm install vue-recaptcha-v3
```

Register the plugin in `main.js` (or `plugins/recaptcha.client.js` for Nuxt):

```js
// main.js
import { VueReCaptcha } from 'vue-recaptcha-v3';

app.use(VueReCaptcha, { siteKey: 'YOUR_SITE_KEY' });
```

Use the composable in your `InputStep`:

```vue
<script setup>
import { useReCaptcha } from 'vue-recaptcha-v3';
import { useTofu }      from '../composables/useTofu';

const props = defineProps({ formKey: String });
const emit  = defineEmits(['success']);

const { executeRecaptcha } = useReCaptcha();
const { errors, loading, submit } = useTofu(props.formKey);

async function handleSubmit() {
    const token = await executeRecaptcha('submit');

    const body = new FormData(/* your form ref */);
    body.append('_tofu_recaptcha_token', token);

    const data = await submit('input', body);
    if (data.success) emit('success', { next: data.next });
}
</script>
```

---

## File Upload with Vue

```vue
<template>
    <form @submit.prevent="handleSubmit" novalidate>
        <!-- text fields … -->

        <div class="field">
            <label for="attachment">Attachment</label>
            <input
                type="file"
                id="attachment"
                accept=".pdf,image/*"
                @change="onFileChange"
            />
            <span v-if="selectedFile">Selected: {{ selectedFile }}</span>
            <ul v-if="errors.attachment" class="field-errors">
                <li v-for="(msg, i) in errors.attachment" :key="i">{{ msg }}</li>
            </ul>
        </div>

        <button type="submit" :disabled="loading">
            {{ loading ? 'Uploading…' : 'Next' }}
        </button>
    </form>
</template>

<script setup>
import { ref } from 'vue';
import { useTofu } from '../composables/useTofu';

const props      = defineProps({ formKey: String });
const emit       = defineEmits(['success']);
const formRef    = ref(null);
const fileInput  = ref(null);
const selectedFile = ref('');

const { errors, loading, submit } = useTofu(props.formKey);

function onFileChange(e) {
    selectedFile.value = e.target.files[0]?.name ?? '';
    fileInput.value    = e.target.files[0] ?? null;
}

async function handleSubmit() {
    const body = new FormData(formRef.value);
    // File is already in FormData via the <input type="file"> in the form
    const data = await submit('input', body);
    if (data.success) emit('success', { next: data.next });
}
</script>
```

> **Validation rules** in `ValidationConfig`:
> ```php
> rules: ['attachment' => 'custom_required_file|max_mb:5|mime_type:application/pdf,image/jpeg'],
> ```

---

## Nuxt 3 Notes

- Place composables in `composables/useTofu.js` (Nuxt auto-imports them)
- All fetch calls use `credentials: 'include'` (already in `useTofu`)
- For cross-origin (WP on a different domain), see the [Headless setup guide](./headless.md)
- To proxy WP REST API requests and avoid CORS, add to `nuxt.config.ts`:
  ```ts
  // nuxt.config.ts
  export default defineNuxtConfig({
      routeRules: {
          '/wp-json/**': { proxy: 'https://wp.example.com/wp-json/**' },
      },
  });
  ```
  With a proxy you can omit `corsOrigins` from `FormConfig` (same-origin from the browser's perspective).
- On the server side (SSR), do **not** call the TOFU REST API — cookies are browser-session-specific.
  Render the form shell on the server, then submit from the client.
