@if (config('services.recaptcha.enabled'))
    @once
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}" async defer></script>
    @endonce
    <input type="hidden" name="g-recaptcha-response" data-recaptcha-v3>
    <script>
        (function () {
            const input = document.currentScript.previousElementSibling;
            const form = input?.closest('form');
            if (!input || !form) return;
            form.addEventListener('submit', function (event) {
                if (form.dataset.recaptchaReady === 'true') return;
                event.preventDefault();
                grecaptcha.ready(function () {
                    grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', { action: 'submit' })
                        .then(function (token) {
                            input.value = token;
                            form.dataset.recaptchaReady = 'true';
                            form.submit();
                        });
                });
            });
        }());
    </script>
@endif