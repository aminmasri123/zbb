<script setup>
import { computed, nextTick, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import ParticipantPortalLayout from '@/Layouts/ParticipantPortalLayout.vue';

const props = defineProps({ participations: Array, bookmarks: Array, applications: Array, applicationStatuses: Array, recommendations: { type: Array, default: () => [] }, applicationDocuments: { type: Array, default: () => [] }, careerDocuments: { type: Array, default: () => [] } });
const jobParticipations = computed(() => props.participations.filter((item) => item.portal_features?.job_search));
const applicationParticipations = computed(() => props.participations.filter((item) => item.portal_features?.application_management));
const selectedParticipation = ref(jobParticipations.value[0]?.id || '');
const search = ref({ was: '', wo: '', umkreis: 25, angebotsart: 1, page: 1, size: 20 });
const results = ref([]);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const success = ref('');
const creatingApplicationRef = ref('');
const bookmarkItems = ref([...props.bookmarks]);
const applicationItems = ref(props.applications.map((item) => ({
    ...item,
    applied_at: item.applied_at?.slice(0, 10) || '',
    next_action_at: item.next_action_at?.slice(0, 10) || '',
    selected_document_ids: (item.documents || []).map((document) => document.id),
    selected_career_document_ids: (item.career_documents || []).map((document) => document.id),
    recipient_email: item.recipient_email || '', email_subject: item.email_subject || `Bewerbung als ${item.title}`,
    email_message: `Sehr geehrte Damen und Herren,\n\nanbei erhalten Sie meine Bewerbungsunterlagen für die Position ${item.title}.\n\nMit freundlichen Grüßen`, activity_note: '', sending: false,
})));
const recommendationItems = ref([...(props.recommendations || [])]);
const statusLabels = { draft: 'Entwurf', preparing: 'Vorbereitung', sent: 'Versendet', response: 'Rückmeldung', interview: 'Vorstellungsgespräch', accepted: 'Zusage', rejected: 'Absage', withdrawn: 'Zurückgezogen' };
const jobPayload = (job) => ({ external_ref: job.external_ref, title: job.title, employer: job.employer || null, location: job.location || null, source_url: job.source_url || null, published_at: job.published_at || null });

const runSearch = async () => {
    loading.value = true; error.value = ''; success.value = '';
    try {
        const response = await axios.get(route('participant-portal.jobs.search'), { params: { ...search.value, project_person_id: selectedParticipation.value } });
        results.value = response.data.items || []; total.value = response.data.total || 0;
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Die Jobsuche konnte nicht geladen werden.';
    } finally { loading.value = false; }
};
const isBookmarked = (job) => bookmarkItems.value.some((item) => item.external_ref === job.external_ref);
const applicationForJob = (job) => applicationItems.value.find((item) => item.external_ref === job.external_ref && Number(item.project_person_id) === Number(selectedParticipation.value));
const toggleBookmark = async (job) => {
    const existing = bookmarkItems.value.find((item) => item.external_ref === job.external_ref);
    if (existing) {
        await axios.delete(route('participant-portal.jobs.bookmarks.destroy', existing.id));
        bookmarkItems.value = bookmarkItems.value.filter((item) => item.id !== existing.id);
    } else {
        const response = await axios.post(route('participant-portal.jobs.bookmarks.store'), { project_person_id: selectedParticipation.value, ...jobPayload(job) });
        bookmarkItems.value.unshift(response.data.bookmark);
    }
};
const createApplication = async (job) => {
    error.value = ''; success.value = '';
    const participationId = applicationParticipations.value.find((item) => item.id === Number(selectedParticipation.value))?.id;
    if (!participationId) { error.value = 'Bewerbungsmanagement ist für das ausgewählte Projekt nicht freigeschaltet.'; return; }
    const existing = applicationItems.value.find((item) => item.external_ref === job.external_ref && Number(item.project_person_id) === Number(participationId));
    if (existing) {
        success.value = 'Für diese Stelle besteht bereits eine Bewerbung. Das Bewerbungscockpit wurde geöffnet.';
        await nextTick(); window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        return;
    }
    creatingApplicationRef.value = job.external_ref;
    try {
        const response = await axios.post(route('participant-portal.applications.store'), { project_person_id: participationId, ...jobPayload(job), next_action_at: null, notes: null });
        applicationItems.value.unshift({ ...response.data.application, applied_at: '', next_action_at: '', selected_document_ids: [] });
        success.value = response.data.message || 'Bewerbung wurde angelegt.';
        await nextTick(); window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    } catch (requestError) {
        error.value = requestError.response?.data?.errors?.external_ref?.[0] || requestError.response?.data?.message || 'Die Bewerbung konnte nicht angelegt werden.';
    } finally { creatingApplicationRef.value = ''; }
};
const saveApplication = async (application) => {
    const response = await axios.put(route('participant-portal.applications.update', application.id), {
        status: application.status,
        applied_at: application.applied_at?.slice(0, 10) || null,
        next_action_at: application.next_action_at?.slice(0, 10) || null,
        notes: application.notes || null,
    });
    Object.assign(application, response.data.application);
};
const recommendationSender = (item) => `${item.recommender?.person?.vorname || ''} ${item.recommender?.person?.nachname || ''}`.trim() || item.recommender?.username || 'Projektteam';
const viewRecommendation = async (item) => { if (!item.viewed_at) { await axios.put(route('participant-portal.recommendations.viewed', item.id)); item.viewed_at = new Date().toISOString(); } };
const dismissRecommendation = async (item) => { await axios.put(route('participant-portal.recommendations.dismiss', item.id)); recommendationItems.value = recommendationItems.value.filter((entry) => entry.id !== item.id); };
const convertRecommendation = async (item) => { const response = await axios.post(route('participant-portal.recommendations.convert', item.id)); applicationItems.value.unshift({ ...response.data.application, selected_document_ids: [] }); Object.assign(item, response.data.recommendation); };
const documentsForApplication = (application) => props.applicationDocuments.filter((document) => Number(document.project_person_id) === Number(application.project_person_id));
const saveApplicationPackage = async (application) => { const response = await axios.put(route('participant-portal.applications.documents.sync', application.id), { document_ids: application.selected_document_ids || [] }); Object.assign(application, response.data.application, { selected_document_ids: (response.data.application.documents || []).map((document) => document.id) }); };
const approveApplicationPackage = async (application) => { const response = await axios.post(route('participant-portal.applications.package.approve', application.id)); Object.assign(application, response.data.application, { selected_document_ids: (response.data.application.documents || []).map((document) => document.id) }); };
const saveCareerPackage = async application => { const response=await axios.put(route('participant-portal.applications.career-documents.sync',application.id),{document_ids:application.selected_career_document_ids||[]});Object.assign(application,response.data.application,{selected_career_document_ids:(response.data.application.career_documents||[]).map(d=>d.id)});success.value=response.data.message; };
const addActivityNote = async application => { if(!application.activity_note.trim())return;const response=await axios.post(route('participant-portal.applications.notes.store',application.id),{body:application.activity_note});application.activities=[response.data.activity,...(application.activities||[])];application.activity_note=''; };
const sendApplication = async application => { application.sending=true;error.value='';try{const response=await axios.post(route('participant-portal.applications.send',application.id),{to:application.recipient_email,subject:application.email_subject,message:application.email_message});Object.assign(application,response.data.application,{selected_career_document_ids:(response.data.application.career_documents||[]).map(d=>d.id)});success.value=response.data.message;}catch(e){error.value=e.response?.data?.message||Object.values(e.response?.data?.errors||{})?.[0]?.[0]||'Versand fehlgeschlagen.';}finally{application.sending=false;} };
</script>

<template>
    <Head title="Jobs und Bewerbungen" />
    <ParticipantPortalLayout title="Jobs & Bewerbungen" subtitle="Finden Sie passende Stellen und behalten Sie Ihre Bewerbungen übersichtlich im Blick.">
        <div class="space-y-6">

            <section v-if="recommendationItems.length" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm"><h2 class="text-lg font-semibold text-emerald-900">Empfehlungen Ihres Projektteams</h2><div class="mt-4 grid gap-4 md:grid-cols-2"><article v-for="item in recommendationItems" :key="item.id" class="rounded-xl border bg-white p-4" @mouseenter="viewRecommendation(item)"><div class="flex justify-between gap-3"><div><h3 class="font-semibold">{{ item.title }}</h3><p class="text-sm text-gray-600">{{ item.employer || 'Arbeitgeber offen' }} · {{ item.location || 'Ort offen' }}</p></div><span v-if="!item.viewed_at" class="h-fit rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-700">Neu</span></div><p v-if="item.note" class="mt-3 rounded bg-gray-50 p-3 text-sm">{{ item.note }}</p><p class="mt-2 text-xs text-gray-500">Empfohlen von {{ recommendationSender(item) }} am {{ new Date(item.recommended_at).toLocaleDateString('de-DE') }} · {{ item.participation?.projekt?.name }}</p><div class="mt-3 flex flex-wrap gap-2"><a v-if="item.source_url" :href="item.source_url" target="_blank" rel="noopener noreferrer" class="rounded border px-3 py-2 text-xs" @click="viewRecommendation(item)">Anzeige öffnen</a><button v-if="!item.converted_application_id" class="rounded bg-zbb px-3 py-2 text-xs text-white" @click="convertRecommendation(item)">Als Bewerbung übernehmen</button><span v-else class="rounded bg-green-100 px-3 py-2 text-xs text-green-700">Übernommen</span><button v-if="!item.converted_application_id" class="px-3 py-2 text-xs text-red-600" @click="dismissRecommendation(item)">Nicht passend</button></div></article></div></section>

            <section v-if="jobParticipations.length" class="rounded-2xl border bg-white p-6 shadow-sm">
                <form class="grid gap-3 md:grid-cols-[220px_1fr_1fr_120px_auto]" @submit.prevent="runSearch">
                    <select v-model="selectedParticipation" class="rounded border-gray-300 text-sm"><option v-for="item in jobParticipations" :key="item.id" :value="item.id">{{ item.projekt.name }}</option></select>
                    <input v-model="search.was" placeholder="Beruf oder Stichwort" class="rounded border-gray-300 text-sm" />
                    <input v-model="search.wo" placeholder="Ort" class="rounded border-gray-300 text-sm" />
                    <input v-model="search.umkreis" type="number" min="0" max="200" placeholder="Umkreis" class="rounded border-gray-300 text-sm" />
                    <button class="rounded bg-zbb px-5 py-2 font-semibold text-white disabled:opacity-50" :disabled="loading">{{ loading ? 'Sucht …' : 'Suchen' }}</button>
                </form>
                <p class="mt-3 text-xs text-gray-500">Quelle: Bundesagentur für Arbeit – Jobsuche. Externe Angaben können sich ändern.</p>
                <p v-if="error" class="mt-3 rounded bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
                <p v-if="success" class="mt-3 rounded bg-green-50 p-3 text-sm font-medium text-green-700">{{ success }}</p>
                <div v-if="results.length" class="mt-5 space-y-3">
                    <p class="text-sm text-gray-500">{{ total }} Treffer</p>
                    <article v-for="job in results" :key="job.external_ref" class="rounded-xl border p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-semibold text-gray-900">{{ job.title }}</h2><p class="text-sm text-gray-600">{{ job.employer || 'Arbeitgeber nicht angegeben' }} · {{ job.location || 'Ort nicht angegeben' }}</p><p class="mt-1 text-xs text-gray-400">Referenz {{ job.external_ref }}</p></div><div class="flex gap-2"><button type="button" class="rounded border px-3 py-2 text-xs" @click="toggleBookmark(job)">{{ isBookmarked(job) ? 'Gemerkt ✓' : 'Merken' }}</button><button v-if="applicationParticipations.length" type="button" class="rounded px-3 py-2 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-70" :class="applicationForJob(job) ? 'bg-green-600' : 'bg-zbb'" :disabled="Boolean(applicationForJob(job)) || creatingApplicationRef === job.external_ref" @click="createApplication(job)">{{ applicationForJob(job) ? 'Bewerbung angelegt ✓' : creatingApplicationRef === job.external_ref ? 'Wird angelegt …' : 'Bewerbung anlegen' }}</button><a v-if="job.source_url" :href="job.source_url" target="_blank" rel="noopener noreferrer" class="rounded border px-3 py-2 text-xs">Anzeige öffnen</a></div></div>
                    </article>
                </div>
            </section>
            <section v-else class="rounded-2xl border bg-white p-6 text-gray-600">Die Jobsuche ist für Ihre Projekte nicht freigeschaltet.</section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-2xl border bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold">Merkliste</h2><div class="mt-4 space-y-3"><article v-for="job in bookmarkItems" :key="job.id" class="rounded-xl border p-3"><p class="font-semibold">{{ job.title }}</p><p class="text-sm text-gray-500">{{ job.employer }} · {{ job.location }}</p><button class="mt-2 text-xs text-red-600" @click="toggleBookmark(job)">Entfernen</button></article><p v-if="!bookmarkItems.length" class="text-sm text-gray-500">Noch keine gemerkten Stellen.</p></div></section>
                <section class="rounded-2xl border bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold">Bewerbungscockpit</h2><div class="mt-4 space-y-4"><article v-for="application in applicationItems" :key="application.id" class="rounded-xl border p-4"><p class="font-semibold">{{ application.title }}</p><p class="text-sm text-gray-500">{{ application.employer }} · {{ application.participation?.projekt?.name }}</p><div class="mt-3 grid gap-2 sm:grid-cols-2"><select v-model="application.status" class="rounded border-gray-300 text-sm"><option v-for="status in applicationStatuses" :key="status" :value="status">{{ statusLabels[status] }}</option></select><input v-model="application.next_action_at" type="date" class="rounded border-gray-300 text-sm" /><input v-model="application.applied_at" type="date" class="rounded border-gray-300 text-sm" /><button class="rounded bg-zbb px-3 py-2 text-sm text-white" @click="saveApplication(application)">Speichern</button></div><textarea v-model="application.notes" rows="2" placeholder="Notiz zum nächsten Schritt" class="mt-2 w-full rounded border-gray-300 text-sm"></textarea><div class="mt-4 rounded-lg bg-gray-50 p-3"><p class="text-sm font-semibold">Bewerbungsunterlagen</p><label v-for="document in documentsForApplication(application)" :key="document.id" class="mt-2 flex items-center gap-2 text-sm"><input v-model="application.selected_document_ids" type="checkbox" :value="document.id"/>{{ document.original_name }}</label><p v-if="!documentsForApplication(application).length" class="mt-2 text-xs text-gray-500">Keine freigegebenen Dokumente vorhanden.</p><div class="mt-3 flex flex-wrap gap-2"><button class="rounded border px-3 py-2 text-xs" @click="saveApplicationPackage(application)">Auswahl speichern</button><button class="rounded bg-green-600 px-3 py-2 text-xs text-white" :disabled="!application.selected_document_ids?.length" @click="approveApplicationPackage(application)">Paket freigeben</button><span class="text-xs" :class="application.participant_package_approved_at?'text-green-700':'text-amber-700'">Teilnehmer: {{ application.participant_package_approved_at?'freigegeben':'offen' }}</span><span class="text-xs" :class="application.staff_package_approved_at?'text-green-700':'text-amber-700'">Projektteam: {{ application.staff_package_approved_at?'geprüft':'offen' }}</span></div></div></article><p v-if="!applicationItems.length" class="text-sm text-gray-500">Noch keine Bewerbungen angelegt.</p></div></section>
            </div>
            <section v-if="applicationItems.length" class="rounded-2xl border bg-white p-6 shadow-sm"><div class="flex flex-wrap justify-between gap-2"><div><h2 class="text-lg font-semibold">Versand, Unterlagen & Verlauf</h2><p class="text-sm text-gray-500">Professionelle Dokumente verknüpfen und Bewerbungen direkt versenden.</p></div><Link :href="route('participant-portal.career-studio.index')" class="h-fit rounded bg-zbb px-3 py-2 text-sm text-white">Bewerbungsstudio öffnen</Link></div><div class="mt-4 space-y-5"><article v-for="application in applicationItems" :key="`dispatch-${application.id}`" class="rounded-xl border p-4"><h3 class="font-bold">{{application.title}} · {{application.employer}}</h3><div class="mt-3 grid gap-2 md:grid-cols-3"><label v-for="document in careerDocuments" :key="document.id" class="flex items-center gap-2 rounded bg-blue-50 p-2 text-sm"><input v-model="application.selected_career_document_ids" type="checkbox" :value="document.id">{{document.title}}</label></div><button class="mt-2 rounded border px-3 py-2 text-xs" @click="saveCareerPackage(application)">Unterlagen verknüpfen</button><div class="mt-4 grid gap-2 sm:grid-cols-2"><input v-model="application.recipient_email" type="email" placeholder="Empfänger-E-Mail" class="rounded border-gray-300 text-sm"><input v-model="application.email_subject" placeholder="Betreff" class="rounded border-gray-300 text-sm"></div><textarea v-model="application.email_message" rows="4" class="mt-2 w-full rounded border-gray-300 text-sm"></textarea><button class="mt-2 rounded bg-emerald-700 px-4 py-2 text-sm text-white disabled:opacity-50" :disabled="application.sending||!application.selected_career_document_ids?.length" @click="sendApplication(application)">{{application.sending?'Wird versendet …':'Bewerbung per E-Mail versenden'}}</button><p v-if="application.last_sent_at" class="mt-2 text-xs text-emerald-700">Zuletzt versendet: {{new Date(application.last_sent_at).toLocaleString('de-DE')}} an {{application.recipient_email}}</p><div class="mt-4 border-t pt-3"><div class="flex gap-2"><input v-model="application.activity_note" class="flex-1 rounded border-gray-300 text-sm" placeholder="Gespräch, Rückmeldung oder nächsten Schritt notieren"><button class="rounded border px-3 text-sm" @click="addActivityNote(application)">Notiz</button></div><div class="mt-3 max-h-40 space-y-2 overflow-y-auto"><div v-for="activity in application.activities" :key="activity.id" class="border-l-2 border-gray-200 pl-3 text-xs"><b>{{activity.type==='email_sent'?'E-Mail versendet':activity.type==='note'?'Notiz':'Unterlagen geändert'}}</b><p>{{activity.body}}</p><span class="text-gray-400">{{new Date(activity.occurred_at).toLocaleString('de-DE')}}</span></div></div></div></article></div></section>
        </div>
    </ParticipantPortalLayout>
</template>
