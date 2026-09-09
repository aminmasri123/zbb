<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { usePermissions } from '@/utils/permissions';
const props=defineProps({permission:Object});
const emit=defineEmits(['changed']);
const {can}=usePermissions();
const mode=ref(null),busy=ref(false),error=ref(''),form=ref({});
function open(value){mode.value=value;error.value='';form.value={name:props.permission.name,display_name:props.permission.display_name||props.permission.name,beschreibung:props.permission.beschreibung||'',confirm_name:''};}
async function save(){busy.value=true;error.value='';try{
 if(mode.value==='edit')await axios.put(route('berechtigung.update',props.permission.id),{name:form.value.name,display_name:form.value.display_name,beschreibung:form.value.beschreibung});
 else await axios.delete(route('berechtigung.destroy',props.permission.id),{data:{confirm_name:form.value.confirm_name}});
 mode.value=null;emit('changed');
}catch(e){error.value=Object.values(e.response?.data?.errors||{}).flat().join(' ')||e.response?.data?.message||'Die Änderung konnte nicht gespeichert werden.';}finally{busy.value=false;}}
</script>
<template>
 <div class="mt-2 flex flex-wrap gap-3 text-xs">
  <button v-if="can('berechtigung.update')" type="button" class="text-zbb underline" @click="open('edit')">Bearbeiten</button>
  <button v-if="can('berechtigung.destroy')&&!permission.name.startsWith('berechtigung.')" type="button" class="text-red-700 underline" @click="open('delete')">Löschen</button>
 </div>
 <Teleport to="body">
  <div v-if="mode" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" @keydown.esc="!busy&&(mode=null)">
   <form role="dialog" aria-modal="true" :aria-labelledby="`permission-title-${permission.id}`" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @submit.prevent="save">
    <h2 :id="`permission-title-${permission.id}`" class="text-xl font-semibold">Berechtigung {{mode==='edit'?'bearbeiten':'löschen'}}</h2>
    <p class="my-3 break-all font-mono text-sm">{{permission.name}}</p>
    <template v-if="mode==='edit'">
     <p class="mb-4 text-sm text-gray-600">Die Änderung gilt für alle Rollen. Zuordnungen bleiben erhalten. Beim Umbenennen werden Verweise im Programm nicht automatisch geändert; dadurch kann der Zugriff auf Funktionen entfallen.</p>
     <label class="mb-4 block text-sm">Technischer Name<input v-model.trim="form.name" required maxlength="255" class="mt-1 w-full rounded border-gray-300 font-mono" :disabled="busy||permission.name.startsWith('berechtigung.')"></label>
     <label class="block text-sm">Anzeigename<input v-model.trim="form.display_name" required maxlength="255" class="mt-1 w-full rounded border-gray-300" :disabled="busy" autofocus></label>
     <label class="mt-4 block text-sm">Beschreibung<textarea v-model="form.beschreibung" maxlength="5000" rows="4" class="mt-1 w-full rounded border-gray-300" :disabled="busy"/></label>
    </template>
    <template v-else>
     <p class="mb-4 text-sm text-red-800">Die Berechtigung wird endgültig für alle Rollen und Benutzer gelöscht. Zugehörige Funktionen können dadurch nicht mehr zugänglich sein. Um sie nur der ausgewählten Rolle zu entziehen, verwenden Sie den Ein-/Aus-Schalter.</p>
     <label class="block text-sm">Zum Bestätigen den technischen Namen eingeben<input v-model="form.confirm_name" required autocomplete="off" class="mt-1 w-full rounded border-gray-300" :disabled="busy" autofocus></label>
    </template>
    <p v-if="error" role="alert" class="mt-4 text-sm text-red-700">{{error}}</p>
    <div class="mt-5 flex justify-end gap-3"><button type="button" :disabled="busy" @click="mode=null">Abbrechen</button><button type="submit" :disabled="busy||(mode==='delete'&&form.confirm_name!==permission.name)" class="rounded px-4 py-2 text-white disabled:opacity-50" :class="mode==='delete'?'bg-red-700':'bg-zbb'">{{busy?'Bitte warten …':mode==='delete'?'Endgültig löschen':'Speichern'}}</button></div>
   </form>
  </div>
 </Teleport>
</template>
