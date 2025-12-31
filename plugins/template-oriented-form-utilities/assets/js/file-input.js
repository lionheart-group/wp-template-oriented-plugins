document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('button[data-tofu-target]')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                const target = button.getAttribute('data-tofu-target');
                if (!target) {
                    return;
                }

                document.querySelectorAll(`[data-tofu-field="${target}"]`)
                    .forEach(function (input) {
                        input.remove();
                    });
            });
        });
});
