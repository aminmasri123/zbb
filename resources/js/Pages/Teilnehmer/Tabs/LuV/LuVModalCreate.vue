<template>
  <Modal v-if="visible" :scrollable-layout="true" :wide="true" @close="emit('close')">
    <template #header>
      <div><div class="text-lg font-semibold">Manuelle Leistungs- und Verhaltensbeurteilung</div><div class="mt-1 text-sm text-gray-500">BvB-Reha · strukturierter Entwurf</div></div>
    </template>
    <template #body>
      <div class="space-y-5">
        <div class="grid grid-cols-3 gap-2 rounded-lg bg-blue-50 p-1">
          <button v-for="type in luvTypes" :key="type" type="button" class="rounded-md px-3 py-2.5 text-sm font-semibold" :class="formLuV.typ === type ? 'bg-white text-zbb shadow' : 'text-gray-600'" @click="selectType(type)">{{ type }}-LuV</button>
        </div>
        <div class="rounded-lg border bg-gray-50 p-4">
          <div class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><span class="block text-xs text-gray-500">Vorname</span><strong>{{ teilnehmer.vorname || 'Nicht hinterlegt' }}</strong></div>
            <div><span class="block text-xs text-gray-500">Nachname</span><strong>{{ teilnehmer.nachname || 'Nicht hinterlegt' }}</strong></div>
            <div><span class="block text-xs text-gray-500">Kundennummer</span><strong>{{ customerNumber }}</strong></div>
            <div><span class="block text-xs text-gray-500">Formular</span><strong>BvB-Reha · {{ formLuV.typ }}</strong></div>
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="text-sm font-medium">Berichtszeitraum von <span class="text-red-600">*</span><input v-model="formLuV.von" type="date" class="mt-1 w-full rounded-md border-gray-300" /></label>
          <label class="text-sm font-medium">Berichtszeitraum bis <span class="text-red-600">*</span><input v-model="formLuV.bis" type="date" class="mt-1 w-full rounded-md border-gray-300" /></label>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Nur dokumentierte Beobachtungen eintragen. Medizinische Diagnosen, Erkrankungen, Vermutungen und abwertende Klassifizierungen gehören nicht in eine LuV.</div>
        <section v-for="group in activeSchema" :key="group.key" class="rounded-xl border bg-white p-4 shadow-sm">
          <h3 class="font-semibold">{{ group.heading }}</h3><p v-if="group.description" class="mt-1 text-xs text-gray-500">{{ group.description }}</p>
          <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <label v-for="field in group.fields" :key="field.key" class="block text-sm text-gray-700">
              <span class="font-medium">{{ field.label }} <span v-if="field.required" class="text-red-600">*</span></span>
              <textarea v-if="field.type === 'textarea'" v-model="fieldValues[field.key]" rows="4" maxlength="30000" class="mt-1 w-full resize-y rounded-md border-gray-300 text-sm" />
              <select v-else-if="field.type === 'select'" v-model="fieldValues[field.key]" class="mt-1 w-full rounded-md border-gray-300 text-sm"><option value="">Bitte auswählen</option><option v-for="option in field.options" :key="option" :value="option">{{ option }}</option></select>
              <select v-else-if="field.type === 'boolean'" v-model="fieldValues[field.key]" class="mt-1 w-full rounded-md border-gray-300 text-sm"><option :value="null">Nicht angegeben</option><option :value="true">Ja</option><option :value="false">Nein</option></select>
              <input v-else v-model="fieldValues[field.key]" :type="field.type || 'text'" maxlength="1000" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
            </label>
          </div>
        </section>
      </div>
    </template>
    <template #footer>
      <button type="button" class="rounded-md border px-4 py-2 text-sm" @click="emit('close')">Abbrechen</button>
      <button type="button" :disabled="saving" class="rounded-md bg-zbb px-5 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="save">{{ saving ? 'Speichert …' : 'Als Entwurf speichern' }}</button>
    </template>
  </Modal>
</template>

<script setup>
import Modal from '@/Components/ModalForm.vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { fieldsForType, manualLuvSchemas } from './luvManualSchemas';

