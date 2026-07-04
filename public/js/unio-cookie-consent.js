(function () {
    var KEY = 'unio-cookie-consent-v1';
    var banner = document.getElementById('unio-cookie-consent');
    var btn = document.getElementById('unio-cookie-accept');
    if (!banner || !btn) {
        return;
    }
    try {
        if (localStorage.getItem(KEY) === '1') {
            return;
        }
    } catch (e) {
        /* ignore */
    }
    banner.hidden = false;
    btn.addEventListener('click', function () {
        try {
            localStorage.setItem(KEY, '1');
        } catch (e) {
            /* ignore */
        }
        banner.hidden = true;
    });
})();
