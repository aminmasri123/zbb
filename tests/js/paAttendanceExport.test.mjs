import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { parse, compileScript } from '@vue/compiler-sfc';

// Run the component's actual save workflow with an isolated server and Vue refs.
const { descriptor } = parse(readFileSync(new URL('../../resources/js/Pages/Partner/BOP/ModalAnwesenheitslistePADigital.vue', import.meta.url), 'utf8'));
const script = compileScript(descriptor, { id: 'attendance-export-test' });
const names = ['draftScopeReady', 'performDraftSave', 'saveDraft', 'ensureDraftSavedForExport'];
const declarations = script.scriptSetupAst.filter(node => node.type === 'VariableDeclaration'
    && names.includes(node.declarations[0].id.name));
const source = declarations.map(node => descriptor.scriptSetup.content.slice(node.start, node.end)).join('\n');
const ref = value => ({ value });

function workflow({ fail = false, revision = 0, queue = Promise.resolve() } = {}) {
    const calls = [];
    const state = {
        props: { partnerId: 1, schuljahr: '2026/2027', teil: '1' },
        form: { exportMode: 'alle', klasse: '' }, isPreparationPa: ref(true),
        computed: getter => ({ get value() { return getter(); } }),
        previewContext: ref({}), draftDirty: ref(false), draftRevision: ref(revision),
        draftSaveBlocked: ref(false), draftSaveError: ref(''), draftLoaded: ref(true),
        draftLastSavedAt: ref(null), draftExpiresAt: ref(null), draftSaving: ref(false),
        draftSaveQueue: queue, draftSaveTimer: null, draftSaveGeneration: 1,
        draftSaveRequestId: 0, draftSaveQueueDepth: 0,
        signatures: {}, pendingSignatureChanges: {}, signatureSnapshot: JSON.stringify,
        draftScopePayload: () => ({ exportMode: 'alle', klasse: '', listType: 'pa_preparation' }),
        buildDraftPayload: ({ signaturesPayload }) => ({ days: [{ date: '2026-09-15', selected: true }], signatures: signaturesPayload }),
        applyDraftPayload: () => {}, route: name => name,
        window: { clearTimeout() {} }, PaSwal: { fire: (...args) => calls.push(['warning', ...args]) },
        axios: { put: async (url, body) => {
            calls.push(['save', body]);
            if (fail) throw { response: { status: 500 } };
            return { data: { revision: revision + 1 } };
        } },
    };
    vm.createContext(state);
    vm.runInContext(source + '\nthis.ensureSaved = ensureDraftSavedForExport;', state);
    return { state, calls };
}

test('untouched all-class preparation preview is saved before export, without requiring a class or signature', async () => {
    const { state, calls } = workflow();
    assert.equal(await state.ensureSaved(), true);
    assert.equal(calls.length, 1);
    assert.equal(calls[0][1].exportMode, 'alle');
    assert.equal(calls[0][1].payload.days[0].date, '2026-09-15');
    assert.equal(Object.keys(calls[0][1].payload.signatures).length, 0);
    assert.equal(state.draftRevision.value, 1);
});

test('export waits for an already running save before saving its current draft', async () => {
    let finish;
    const { state, calls } = workflow({ queue: new Promise(resolve => { finish = resolve; }) });
    const result = state.ensureSaved();
    assert.equal(calls.length, 0);
    finish();
    assert.equal(await result, true);
    assert.equal(calls[0][0], 'save');
});

test('failed save blocks export even when an older revision exists', async () => {
    const { state, calls } = workflow({ fail: true, revision: 3 });
    assert.equal(await state.ensureSaved(), false);
    assert.equal(state.draftSaveBlocked.value, true);
    assert.equal(calls.at(-1)[0], 'warning');
});

test('pending signature changes are included in the export save', async () => {
    const { state, calls } = workflow();
    state.pendingSignatureChanges['day:42'] = 'data:image/png;base64,test';
    assert.equal(await state.ensureSaved(), true);
    assert.equal(calls[0][1].payload.signatures['day:42'], 'data:image/png;base64,test');
});

test('single-class mode still requires a selected class', async () => {
    const { state, calls } = workflow();
    state.form.exportMode = 'klasse';
    assert.equal(await state.ensureSaved(), false);
    assert.equal(calls.length, 0);
});
