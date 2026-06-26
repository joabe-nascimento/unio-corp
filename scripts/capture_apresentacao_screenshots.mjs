/**
 * Captura screenshots em tema claro para /apresentacao.
 * Uso: npm run capture:apresentacao
 */
import { chromium } from 'playwright';
import { mkdir } from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const OUT = path.join(ROOT, 'public', 'images', 'apresentacao');
const BASE = process.env.UNIO_BASE_URL || 'http://127.0.0.1:8000';

const shots = [
    {
        name: 'login',
        url: `${BASE}/login?theme=light`,
        selector: '.auth-wrapper',
        viewport: { width: 1400, height: 900 },
        wait: 800,
        initLight: true,
    },
    {
        name: 'workspace',
        url: `${BASE}/apresentacao/preview/workspace`,
        selector: '#capture-root',
        viewport: { width: 800, height: 900 },
        wait: 500,
    },
    {
        name: 'hubs-sidebar',
        url: `${BASE}/apresentacao/preview/hubs`,
        selector: '#capture-root',
        viewport: { width: 1500, height: 980 },
        wait: 600,
    },
    {
        name: 'hub-dock',
        url: `${BASE}/apresentacao/preview/hub-dock`,
        selector: '#capture-root',
        viewport: { width: 1280, height: 800 },
        wait: 500,
    },
];

async function main() {
    await mkdir(OUT, { recursive: true });

    const browser = await chromium.launch();
    const context = await browser.newContext({
        deviceScaleFactor: 1,
        colorScheme: 'light',
    });

    for (const shot of shots) {
        const page = await context.newPage();

        if (shot.initLight) {
            await page.addInitScript(() => {
                localStorage.setItem('unio-theme', 'light');
            });
        }

        await page.setViewportSize(shot.viewport);
        console.log(`Capturando ${shot.name} (tema claro)…`);
        await page.goto(shot.url, { waitUntil: 'networkidle', timeout: 60000 });

        if (shot.initLight) {
            const theme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
            if (theme !== 'light') {
                await page.evaluate(() => {
                    document.documentElement.setAttribute('data-theme', 'light');
                    localStorage.setItem('unio-theme', 'light');
                });
                await page.reload({ waitUntil: 'networkidle' });
            }
            await page.evaluate(() => {
                document.querySelectorAll('.sf-toolbar, #sfwdt, .sf-minitoolbar').forEach(function (el) {
                    el.remove();
                });
            });
        }

        if (shot.beforeCapture) {
            await shot.beforeCapture(page);
        }

        await page.waitForTimeout(shot.wait);

        const el = await page.$(shot.selector);
        if (!el) {
            console.warn(`  ⚠ Seletor não encontrado: ${shot.selector}`);
            await page.screenshot({ path: path.join(OUT, `${shot.name}.png`), type: 'png' });
        } else {
            await el.screenshot({ path: path.join(OUT, `${shot.name}.png`), type: 'png' });
        }
        await page.close();
        console.log(`  ✓ ${shot.name}.png`);
    }

    await browser.close();
    console.log('\nScreenshots salvos em public/images/apresentacao/');
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
