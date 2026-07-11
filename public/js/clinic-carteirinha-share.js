(function () {
    function copyText(text, onDone) {
        if (!text) {
            return;
        }
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(text).then(onDone).catch(fallback);
            return;
        }
        fallback();

        function fallback() {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            try {
                document.execCommand('copy');
                onDone();
            } catch (e) {}
            document.body.removeChild(area);
        }
    }

    function showCopied(btn) {
        var icon = btn.querySelector('i');
        var prev = icon ? icon.className : '';
        btn.classList.add('is-copied');
        if (icon) {
            icon.className = 'fas fa-check';
        }
        window.setTimeout(function () {
            btn.classList.remove('is-copied');
            if (icon) {
                icon.className = prev;
            }
        }, 1600);
    }

    document.querySelectorAll('[data-clinic-carteirinha-share] [data-copy-text]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-copy-text') || '';
            copyText(text, function () {
                showCopied(btn);
            });
        });
    });
})();
