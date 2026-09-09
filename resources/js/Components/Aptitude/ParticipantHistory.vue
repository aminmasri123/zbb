<script setup>
import { ref,onMounted } from 'vue';
import axios from 'axios';
import AttemptSummary from './AttemptSummary.vue';
const props=defineProps({participantId:Number});
const attempts=ref([]),error=ref(''),loading=ref(true);
onMounted(async()=>{try{attempts.value=(await axios.get(route('aptitude.history',props.participantId))).data.attempts;}catch(e){error.value=e.response?.data?.message||'Testergebnisse konnten nicht geladen werden.';}finally{loading.value=false;}});
</script>
<template><section class="mx-auto max-w-6xl space-y-4"><h2 class="text-xl font-semibold">Eignungstests</h2><p class="text-sm text-gray-600">Alle Durchführungen dieser Projektteilnahme. Freigegebene Einschätzungen und Förderbedarfe stehen als LuV-Quelle zur Verfügung.</p><p v-if="loading">Ergebnisse werden geladen …</p><p v-else-if="error" role="alert" class="text-red-700">{{error}}</p><p v-else-if="!attempts.length" class="rounded border border-dashed p-5 text-gray-500">Noch keine Testauswertung vorhanden. Die Erfassung erfolgt in der Testgruppe.</p><AttemptSummary v-for="attempt in attempts" :key="attempt.id" :attempt="attempt"/></section></template>
