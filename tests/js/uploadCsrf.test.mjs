import { test } from 'node:test';
import assert from 'node:assert/strict';
import { applyUploadCsrf } from '../../resources/js/utils/uploadCsrf.mjs';

test('upload uses the current decoded cookie instead of a stale meta token', () => {
    const source = { cookie: 'session=abc; XSRF-TOKEN=first%3D', querySelector: () => ({ getAttribute: () => 'stale' }) };
    const send = () => {
        const headers = {};
        applyUploadCsrf({ setRequestHeader: (key,value) => { headers[key]=value; } }, source);
        return headers;
    };
    assert.deepEqual(send(), { 'X-XSRF-TOKEN': 'first=' });
    source.cookie = 'XSRF-TOKEN=rotated%2Bvalue%3D';
    assert.deepEqual(send(), { 'X-XSRF-TOKEN': 'rotated+value=' });
});

test('upload can fall back to the meta token when no XSRF cookie exists', () => {
    const headers={};
    applyUploadCsrf({ setRequestHeader: (key,value) => { headers[key]=value; } }, { cookie: '', querySelector: () => ({ getAttribute: () => 'current' }) });
    assert.deepEqual(headers, { 'X-CSRF-TOKEN': 'current' });
});
