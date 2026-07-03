(function () {
    document.querySelectorAll('[data-unio-table-pager-limit]').forEach(function (sel) {
        if (sel.dataset.unioPagerBound) {
            return;
        }
        sel.dataset.unioPagerBound = '1';
        sel.addEventListener('change', function () {
            if (sel.value) {
                window.location.href = sel.value;
            }
        });
    });
})();
