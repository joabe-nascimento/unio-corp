/**
 * Testes manuais da lógica Helix (localStorage simulado).
 * Executar: node tests/helix-logic.test.js
 */
'use strict';

let store = {};
const localStorage = {
    getItem: (k) => (k in store ? store[k] : null),
    setItem: (k, v) => { store[k] = v; },
    clear: () => { store = {}; },
};

const STORAGE_KEY = 'unio-helix-sessions';
let currentSessionId = null;

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function loadSessions() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch (e) {
        return [];
    }
}

function saveSessions(sessions) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(sessions));
}

function uid() {
    return 'h' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
}

function getSession(id) {
    return loadSessions().find((s) => s.id === id) || null;
}

function upsertSession(session) {
    let sessions = loadSessions().filter((s) => s.id !== session.id);
    sessions.unshift(session);
    if (sessions.length > 50) sessions = sessions.slice(0, 50);
    saveSessions(sessions);
}

function ensureSession() {
    if (currentSessionId && getSession(currentSessionId)) return currentSessionId;
    const session = {
        id: uid(),
        title: 'Nova conversa',
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
        messages: [],
    };
    upsertSession(session);
    currentSessionId = session.id;
    return currentSessionId;
}

function sendMessage(text) {
    text = (text || '').trim();
    if (!text) return false;
    const sid = ensureSession();
    const session = getSession(sid);
    if (!session) return false;
    session.messages.push({ role: 'user', text, at: new Date().toISOString() });
    if (session.title === 'Nova conversa') {
        session.title = text.length > 48 ? text.slice(0, 48) + '…' : text;
    }
    session.messages.push({ role: 'assistant', text: 'reply', at: new Date().toISOString() });
    session.updatedAt = new Date().toISOString();
    upsertSession(session);
    return true;
}

function assert(cond, msg) {
    if (!cond) throw new Error('FAIL: ' + msg);
}

function run() {
    localStorage.clear();
    currentSessionId = null;

    assert(loadSessions().length === 0, 'inicio sem sessoes');
    assert(sendMessage('') === false, 'mensagem vazia rejeitada');
    assert(sendMessage('   ') === false, 'mensagem espaco rejeitada');
    assert(sendMessage('Ola') === true, 'primeira mensagem ok');
    assert(loadSessions().length === 1, 'uma sessao apos envio');
    assert(loadSessions()[0].title === 'Ola', 'titulo da sessao');
    assert(loadSessions()[0].messages.length === 2, 'user + assistant');

    const xss = '<script>alert(1)</script>';
    const esc = escapeHtml(xss);
    assert(!esc.includes('<script>'), 'escapeHtml sanitiza');

    for (let i = 0; i < 55; i++) {
        currentSessionId = null;
        sendMessage('msg ' + i);
    }
    assert(loadSessions().length === 50, 'limite 50 sessoes');

    console.log('OK — todos os testes Helix passaram');
}

run();
