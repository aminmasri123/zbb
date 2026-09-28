import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(process.env.KEEPALIVE_SOURCE || new URL('../../resources/js/keepAlive.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '').replace(/export /g, '');
const settle = () => new Promise(resolve => setImmediate(resolve));

function session() {
    let now = 1_000_000;
    let deadline = now + 1_200_000;
    const listeners = new Map();
    const intervals = [];
    const redirects = [];
    const calls = [];
    const payload = () => ({ lifetime_seconds: 1200, remaining_seconds: Math.max(0, (deadline - now) / 1000) });
    const state = {
        ref: value => ({ value }), navigator: { onLine: true },
        Date: { now: () => now }, AbortController,
        CustomEvent: class { constructor(type) { this.type = type; } },
        document: { hidden: false, addEventListener() {} },
        fetch: async () => ({ ok: now < deadline, status: now < deadline ? 200 : 401, json: async () => payload() }),
        window: {
            asset: path => '/' + path, setTimeout() { return 1; }, clearTimeout() {},
            setInterval(fn, ms) { intervals.push({ fn, ms }); return intervals.length; },
            addEventListener(name, fn, options) { listeners.set(name, { fn, options }); },
            dispatchEvent() {}, location: { replace: url => redirects.push(url) },
            axios: { post: async () => { calls.push(now); deadline = now + 1_200_000; return { data: payload() }; } },
        },
    };
    vm.createContext(state);
    vm.runInContext(source + '\nthis.api = { startBackendKeepAlive, recordAuthenticatedActivity, checkAuthenticatedSession, sessionWarningVisible, sessionRemainingSeconds };', state);
    return {
        state, calls, redirects, listeners,
        advance(ms) { now += ms; }, renewElsewhere() { deadline = now + 1_200_000; },
        async start() { state.api.startBackendKeepAlive(); await settle(); },
        async tick() { intervals.find(i => i.ms === 1000).fn(); await settle(); },
        async event(name, { stopped = false, trusted = true, warning = false } = {}) {
            const listener = listeners.get(name);
            if (listener && (!stopped || listener.options.capture)) {
                listener.fn({ isTrusted: trusted, target: { closest: () => warning ? {} : null } });
            }
            await settle();
        },
    };
}

test('editing and nested scrolling keep a 20-minute session alive for 40 minutes', async () => {
    const s = session(); await s.start();
    for (let i = 0; i < 80; i++) {
        s.advance(30_000);
        await s.event(i % 2 ? 'keydown' : 'scroll', { stopped: true });
        await s.tick();
    }
    assert.equal(s.redirects.length, 0);
    assert.ok(s.calls.length >= 39);
    assert.ok(s.state.api.sessionRemainingSeconds.value > 1100);
});

test('genuine form input renews the session even after the warning appears', async () => {
    const s = session(); await s.start(); s.advance(16 * 60_000); await s.tick();
    assert.equal(s.state.api.sessionWarningVisible.value, true);
    await s.event('input', { stopped: true });
    assert.equal(s.calls.length, 1);
    assert.equal(s.state.api.sessionWarningVisible.value, false);
});

test('warning buttons and synthetic activity do not implicitly renew a session', async () => {
    const s = session(); await s.start();
    await s.event('pointerdown', { warning: true });
    await s.event('scroll', { trusted: false });
    assert.equal(s.calls.length, 0);
});

test('idle sessions still expire after server confirmation', async () => {
    const s = session(); await s.start(); s.advance(20 * 60_000); await s.tick();
    assert.deepEqual(s.redirects, ['/']);
    assert.equal(s.calls.length, 0);
});

test('a renewal in another tab is checked before redirecting at the old deadline', async () => {
    const s = session(); await s.start(); s.advance(19 * 60_000); s.renewElsewhere();
    s.advance(60_000); await s.tick();
    assert.equal(s.redirects.length, 0);
    assert.equal(s.state.api.sessionRemainingSeconds.value, 1140);
});

test('network failure at the local deadline does not throw away the working form', async () => {
    const s = session(); await s.start(); s.state.fetch = async () => { throw new Error('offline'); };
    s.advance(20 * 60_000); await s.tick();
    assert.equal(s.redirects.length, 0);
});

test('an older status response cannot overwrite a successful activity renewal', async () => {
    const s = session(); await s.start(); s.advance(10 * 60_000);
    let resolve;
    s.state.fetch = () => new Promise(r => { resolve = r; });
    const checking = s.state.api.checkAuthenticatedSession();
    await s.state.api.recordAuthenticatedActivity();
    resolve({ ok: true, status: 200, json: async () => ({ lifetime_seconds: 1200, remaining_seconds: 600 }) });
    await checking;
    assert.equal(s.state.api.sessionRemainingSeconds.value, 1200);
});

test('failed activity can retry immediately, successful requests remain throttled', async () => {
    const s = session(); await s.start();
    const success = s.state.window.axios.post;
    s.state.window.axios.post = async () => { throw new Error('temporary'); };
    await s.state.api.recordAuthenticatedActivity();
    s.state.window.axios.post = success;
    await s.state.api.recordAuthenticatedActivity();
    await s.state.api.recordAuthenticatedActivity();
    assert.equal(s.calls.length, 1);
});
