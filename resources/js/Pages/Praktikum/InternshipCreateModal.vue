<script setup>
import Modal from '@/Components/ModalForm.vue';
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    visible: Boolean,
    locations: { type: Array, default: () => [] },
    hostProjects: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const defaults = () => ({
    vorname: '',
    nachname: '',
    geschlecht: '',
    geburtsdatum: '',
    standort_id: props.locations.length === 1 ? props.locations[0].id : '',
    strasse: '',
    hausnummer: '',
    plz: '',
    stadt: '',
    land: 'Deutschland',
    telefon: '',
    email: '',
    placement_type: 'internal',
    traeger: '',
    host_project_id: '',
    supervisor_person_id: '',
    host_address: '',
    department: '',
    internship_kind: 'orientation',
    occupation: '',
    attendance_weekday: '',
    contact_name: '',
    contact_email: '',
    contact_phone: '',
    weekly_hours: 39,
    start: '',
    end: '',
    next_follow_up_at: '',
    objective: '',
    bemerkung: '',
    status: 'geplant',
});

const form = useForm(defaults());
const selectedHostProject = computed(() => props.hostProjects.find((project) => Number(project.id) === Number(form.host_project_id)));
const availableSupervisors = computed(() => selectedHostProject.value?.mitarbeiter || []);

watch(() => form.host_project_id, () => {
    if (!availableSupervisors.value.some((person) => Number(person.id) === Number(form.supervisor_person_id))) {
        form.supervisor_person_id = '';
    }
});

watch(() => form.placement_type, () => {
    form.clearErrors('traeger', 'host_project_id', 'supervisor_person_id');
});

const close = () => {
    if (form.processing) return;
    form.reset();
    form.clearErrors();
    emit('close');
};

const submit = () => {
    form.post(route('internships.store'), {
        preserveScroll: true,
        onError: () => document.querySelector('[data-internship-create-errors]')?.scrollIntoView({ behavior: 'smooth', block: 'center' }),
    });
};

const fieldClass = (field) => [
    'mt-1 w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-zbb focus:ring-zbb',
    form.errors[field] ? 'border-red-400' : 'border-gray-300',
];
</script>