const props = defineProps({ visible: Boolean, teilnehmer: { type: Object, required: true } });
const emit = defineEmits(['close', 'added']);
const luvTypes = ['Start', 'Verlauf', 'Abschluss'];
const today = () => new Date().toISOString().slice(0, 10);
const saving = ref(false);
const fieldValues = reactive({});
const formLuV = reactive({ teilnehmer_id: props.teilnehmer.id, typ: 'Start', von: today(), bis: today() });
const activeSchema = computed(() => manualLuvSchemas[formLuV.typ] || manualLuvSchemas.Start);
const customerNumber = computed(() => props.teilnehmer.sozialedaten?.kundennummer || 'Nicht hinterlegt');

const initializeFields = (type) => {
  for (const field of fieldsForType(type)) if (!(field.key in fieldValues)) fieldValues[field.key] = field.type === 'boolean' ? null : '';
  fieldValues['report.report_date'] ||= today();
};
const selectType = (type) => { formLuV.typ = type; initializeFields(type); };
const printableValue = (value) => value === true ? 'Ja' : value === false ? 'Nein' : String(value ?? '').trim();
const buildSections = () => activeSchema.value.map((group) => ({
  key: group.key,
  heading: group.heading,
  value: group.fields.map((field) => [field.label, printableValue(fieldValues[field.key])]).filter(([, value]) => value !== '').map(([label, value]) => `${label}: ${value}`).join('\n\n'),
})).filter((section) => section.value !== '');
const sectionValue = (sections, key) => sections.find((section) => section.key === key)?.value || '';
const resetForm = () => { formLuV.typ = 'Start'; formLuV.von = today(); formLuV.bis = today(); Object.keys(fieldValues).forEach((key) => delete fieldValues[key]); initializeFields('Start'); };

const save = async () => {
  const missing = fieldsForType(formLuV.typ).filter((field) => field.required && !printableValue(fieldValues[field.key]));
  if (!formLuV.von || !formLuV.bis || missing.length) {
    await Swal.fire('Pflichtangaben fehlen', missing.length ? `Bitte ausfüllen: ${missing.map((field) => field.label).join(', ')}` : 'Bitte den Berichtszeitraum vollständig angeben.', 'warning'); return;
  }
  saving.value = true;
  const sections = buildSections();
  const legacy = formLuV.typ === 'Start'
    ? [sectionValue(sections, 'initial_situation'), sectionValue(sections, 'goal_steps'), sectionValue(sections, 'funding_sequences')]
    : formLuV.typ === 'Verlauf'
      ? [sectionValue(sections, 'development'), sectionValue(sections, 'goal_steps'), sectionValue(sections, 'funding_sequences')]
      : [sectionValue(sections, 'results'), sectionValue(sections, 'support'), sectionValue(sections, 'support')];
  try {
    const response = await axios.post(route('projekthasteilnehmer.luv.store'), {
      ...formLuV, ausgangssituation: legacy[0], zielvereinbarung: legacy[1], qualifikationen: legacy[2], discussed_on: fieldValues['report.discussed_on'] || null,
      payload: { schema: 'bvb-reha-2023', luv_type: formLuV.typ, title: `${formLuV.typ}-LuV`, fields: Object.fromEntries(fieldsForType(formLuV.typ).map((field) => [field.key, fieldValues[field.key]])), sections, warnings: [] },
    });
    emit('added', response.data.luv); resetForm(); emit('close');
    await Swal.fire({ icon: 'success', title: 'LuV-Entwurf gespeichert', text: response.data.message, timer: 2200, showConfirmButton: false });
  } catch (error) {
    const errors = error.response?.data?.errors;
    await Swal.fire('Speichern nicht möglich', errors ? Object.values(errors).flat().join('\n') : (error.response?.data?.message || 'Es ist ein unerwarteter Fehler aufgetreten.'), 'error');
  } finally { saving.value = false; }
};
watch(() => props.visible, (visible) => { if (visible) initializeFields(formLuV.typ); }, { immediate: true });
</script>
