/* Portal Pós-Operatório — shell offline + fila de questionário */
const CACHE = 'unio-pos-op-v2';
const SHELL = ['/pos-operatorio/portal', '/clinica/portal'];
const QUEUE_KEY = 'unio-posop-offline-queue';

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (!url.pathname.startsWith('/pos-operatorio') && !url.pathname.startsWith('/clinica/portal')) {
    return;
  }

  if (event.request.method === 'POST' && url.pathname.includes('/portal/questionario')) {
    event.respondWith(queueOrForward(event.request));
    return;
  }

  if (event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        const copy = response.clone();
        caches.open(CACHE).then((cache) => cache.put(event.request, copy));
        return response;
      })
      .catch(() =>
        caches.match(event.request).then((r) => r || caches.match('/pos-operatorio/portal'))
      )
  );
});

self.addEventListener('sync', (event) => {
  if (event.tag === 'unio-posop-sync') {
    event.waitUntil(flushQueue());
  }
});

async function queueOrForward(request) {
  try {
    return await fetch(request.clone());
  } catch (e) {
    const body = await request.clone().text();
    const entry = {
      url: request.url,
      body,
      headers: { 'Content-Type': request.headers.get('Content-Type') || 'application/x-www-form-urlencoded' },
      at: Date.now(),
    };
    const queue = await readQueue();
    queue.push(entry);
    await writeQueue(queue);
    if (self.registration && self.registration.sync) {
      try {
        await self.registration.sync.register('unio-posop-sync');
      } catch (_) {}
    }
    return new Response(
      '<!doctype html><html><body><p>Sem conexão — respostas guardadas. Voltamos a enviar automaticamente.</p><a href="/pos-operatorio/portal">Voltar</a></body></html>',
      { status: 202, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    );
  }
}

async function flushQueue() {
  const queue = await readQueue();
  if (!queue.length) return;
  const remaining = [];
  for (const entry of queue) {
    try {
      await fetch(entry.url, {
        method: 'POST',
        headers: entry.headers,
        body: entry.body,
        credentials: 'include',
      });
    } catch (_) {
      remaining.push(entry);
    }
  }
  await writeQueue(remaining);
}

async function readQueue() {
  const cache = await caches.open(CACHE);
  const res = await cache.match(QUEUE_KEY);
  if (!res) return [];
  try {
    return await res.json();
  } catch (_) {
    return [];
  }
}

async function writeQueue(queue) {
  const cache = await caches.open(CACHE);
  await cache.put(
    QUEUE_KEY,
    new Response(JSON.stringify(queue), { headers: { 'Content-Type': 'application/json' } })
  );
}
