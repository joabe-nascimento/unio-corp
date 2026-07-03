import { chromium } from 'playwright';

const BASE = process.env.UNIO_BASE_URL || 'http://127.0.0.1:8000';
const EMAIL = process.env.UNIO_TEST_EMAIL || 'tenant@unio.dev';
const PASSWORD = process.env.UNIO_TEST_PASSWORD || 'unio123';

async function main() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  console.log('1. Login em', BASE);
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('#email', EMAIL);
  await page.fill('#password', PASSWORD);
  await page.click('button[type="submit"]:has-text("Entrar")');
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });

  console.log('   Pós-login:', page.url());
  await page.goto(`${BASE}/pos-operatorio`, { waitUntil: 'networkidle' });

  console.log('2. Abrir painel Vitória (Helix)');
  await page.waitForSelector('#helixOpenBtn', { timeout: 15000 });
  await page.click('#helixOpenBtn');
  await page.waitForSelector('#helixPanel.is-open', { timeout: 5000 });
  await page.evaluate(() => document.querySelector('.sf-toolbar')?.remove());

  const chatUrl = await page.getAttribute('#helixPanel', 'data-vitoria-chat-url');
  console.log('   URL chat Symfony:', chatUrl);

  console.log('3. Enviar mensagem de teste');
  const msg = 'Como priorizo alertas P1 no pós-operatório?';
  await page.fill('#helixInput', msg);
  await page.dispatchEvent('#helixInput', 'input');

  const [response] = await Promise.all([
    page.waitForResponse((r) => r.url().includes('/api/vitoria/chat') && r.status() === 200, { timeout: 25000 }),
    page.locator('#helixForm').evaluate((form) => form.requestSubmit()),
  ]);
  const apiBody = await response.json();
  console.log('   API status 200, source:', apiBody.source);

  await page.waitForFunction(() => {
    const users = document.querySelectorAll('.helix-msg--user').length;
    const assistants = document.querySelectorAll('.helix-msg--assistant').length;
    return users >= 1 && assistants >= 1;
  }, { timeout: 5000 });

  const assistantTexts = await page.$$eval('.helix-msg--assistant .helix-msg-bubble p', (nodes) =>
    nodes.map((n) => n.textContent?.trim() || ''),
  );
  const lastReply = assistantTexts[assistantTexts.length - 1] || '';
  console.log('4. Resposta da Vitória:', lastReply.slice(0, 200));

  const offline = lastReply.includes('Não consegui contactar');
  if (offline) {
    console.error('FALHA: Vitória offline no proxy Symfony');
    process.exit(1);
  }

  console.log('5. Validar status API autenticada');
  const status = await page.evaluate(async (url) => {
    const r = await fetch(url.replace('/chat', '/status'), { credentials: 'same-origin' });
    return r.json();
  }, chatUrl);
  console.log('   Status:', JSON.stringify(status));

  if (!status.online) {
    console.error('FALHA: status.online = false');
    process.exit(1);
  }

  console.log('\nOK — chat end-to-end validado.');
  await browser.close();
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
