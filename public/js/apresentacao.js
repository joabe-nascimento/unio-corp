(function () {
    'use strict';

    var A4_W_MM = 210;
    var A4_H_MM = 297;
    var CAPTURE_SCALE = 1.5;
    var PNG_MIME = 'image/png';

    var fontEmbedCache = null;

    function toast(msg) {
        var el = document.getElementById('aprToast');
        if (!el) return;
        el.textContent = msg;
        el.classList.add('is-visible');
        clearTimeout(toast._t);
        toast._t = setTimeout(function () { el.classList.remove('is-visible'); }, 3600);
    }

    function setBtnProgress(btn, html) {
        btn.innerHTML = html;
    }

    function faWebfontsBase() {
        var base = document.body && document.body.dataset.faWebfonts;
        return base || '/vendor/font-awesome/webfonts/';
    }

    function faCssHref() {
        var href = document.body && document.body.dataset.faCss;
        return href || '/vendor/font-awesome/all.min.css';
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

    function blobToDataUri(blob) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () { resolve(reader.result); };
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }

    async function fetchFontDataUri(filename) {
        var res = await fetch(faWebfontsBase() + filename);
        if (!res.ok) throw new Error('Fonte não encontrada: ' + filename);
        return blobToDataUri(await res.blob());
    }

    /*
     * Monta CSS de embed manualmente — NÃO usa getFontEmbedCSS do html-to-image,
     * que corrompe o cache de font-faces da página ao exportar.
     */
    async function buildFontEmbedCSS() {
        if (fontEmbedCache) return fontEmbedCache;

        var solid   = await fetchFontDataUri('fa-solid-900.woff2');
        var regular = await fetchFontDataUri('fa-regular-400.woff2');
        var brands  = await fetchFontDataUri('fa-brands-400.woff2');

        fontEmbedCache =
            '@font-face{font-family:"Font Awesome 6 Free";font-style:normal;font-weight:900;font-display:block;src:url(' +
            solid + ') format("woff2");}' +
            '@font-face{font-family:"Font Awesome 6 Free";font-style:normal;font-weight:400;font-display:block;src:url(' +
            regular + ') format("woff2");}' +
            '@font-face{font-family:"Font Awesome 6 Brands";font-style:normal;font-weight:400;font-display:block;src:url(' +
            brands + ') format("woff2");}';

        return fontEmbedCache;
    }

    async function restoreIconFonts() {
        var cssHref = faCssHref().split('?')[0];

        await new Promise(function (resolve) {
            var current = document.querySelector('link[data-apr-fa="1"]');
            var fresh = document.createElement('link');
            fresh.rel = 'stylesheet';
            fresh.href = cssHref + '?restore=' + Date.now();
            fresh.setAttribute('data-apr-fa', '1');
            fresh.onload = resolve;
            fresh.onerror = resolve;
            if (current && current.parentNode) {
                current.parentNode.replaceChild(fresh, current);
            } else {
                document.head.appendChild(fresh);
            }
        });

        var loads = [
            document.fonts.load('900 13px "Font Awesome 6 Free"'),
            document.fonts.load('400 13px "Font Awesome 6 Free"'),
            document.fonts.load('400 13px "Font Awesome 6 Brands"'),
        ];
        await Promise.allSettled(loads);
        await document.fonts.ready;
    }

    function getJsPDF() {
        if (window.jspdf && window.jspdf.jsPDF) return window.jspdf.jsPDF;
        if (typeof window.jsPDF === 'function') return window.jsPDF;
        throw new Error('jsPDF não carregou');
    }

    function getHtmlToImage() {
        if (window.htmlToImage && typeof window.htmlToImage.toCanvas === 'function') {
            return window.htmlToImage;
        }
        throw new Error('html-to-image não carregou');
    }

    function sheetBackground(sheet) {
        if (sheet.classList.contains('apr-sheet--cover')) return '#071428';
        if (sheet.classList.contains('apr-sheet--dark')) return '#071428';
        return '#ffffff';
    }

    function raf() {
        return new Promise(function (resolve) {
            requestAnimationFrame(function () {
                requestAnimationFrame(resolve);
            });
        });
    }

    function isolateSheet(sheets, activeIndex) {
        sheets.forEach(function (sheet, index) {
            if (index === activeIndex) {
                sheet.style.visibility = '';
                sheet.style.pointerEvents = '';
            } else {
                sheet.style.visibility = 'hidden';
                sheet.style.pointerEvents = 'none';
            }
        });
    }

    function restoreSheets(sheets) {
        sheets.forEach(function (sheet) {
            sheet.style.visibility = '';
            sheet.style.pointerEvents = '';
        });
    }

    async function captureSheet(htmlToImage, sheet, fontEmbedCSS) {
        await raf();

        var bg = sheetBackground(sheet);
        var width  = sheet.offsetWidth;
        var height = sheet.offsetHeight;

        return htmlToImage.toCanvas(sheet, {
            pixelRatio: CAPTURE_SCALE,
            backgroundColor: bg,
            cacheBust: false,
            fontEmbedCSS: fontEmbedCSS,
            skipFonts: false,
            width: width,
            height: height,
            style: {
                overflow: 'visible',
                boxShadow: 'none',
                width:     width  + 'px',
                height:    height + 'px',
                minHeight: height + 'px',
                maxHeight: height + 'px',
            },
        });
    }

    function addCanvasToPdf(pdf, canvas, addPageBefore) {
        if (addPageBefore) pdf.addPage();

        var imgData    = canvas.toDataURL(PNG_MIME);
        var pxW        = canvas.width  / CAPTURE_SCALE;
        var pxH        = canvas.height / CAPTURE_SCALE;
        var targetRatio = A4_W_MM / A4_H_MM;
        var canvasRatio = pxW / pxH;

        var renderW = A4_W_MM;
        var renderH = A4_H_MM;
        var offsetX = 0;
        var offsetY = 0;

        if (Math.abs(canvasRatio - targetRatio) > 0.01) {
            if (canvasRatio > targetRatio) {
                renderH = A4_W_MM / canvasRatio;
                offsetY = (A4_H_MM - renderH) / 2;
            } else {
                renderW = A4_H_MM * canvasRatio;
                offsetX = (A4_W_MM - renderW) / 2;
            }
        }

        pdf.addImage(imgData, 'PNG', offsetX, offsetY, renderW, renderH, undefined, 'FAST');
        return pdf;
    }

    async function downloadPdf() {
        var btn  = document.getElementById('downloadPdf');
        var deck = document.getElementById('apr-document');
        if (!btn || !deck) { toast('Erro ao gerar PDF. Recarregue a página.'); return; }

        var sheets = deck.querySelectorAll('.apr-sheet');
        if (!sheets.length) { toast('Nenhuma página encontrada para exportar.'); return; }

        var savedScrollY = window.scrollY || window.pageYOffset || 0;

        var label = btn.innerHTML;
        btn.disabled = true;
        setBtnProgress(btn, '<i class="fas fa-spinner fa-spin"></i> Preparando…');
        document.body.classList.add('apr-exporting');

        try {
            deck.querySelectorAll('img[loading="lazy"]').forEach(function (img) {
                img.loading = 'eager';
            });
            await document.fonts.ready;
            await waitImages(deck);
            await raf();

            var JsPDF       = getJsPDF();
            var htmlToImage = getHtmlToImage();

            setBtnProgress(btn, '<i class="fas fa-spinner fa-spin"></i> Preparando fontes…');
            var fontEmbedCSS = await buildFontEmbedCSS();

            toast('Gerando PDF (' + sheets.length + ' páginas)…');

            var pdf = null;

            for (var i = 0; i < sheets.length; i++) {
                setBtnProgress(btn, '<i class="fas fa-spinner fa-spin"></i> Folha ' + (i + 1) + '/' + sheets.length + '…');

                isolateSheet(sheets, i);
                await raf();

                var canvas = await captureSheet(htmlToImage, sheets[i], fontEmbedCSS);

                if (pdf === null) {
                    pdf = new JsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait', compress: true });
                    addCanvasToPdf(pdf, canvas, false);
                } else {
                    addCanvasToPdf(pdf, canvas, true);
                }
            }

            restoreSheets(sheets);
            pdf.save('unio-apresentacao-plataforma.pdf');
            toast('PDF baixado (' + sheets.length + ' páginas).');
        } catch (e) {
            console.error(e);
            restoreSheets(sheets);
            toast('Erro ao gerar PDF: ' + (e && e.message ? e.message : 'Erro desconhecido'));
        } finally {
            document.body.classList.remove('apr-exporting');
            btn.disabled  = false;
            btn.innerHTML = label;

            await restoreIconFonts();

            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    window.scrollTo({ top: savedScrollY, behavior: 'instant' });
                });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var faLink = document.querySelector('link[href*="font-awesome"]');
        if (faLink) faLink.setAttribute('data-apr-fa', '1');

        var btn = document.getElementById('downloadPdf');
        if (btn) btn.addEventListener('click', downloadPdf);
    });
})();