<template>
    <Modal v-if="visible" scrollable-layout @close="close">
        <template #header>Praktikant:in anlegen</template>

        <template #body>
            <form id="internship-direct-create-form" class="space-y-5" @submit.prevent="submit">
                <div class="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">
                    Dieser Weg ist für Praktikant:innen vorgesehen. Die Person wird dem aktiven Projekt zugeordnet, aber nicht als Schüler:in erfasst. Eine Schule ist deshalb nicht erforderlich.
                </div>

                <div v-if="Object.keys(form.errors).length" data-internship-create-errors class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <p class="font-semibold">Bitte prüfe die markierten Angaben:</p>
                    <ul class="mt-1 list-disc pl-5"><li v-for="(message, field) in form.errors" :key="field">{{ message }}</li></ul>
                </div>

                <section class="rounded-xl border border-gray-200 p-4">
                    <h3 class="mb-3 font-semibold text-gray-900">1. Person</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm text-gray-700">Vorname *<input v-model="form.vorname" type="text" :class="fieldClass('vorname')" /></label>
                        <label class="text-sm text-gray-700">Nachname *<input v-model="form.nachname" type="text" :class="fieldClass('nachname')" /></label>
                        <label class="text-sm text-gray-700">Geschlecht *
                            <select v-model="form.geschlecht" :class="fieldClass('geschlecht')"><option value="">Bitte wählen</option><option value="w">Weiblich</option><option value="m">Männlich</option><option value="d">Divers</option></select>
                        </label>
                        <label class="text-sm text-gray-700">Geburtsdatum *<input v-model="form.geburtsdatum" type="date" :class="fieldClass('geburtsdatum')" /></label>
                        <label class="text-sm text-gray-700">Projektstandort *
                            <select v-model="form.standort_id" :class="fieldClass('standort_id')"><option value="">Bitte wählen</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                        </label>
                        <label class="text-sm text-gray-700">Telefon<input v-model="form.telefon" type="text" :class="fieldClass('telefon')" /></label>
                        <label class="text-sm text-gray-700 md:col-span-2">E-Mail<input v-model="form.email" type="email" :class="fieldClass('email')" /></label>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 p-4">
                    <h3 class="mb-3 font-semibold text-gray-900">2. Anschrift der Person</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm text-gray-700">Straße *<input v-model="form.strasse" type="text" :class="fieldClass('strasse')" /></label>
                        <label class="text-sm text-gray-700">Hausnummer *<input v-model="form.hausnummer" type="text" :class="fieldClass('hausnummer')" /></label>
                        <label class="text-sm text-gray-700">PLZ *<input v-model="form.plz" type="text" :class="fieldClass('plz')" /></label>
                        <label class="text-sm text-gray-700">Ort *<input v-model="form.stadt" type="text" :class="fieldClass('stadt')" /></label>
                        <label class="text-sm text-gray-700 md:col-span-2">Land<input v-model="form.land" type="text" :class="fieldClass('land')" /></label>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 p-4">
                    <h3 class="mb-3 font-semibold text-gray-900">3. Praktikum</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm text-gray-700">Praktikumsstelle *
                            <select v-model="form.placement_type" :class="fieldClass('placement_type')"><option value="internal">Intern – eigenes Projekt</option><option value="external">Extern – anderer Betrieb</option></select>
                        </label>

                        <template v-if="form.placement_type === 'internal'">
                            <label class="text-sm text-gray-700">Internes Einsatzprojekt *
                                <select v-model="form.host_project_id" :class="fieldClass('host_project_id')"><option value="">Bitte wählen</option><option v-for="project in hostProjects" :key="project.id" :value="project.id">{{ project.name }}</option></select>
                            </label>
                            <label class="text-sm text-gray-700">Fachliche Betreuung *
                                <select v-model="form.supervisor_person_id" :disabled="!form.host_project_id" :class="fieldClass('supervisor_person_id')"><option value="">Bitte wählen</option><option v-for="person in availableSupervisors" :key="person.id" :value="person.id">{{ person.vorname }} {{ person.nachname }}</option></select>
                            </label>
                        </template>

                        <template v-else>
                            <label class="text-sm text-gray-700">Praktikumsbetrieb *<input v-model="form.traeger" type="text" :class="fieldClass('traeger')" /></label>
                            <label class="text-sm text-gray-700">Ansprechpartner:in<input v-model="form.contact_name" type="text" :class="fieldClass('contact_name')" /></label>
                            <label class="text-sm text-gray-700">E-Mail des Betriebs<input v-model="form.contact_email" type="email" :class="fieldClass('contact_email')" /></label>
                            <label class="text-sm text-gray-700">Telefon des Betriebs<input v-model="form.contact_phone" type="text" :class="fieldClass('contact_phone')" /></label>
                        </template>

                        <label class="text-sm text-gray-700 md:col-span-2">Anschrift der Einsatzstelle<input v-model="form.host_address" type="text" :class="fieldClass('host_address')" placeholder="Straße, PLZ Ort" /></label>
                        <label class="text-sm text-gray-700">Einsatzbereich / Abteilung<input v-model="form.department" type="text" :class="fieldClass('department')" /></label>
                        <label class="text-sm text-gray-700">Beruf / Tätigkeitsbereich<input v-model="form.occupation" type="text" :class="fieldClass('occupation')" /></label>
                        <label class="text-sm text-gray-700">Art des Praktikums
                            <select v-model="form.internship_kind" :class="fieldClass('internship_kind')"><option value="orientation">Orientierungspraktikum</option><option value="qualification">Qualifizierungspraktikum</option><option value="integration">Eingliederungspraktikum</option></select>
                        </label>
                        <label class="text-sm text-gray-700">Betreuungs-/Beschulungstag<input v-model="form.attendance_weekday" type="text" :class="fieldClass('attendance_weekday')" placeholder="z. B. Mittwoch" /></label>
                        <label class="text-sm text-gray-700">Beginn *<input v-model="form.start" type="date" :class="fieldClass('start')" /></label>
                        <label class="text-sm text-gray-700">Ende *<input v-model="form.end" type="date" :class="fieldClass('end')" /></label>
                        <label class="text-sm text-gray-700">Wochenstunden<input v-model="form.weekly_hours" type="number" min="1" max="168" :class="fieldClass('weekly_hours')" /></label>
                        <label class="text-sm text-gray-700">Status
                            <select v-model="form.status" :class="fieldClass('status')"><option value="geplant">Geplant</option><option value="laufend">Laufend</option></select>
                        </label>
                        <label class="text-sm text-gray-700">Nächste Nachverfolgung<input v-model="form.next_follow_up_at" type="date" :class="fieldClass('next_follow_up_at')" /></label>
                        <label class="text-sm text-gray-700 md:col-span-2">Ziel<textarea v-model="form.objective" rows="3" :class="fieldClass('objective')"></textarea></label>
                        <label class="text-sm text-gray-700 md:col-span-2">Bemerkung<textarea v-model="form.bemerkung" rows="3" :class="fieldClass('bemerkung')"></textarea></label>
                    </div>
                </section>
            </form>
        </template>

        <template #footer>
            <button type="submit" form="internship-direct-create-form" :disabled="form.processing" class="rounded-lg bg-zbb px-4 py-2 font-medium text-white disabled:opacity-50">{{ form.processing ? 'Wird angelegt …' : 'Praktikant:in anlegen' }}</button>
            <button type="button" :disabled="form.processing" class="rounded-lg border px-4 py-2 text-gray-700" @click="close">Abbrechen</button>
        </template>
    </Modal>
</template>
