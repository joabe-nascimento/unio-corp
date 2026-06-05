const fs = require('fs');
const path = require('path');
const solid = require('@fortawesome/free-solid-svg-icons');
const brands = require('@fortawesome/free-brands-svg-icons');

const solidNames = {
    faBuilding: 'fa-building',
    faRobot: 'fa-robot',
    faUserPlus: 'fa-user-plus',
    faUmbrellaBeach: 'fa-umbrella-beach',
    faFileInvoiceDollar: 'fa-file-invoice-dollar',
    faUserMinus: 'fa-user-minus',
    faLayerGroup: 'fa-layer-group',
    faRightLeft: 'fa-right-left',
    faKey: 'fa-key',
    faPalette: 'fa-palette',
    faEye: 'fa-eye',
    faUsersGear: 'fa-users-gear',
    faHeadset: 'fa-headset',
    faGaugeHigh: 'fa-gauge-high',
    faFire: 'fa-fire',
    faChartLine: 'fa-chart-line',
    faPlug: 'fa-plug',
    faDiagramProject: 'fa-diagram-project',
    faInbox: 'fa-inbox',
    faBullseye: 'fa-bullseye',
    faComments: 'fa-comments',
    faCompass: 'fa-compass',
    faBrain: 'fa-brain',
    faShieldHalved: 'fa-shield-halved',
    faArrowRight: 'fa-arrow-right',
    faArrowUp: 'fa-arrow-up',
    faDownload: 'fa-download',
    faFileZipper: 'fa-file-zipper',
    faSpinner: 'fa-spinner',
};

const brandNames = {
    faInstagram: 'fa-instagram',
};

const ICONS = {};

for (const [key, name] of Object.entries(solidNames)) {
    const ic = solid[key];
    if (!ic) throw new Error('missing ' + key);
    ICONS[name] = { viewBox: '0 0 ' + ic.icon[0] + ' ' + ic.icon[1], d: ic.icon[4] };
}

for (const [key, name] of Object.entries(brandNames)) {
    const ic = brands[key];
    if (!ic) throw new Error('missing ' + key);
    ICONS[name] = { viewBox: '0 0 ' + ic.icon[0] + ' ' + ic.icon[1], d: ic.icon[4] };
}

const body = `/**
 * Ícones SVG inline (Font Awesome 6) — compatíveis com preview e export PNG.
 * Gerado por scripts/gen-instagram-icons.js
 */
(function (global) {
    'use strict';

    var ICONS = ${JSON.stringify(ICONS, null, 4)};

    function parseIconClass(el) {
        var list = (el.getAttribute('class') || '').split(/\\s+/);
        for (var i = 0; i < list.length; i++) {
            var c = list[i];
            if (c.indexOf('fa-') === 0 && c !== 'fas' && c !== 'fab' && c !== 'far' && c !== 'fal') {
                return c;
            }
        }
        return null;
    }

    function createSvg(name, spin) {
        var def = ICONS[name];
        if (!def) return null;
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', def.viewBox);
        svg.setAttribute('class', 'ig-svg-icon' + (spin ? ' ig-svg-icon--spin' : ''));
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        p.setAttribute('fill', 'currentColor');
        p.setAttribute('d', def.d);
        svg.appendChild(p);
        return svg;
    }

    function inlineIcons(root) {
        if (!root) return;
        var icons = root.querySelectorAll('i[class*="fa-"]');
        for (var i = 0; i < icons.length; i++) {
            var el = icons[i];
            var name = parseIconClass(el);
            if (!name) continue;
            var spin = (el.getAttribute('class') || '').indexOf('fa-spin') !== -1;
            var svg = createSvg(name, spin);
            if (svg) el.replaceWith(svg);
        }
    }

    function inlineAllCards() {
        document.querySelectorAll('.ig-card').forEach(function (card) {
            inlineIcons(card);
        });
    }

    function inlinePage() {
        inlineAllCards();
        document.querySelectorAll('.ig-toolbar i[class*="fa-"]').forEach(function (el) {
            var name = parseIconClass(el);
            if (!name) return;
            var spin = (el.getAttribute('class') || '').indexOf('fa-spin') !== -1;
            var svg = createSvg(name, spin);
            if (svg) el.replaceWith(svg);
        });
    }

    global.IgIcons = {
        inlineIcons: inlineIcons,
        inlineAllCards: inlineAllCards,
        inlinePage: inlinePage,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inlinePage);
    } else {
        inlinePage();
    }
})(window);
`;

const out = path.join(__dirname, '..', 'public', 'instagram', 'feed-card-icons.js');
fs.writeFileSync(out, body);
console.log('written', Object.keys(ICONS).length, 'icons to', out);
