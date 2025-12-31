document.addEventListener('submit', async function (e) {
    // Check the triggering element to ensure it's the correct form
    const form = e.target;
    if (form.id !== tofuRecaptchaConfig.formId) {
        return; // Allow other forms to submit normally
    }

    if (typeof grecaptcha === 'undefined') {
        console.error('reCAPTCHA library is not loaded.');
        return; // Allow form submission to proceed
    }

    if (e.defaultPrevented) {
        return; // Allow if already prevented
    }

    const inputField = document.getElementById(tofuRecaptchaConfig.inputId);
    if (!inputField) {
        return; // Allow form submission to proceed
    }

    e.preventDefault(); // Prevent the default form submission
    e.stopImmediatePropagation(); // Stop other listeners

    grecaptcha.ready(function () {
        grecaptcha.execute(tofuRecaptchaConfig.siteKey, { action: 'submit' }).then(function (token) {
            inputField.value = token;
            form.submit(); // Now submit the form programmatically
        });
    });
});
