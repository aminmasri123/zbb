<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import ProfileItems from './ProfileItems.vue';
const props=defineProps({projectId:Number,canEdit:Boolean});
const emit=defineEmits(['saved']);
const profiles=ref([]), preset=ref(null), enabled=ref(false), name=ref('BvB Reha – Eingangstest'), definition=ref(null), busy=ref(false), error=ref(''), message=ref('');
const copy=x=>JSON.parse(JSON.stringify(x));
function useProfile(p){name.value=p.name;definition.value=copy(p.definition);}
async function load(){try {const {data}=await axios.get(route('aptitude.config',props.projectId));profiles.value=data.profiles;preset.value=data.preset;enabled.value=data.enabled; if(data.profiles.length)useProfile(data.profiles[0]);else definition.value=copy(data.preset);} catch(e){error.value=e.response?.data?.message||'Konfiguration konnte nicht geladen werden.';}}
async function save(){busy.value=true;error.value='';message.value='';try{const {data}=await axios.put(route('aptitude.config.save',props.projectId),{enabled:enabled.value,name:name.value,definition:definition.value});profiles.value=data.profiles;message.value='Konfiguration gespeichert. Bestehende Testgruppen behalten ihre Profilversion.';emit('saved',data.enabled);}catch(e){error.value=Object.values(e.response?.data?.errors||{}).flat().join(' ')||e.response?.data?.message||'Speichern fehlgeschlagen.';}finally{busy.value=false;}}
function addSection(){definition.value.sections.push({key:`section_${Date.now()}`,label:'Neuer Testbereich',category:'school',threshold:null,grades:[],items:[{key:`task_${Date.now()}`,label:'Neue Aufgabe',max:10}]});}
onMounted(load);
</script>
<template>
 <section class="rounded-xl border bg-white p-5 shadow-sm">
  <h2 class="text-lg font-semibold">Eignungstests</h2>
  <p class="mt-1 text-sm text-gray-600">Beim Anlegen einer Gruppe genügt die Auswahl „Bereich: Eignungstest“. Neue Testgruppen verwenden automatisch die zuletzt gespeicherte Vorlage. Bestehende Gruppen behalten ihren Bewertungsstand.</p>
  <p v-if="error" role="alert" class="my-3 rounded bg-red-50 p-3 text-red-800">{{error}}</p><p v-if="message" role="status" class="my-3 text-green-800">{{message}}</p>
  <template v-if="definition">
   <label class="my-4 flex items-center gap-2"><input v-model="enabled" :disabled="!canEdit" type="checkbox" class="rounded text-zbb"/> Eignungstests in diesem Projekt aktivieren</label>
   <div class="mb-4 flex flex-wrap gap-3"><label class="flex-1 text-sm">Profilname<input v-model="name" :disabled="!canEdit" class="mt-1 w-full rounded border-gray-300"/></label><label v-if="profiles.length" class="text-sm">Profilversion als Vorlage<select class="mt-1 block rounded border-gray-300" @change="useProfile(profiles.find(p=>p.id===Number($event.target.value)))"><option v-for="p in profiles" :key="p.id" :value="p.id">{{p.name}} · Version {{p.version}}</option></select></label></div>
   <button v-if="canEdit" class="mb-4 text-sm text-zbb underline" @click="definition=copy(preset);name='BvB Reha – Eingangstest'">BvB-Reha-Vorlage verwenden</button>
   <p class="mb-4 text-sm text-gray-600">Die Vorlage enthält Deutsch (100 Punkte) und Mathematik (100 Punkte). Notenschlüssel sind noch nicht hinterlegt. Die 75-%-Grenze gilt in der Vorlage nur für Deutsch.</p>
   <div v-for="(section,index) in definition.sections" :key="section.key" class="mb-5 rounded-lg border bg-gray-50 p-4">
    <div class="mb-3 grid gap-3 md:grid-cols-3"><label class="text-sm">Testbereich<input v-model="section.label" :disabled="!canEdit" class="mt-1 w-full rounded border-gray-300"/></label><label class="text-sm">LuV-Kompetenzbereich<select v-model="section.category" :disabled="!canEdit" class="mt-1 w-full rounded border-gray-300"><option value="school">Schulisch</option><option value="personal">Personal</option><option value="methodical">Methodisch</option><option value="social">Sozial-kommunikativ</option><option value="technical">Fachlich</option></select></label><label class="text-sm">Hinweisgrenze in % (optional)<input v-model.number="section.threshold" type="number" min="0" max="100" :disabled="!canEdit" class="mt-1 w-full rounded border-gray-300"/></label></div>
    <ProfileItems :items="section.items" :disabled="!canEdit"/>
    <div class="mt-4"><p class="text-sm font-semibold">Notenschlüssel</p><div v-for="(grade,g) in section.grades" :key="g" class="mt-2 flex gap-2"><label class="text-xs">Ab Prozent<input v-model.number="grade.min" :disabled="!canEdit" type="number" min="0" max="100" class="block w-28 rounded border-gray-300"/></label><label class="text-xs">Note<input v-model="grade.label" :disabled="!canEdit" class="block w-28 rounded border-gray-300"/></label><button v-if="canEdit" class="text-xs text-red-700" @click="section.grades.splice(g,1)">Entfernen</button></div><button v-if="canEdit" class="mt-2 text-sm text-zbb underline" @click="section.grades.push({min:0,label:'6'})">Notengrenze hinzufügen</button></div>
    <button v-if="canEdit && definition.sections.length>1" class="mt-4 text-xs text-red-700" @click="definition.sections.splice(index,1)">Testbereich entfernen</button>
   </div>
   <div v-if="canEdit" class="flex gap-3"><button class="rounded border px-4 py-2" @click="addSection">Testbereich hinzufügen</button><button :disabled="busy" class="rounded bg-zbb px-4 py-2 text-white disabled:opacity-50" @click="save">{{busy?'Speichert …':'Konfiguration speichern'}}</button></div>
  </template>
 </section>
</template>
