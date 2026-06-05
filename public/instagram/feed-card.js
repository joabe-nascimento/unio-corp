(function () {
    'use strict';

    var CARD_W = 1080;
    var CARD_H = 1080;

    function toast(msg) {
        var el = document.getElementById('igToast');
        if (!el) return;
        el.textContent = msg;
        el.classList.add('is-visible');
        clearTimeout(toast._t);
        toast._t = setTimeout(function () { el.classList.remove('is-visible'); }, 3200);
    }

    function waitImages(root) {
        var imgs = root.querySelectorAll('img');
        return Promise.all(Array.prototype.map.call(imgs, function (img) {
            if (img.complete && img.naturalWidth > 0) return Promise.resolve();
            return new Promise(function (resolve) {
                img.onload = resolve;
                img.onerror = resolve;
            });
        }));
    }

    function prepareCaptureStage(card) {
        var scaleWrap = card.closest('.ig-card-scale');
        if (!scaleWrap) return { restore: function () {} };

        var saved = {
            wrapCss: scaleWrap.style.cssText,
            cardCss: card.style.cssText,
        };

        scaleWrap.style.cssText = [
            'position:fixed',
            'left:-1200px',
            'top:0',
            'transform:none',
            'transform-origin:top left',
            'opacity:1',
            'visibility:visible',
            'z-index:-1',
            'pointer-events:none',
            'margin:0',
            'padding:0',
        ].join(';');

        card.style.cssText = [
            'width:' + CARD_W + 'px',
            'height:' + CARD_H + 'px',
            'transform:none',
            'margin:0',
            'box-shadow:none',
        ].join(';');

        return {
            restore: function () {
                scaleWrap.style.cssText = saved.wrapCss;
                card.style.cssText = saved.cardCss;
            },
        };
    }

    async function captureCard(card) {
        if (typeof htmlToImage === 'undefined') {
            throw new Error('html-to-image não carregou');
        }

        if (window.IgIcons) {
            IgIcons.inlineIcons(card);
        }

        await document.fonts.ready;
        await waitImages(card);

        var stage = prepareCaptureStage(card);
        document.body.classList.add('ig-capturing');

        try {
            await new Promise(function (r) {
                requestAnimationFrame(function () {
                    requestAnimationFrame(r);
                });
            });

            return htmlToImage.toCanvas(card, {
                width: CARD_W,
                height: CARD_H,
                canvasWidth: CARD_W,
                canvasHeight: CARD_H,
                pixelRatio: 1,
                backgroundColor: '#071428',
                cacheBust: false,
                skipAutoScale: true,
                style: {
                    transform: 'none',
                    margin: '0',
                    boxShadow: 'none',
                },
            });
        } finally {
            document.body.classList.remove('ig-capturing');
            stage.restore();
        }
    }

    function canvasToBlob(canvas) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (blob) resolve(blob);
                else reject(new Error('Falha ao gerar blob PNG'));
            }, 'image/png', 1.0);
        });
    }

    function triggerBlobDownload(blob, filename) {
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.download = filename;
        link.href = url;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    function triggerDownload(canvas, filename) {
        canvasToBlob(canvas).then(function (blob) {
            triggerBlobDownload(blob, filename.endsWith('.png') ? filename : filename + '.png');
        });
    }

    async function downloadCard(card, btn) {
        if (!card || btn.disabled) return;
        var filename = card.getAttribute('data-filename') || 'unio-instagram';
        var label = btn.innerHTML;
        btn.disabled = true;
        btn.classList.add('is-loading');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando…';
        if (window.IgIcons) IgIcons.inlineIcons(btn);

        try {
            var canvas = await captureCard(card);
            await canvasToBlob(canvas).then(function (blob) {
                triggerBlobDownload(blob, filename + '.png');
            });
            toast('PNG baixado: ' + filename + '.png');
        } catch (e) {
            console.error(e);
            toast('Erro ao gerar PNG. Tente novamente.');
        } finally {
            btn.disabled = false;
            btn.classList.remove('is-loading');
            btn.innerHTML = label;
        }
    }

    async function downloadAllAsZip(cards, btn) {
        if (typeof JSZip === 'undefined') {
            throw new Error('JSZip não carregou');
        }

        var zip = new JSZip();
        var folder = zip.folder('unio-instagram-carrossel');
        var errors = [];
        var added = 0;

        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            var filename = (card.getAttribute('data-filename') || ('unio-instagram-' + String(i + 1).padStart(2, '0'))) + '.png';
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Slide ' + (i + 1) + '/' + cards.length + '…';
            if (window.IgIcons) IgIcons.inlineIcons(btn);
            toast('Gerando slide ' + (i + 1) + ' de ' + cards.length + '…');

            try {
                var canvas = await captureCard(card);
                var blob = await canvasToBlob(canvas);
                folder.file(filename, blob);
                added++;
            } catch (e) {
                console.error('Erro no slide ' + (i + 1), e);
                errors.push(i + 1);
            }
        }

        if (added === 0) {
            throw new Error('Nenhum slide foi gerado');
        }

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Compactando ZIP…';
        toast('Compactando os PNGs em um arquivo ZIP…');

        var zipBlob = await zip.generateAsync({
            type: 'blob',
            compression: 'DEFLATE',
            compressionOptions: { level: 1 },
        });

        triggerBlobDownload(zipBlob, 'unio-instagram-carrossel.zip');

        if (errors.length) {
            toast('ZIP baixado com ' + added + ' slides. Falharam: ' + errors.join(', '));
        } else {
            toast('ZIP baixado com os ' + added + ' slides (1080×1080).');
        }
    }

    document.querySelectorAll('.ig-btn-download[data-card]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = document.getElementById(btn.getAttribute('data-card'));
            downloadCard(card, btn);
        });
    });

    var downloadAll = document.getElementById('downloadAll');
    if (downloadAll) {
        downloadAll.addEventListener('click', async function () {
            if (downloadAll.disabled) return;
            var cards = document.querySelectorAll('.ig-card[data-filename]');
            var orig = downloadAll.innerHTML;
            downloadAll.disabled = true;

            try {
                await downloadAllAsZip(cards, downloadAll);
            } catch (e) {
                console.error(e);
                toast('Erro ao gerar o ZIP. Tente baixar slide por slide.');
            } finally {
                downloadAll.disabled = false;
                downloadAll.innerHTML = orig;
                if (window.IgIcons) IgIcons.inlineIcons(downloadAll);
            }
        });
    }

})();
