document.addEventListener("submit", function (e) {
    // Check the triggering element to ensure it's the correct form
    const form = e.target;
    const formConfig = tofuRecaptchaConfig.forms.find(
        (f) => f.formId === form.id
    );
    if (!formConfig) {
        return; // Allow other forms to submit normally
    }

    if (typeof grecaptcha === "undefined") {
        console.error("reCAPTCHA library is not loaded.");
        return; // Allow form submission to proceed
    }

    if (e.defaultPrevented) {
        return; // Allow if already prevented
    }

    const inputField = document.getElementById(formConfig.inputId);
    if (!inputField) {
        return; // Allow form submission to proceed
    }

    e.preventDefault(); // Prevent the default form submission
    e.stopImmediatePropagation(); // Stop other listeners

    grecaptcha.ready(function () {
        grecaptcha
            .execute(tofuRecaptchaConfig.siteKey, { action: "submit" })
            .then(function (token) {
                inputField.value = token;
                form.submit(); // Now submit the form programmatically
            })
            .catch(function (error) {
                console.error("reCAPTCHA execution failed.", error);
                alert("reCAPTCHA verification failed. Please try again.");
            });
    });
});
