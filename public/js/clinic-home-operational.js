(function () {
    document.querySelectorAll('[data-copy-booking-url]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var el = document.querySelector('[data-booking-url]');
            var url = el ? (el.getAttribute('data-booking-url') || el.textContent || '').trim() : '';
            if (!url) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    btn.textContent = 'Copiado!';
                    setTimeout(function () { btn.textContent = 'Copiar link'; }, 1800);
                });
                return;
            }
            window.prompt('Copie o link:', url);
        });
    });
})();
