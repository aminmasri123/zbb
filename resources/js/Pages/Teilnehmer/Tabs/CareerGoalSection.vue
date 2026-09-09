<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({ participationId: { type: Number, required: true }, canEdit: Boolean });
const history = ref([]);
const loading = ref(true);
const loadFailed = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const labels = { training: 'Ausbildung', employment: 'Arbeit / Beschäftigung', undecided: 'Noch offen' };
const today = new Date().toLocaleDateString('sv-SE');
const form = ref({ previous_id: null, target: 'undecided', occupation: '', alternatives: '', notes: '', agreement_status: 'wish', documented_on: today });
function applyHistory(items) {
    history.value = items;
    const latest = items[0];
    if (latest) form.value = { previous_id: latest.id, target: latest.target, occupation: latest.occupation || '', alternatives: latest.alternatives || '', notes: latest.notes || '', agreement_status: latest.agreement_status, documented_on: latest.documented_on.slice(0, 10) };
}
async function load() {
    loading.value = true;
    loadFailed.value = false;
    error.value = '';
    try { applyHistory((await axios.get(route('teilnehmer.career-goal.index', props.participationId))).data.history); }
    catch (e) { loadFailed.value = true; error.value = e.response?.data?.message || 'Zielvereinbarung konnte nicht geladen werden.'; }
    finally { loading.value = false; }
}
async function save() {
    saving.value = true; error.value = ''; success.value = '';
    try {
        applyHistory((await axios.post(route('teilnehmer.career-goal.store', props.participationId), form.value)).data.history);
        success.value = 'Zielvereinbarung gespeichert. Sie wird bei einem neuen Start- oder Verlauf-LuV-Entwurf berücksichtigt.';
    } catch (e) {
        error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Speichern fehlgeschlagen.';
    } finally { saving.value = false; }
}
const dateLabel = value => new Date(value).toLocaleDateString('de-DE');
onMounted(load);
</script>

<template>
    <section class="mb-6 rounded-xl border border-gray-200 bg-white p-5">
        <h3 class="text-lg font-semibold text-zbb">Berufliches Ziel / Zielvereinbarung</h3>
        <p class="mt-1 text-sm text-gray-500">Für diese Projektteilnahme. Quelle für „Eingliederungsziel“ in neuen Start- und Verlauf-LuV-Entwürfen. Bestehende Berichte bleiben unverändert.</p>
        <p v-if="loading" class="mt-3 text-sm" role="status">Wird geladen …</p>
        <p v-if="error" class="mt-3 rounded bg-red-50 p-3 text-sm text-red-700" role="alert">{{ error }}</p>
        <button v-if="error" type="button" class="mt-2 text-sm text-zbb underline" @click="load">Gespeicherten Stand neu laden</button>
        <p v-if="success" class="mt-3 rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ success }}</p>
        <form v-if="!loading" class="mt-4" @submit.prevent="save">
            <fieldset :disabled="!canEdit || saving || loadFailed" class="grid gap-4 md:grid-cols-2">
                <label class="text-sm">Ziel *
                    <select v-model="form.target" class="mt-1 block w-full rounded border-gray-300"><option v-for="(label, key) in labels" :key="key" :value="key">{{ label }}</option></select>
                </label>
                <label class="text-sm">{{ form.target === 'training' ? 'Ausbildung als *' : form.target === 'employment' ? 'Beschäftigung als *' : 'Berufsfeld / Tätigkeit (optional)' }}
                    <input v-model.trim="form.occupation" :required="form.target !== 'undecided'" maxlength="255" class="mt-1 block w-full rounded border-gray-300" :placeholder="form.target === 'training' ? 'z. B. Fachlagerist/in' : 'Beruf oder Tätigkeit eingeben'" />
                </label>
                <label class="text-sm">Alternativen (optional)<textarea v-model.trim="form.alternatives" maxlength="2000" rows="2" class="mt-1 block w-full rounded border-gray-300" /></label>
                <label class="text-sm">Ergänzungen (optional)<textarea v-model.trim="form.notes" maxlength="4000" rows="2" class="mt-1 block w-full rounded border-gray-300" /></label>
                <label class="text-sm">Stand *<select v-model="form.agreement_status" class="mt-1 block w-full rounded border-gray-300"><option value="wish">Berufswunsch</option><option value="agreed">Mit Teilnehmer vereinbart</option></select></label>
                <label class="text-sm">Dokumentiert / vereinbart am *<input v-model="form.documented_on" type="date" required :max="today" class="mt-1 block w-full rounded border-gray-300" /></label>
                <div v-if="canEdit" class="md:col-span-2"><button type="submit" class="rounded bg-zbb px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ saving ? 'Wird gespeichert …' : 'Zielvereinbarung speichern' }}</button></div>
            </fieldset>
        </form>
        <details v-if="history.length" class="mt-5 border-t pt-3 text-sm">
            <summary class="cursor-pointer font-semibold">Änderungsverlauf ({{ history.length }})</summary>
            <article v-for="item in history" :key="item.id" class="mt-3 rounded bg-gray-50 p-3">
                <p class="font-semibold">{{ labels[item.target] }}{{ item.occupation ? ': ' + item.occupation : '' }}</p>
                <p>{{ item.agreement_status === 'agreed' ? 'Mit Teilnehmer vereinbart' : 'Berufswunsch' }} · {{ dateLabel(item.documented_on) }}</p>
                <p v-if="item.alternatives" class="mt-1 whitespace-pre-wrap">Alternativen: {{ item.alternatives }}</p>
                <p v-if="item.notes" class="mt-1 whitespace-pre-wrap">{{ item.notes }}</p>
                <p class="mt-2 text-xs text-gray-500">Gespeichert von {{ item.author_name }} · {{ new Date(item.created_at).toLocaleString('de-DE') }}</p>
            </article>
        </details>
    </section>
</template>
