<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import MultiSelect from 'primevue/multiselect';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    arbeitsbereichRoomsForProject,
    bueroRoomsForProject,
    emptyRoomAssignmentFields,
    ensureProjectRoomAssignmentFields,
    normalizeDefaultRoom,
    resetRoomAssignments,
    roomLabel,
    selectedRoomsForRow,
} from '@/utils/projectRoomAssignments';

const props = defineProps({
    person: Object,
    rollen: Array,
    alleProjekte: Array,
    alleStandorte: Array,
    zuweisungen: Array, // [{ projekt_id, projekt_name, standort_ids: [1,2,...] }]
});

console.log(props.alleStandorte)
// Basis-Form
const form = useForm({
    id: props.person.id ?? '',
    first_name: props.person.vorname ?? '',
    last_name: props.person.nachname ?? '',
    username: props.person.user.username ?? '',
    email: props.person.user.email ?? '',
    password: '',
    password_confirmation: '',
    rollen: props.person.user.roles?.map(r => r.id) ?? [],
});

// Projekte + Standorte (reaktive Kopie)
const projektList = ref(
    (props.zuweisungen || []).map(z => ({
        projekt_id: z.projekt_id,
        standort_ids: Array.isArray(z.standort_ids) ? z.standort_ids : [],
        bereich_ids: Array.isArray(z.bereich_ids) ? z.bereich_ids : [],
        default_bereich_id: z.default_bereich_id ?? null,
        buero_raum_ids: Array.isArray(z.buero_raum_ids) ? z.buero_raum_ids : [],
        default_buero_raum_id: z.default_buero_raum_id ?? null,
        arbeitsbereich_raum_ids: Array.isArray(z.arbeitsbereich_raum_ids) ? z.arbeitsbereich_raum_ids : [],
        default_arbeitsbereich_raum_id: z.default_arbeitsbereich_raum_id ?? null,
    }))
);

projektList.value.forEach(ensureProjectRoomAssignmentFields);

const errorFor = (field) => form.errors[field] || '';

const bereicheForProjekt = (projektId) => {
    return props.alleProjekte.find((projekt) => Number(projekt.id) === Number(projektId))?.bereiche || [];
};

const selectedBereicheForRow = (row) => {
    const selectedIds = (row.bereich_ids || []).map((id) => Number(id));

    return bereicheForProjekt(row.projekt_id).filter((bereich) => selectedIds.includes(Number(bereich.id)));
};

const resetProjektBereiche = (row) => {
    row.bereich_ids = [];
    row.default_bereich_id = null;
};

const resetProjektAssignments = (row) => {
    resetProjektBereiche(row);
    resetRoomAssignments(row);
};

const normalizeDefaultBereich = (row) => {
    const selectedIds = (row.bereich_ids || []).map((id) => Number(id));

    if (selectedIds.length === 1) {
        row.default_bereich_id = selectedIds[0];
        return;
    }

    if (!selectedIds.includes(Number(row.default_bereich_id))) {
        row.default_bereich_id = null;
    }
};

// Hilfsfunktion: neues leeres Projekt
const addProjekt = () => {
    projektList.value.push({
        projekt_id: null,
        standort_ids: [],
        bereich_ids: [],
        default_bereich_id: null,
        ...emptyRoomAssignmentFields(),
    });
};

const selectedBueroRaeumeForRow = (row) =>
    selectedRoomsForRow(props.alleProjekte, row, 'buero_raum_ids', bueroRoomsForProject);

const selectedArbeitsbereichRaeumeForRow = (row) =>
    selectedRoomsForRow(props.alleProjekte, row, 'arbeitsbereich_raum_ids', arbeitsbereichRoomsForProject);

// Projekt entfernen (nur aus Frontend-Liste – DB wird im Controller neu geschrieben)
const removeProjekt = (index) => {
    projektList.value.splice(index, 1);
};

// Für Anzeige im Select (falls du Projektname im Chip brauchst)
const getProjektById = (id) => {
    return props.alleProjekte.find(p => p.id === id) || null;
};

// Submit mit Projekten + Standorten
const submit = () => {
    form
        .transform(data => ({
            ...data,
            projekt_zuweisungen: projektList.value,
        }))
        .put(route('personal.update', props.person.id), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
};
</script>

<template>
    <AppLayout>
        <Head title="Benutzer bearbeiten" />

        <form @submit.prevent="submit" class="bg-white p-6 rounded-lg shadow-md max-w-5xl mx-auto">
            <h1 class="text-xl font-bold mb-4">Benutzer bearbeiten</h1>

            <div class="grid grid-cols-2 gap-4">

                <!-- ID -->
                <div>
                    <InputLabel for="id" value="Identifikationsnummer" />
                    <TextInput
                        id="id"
                        disabled
                        class="mt-1 block w-full bg-slate-200"
                        v-model="form.id"
                    />
                    <InputError :message="form.errors.id" class="mt-2" />
                </div>

                <!-- Rollen -->
                <div>
                    <InputLabel for="rollen" value="Rollen" />
                    <MultiSelect
                        id="rollen"
                        v-model="form.rollen"
                        :options="rollen"
                        optionLabel="name"
                        optionValue="id"
                        display="chip"
                        class="w-full"
                    />



                    <InputError :message="form.errors.rollen" class="mt-2" />
                </div>

                <!-- Benutzername -->
                <div>
                    <InputLabel for="username" value="Benutzername" />
                    <TextInput
                        id="username"
                        v-model="form.username"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.username" class="mt-2" />
                </div>

                <!-- Email -->
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.email" class="mt-2" />
                </div>

                <!-- Vorname -->
                <div>
                    <InputLabel for="first_name" value="Vorname" />
                    <TextInput
                        id="first_name"
                        v-model="form.first_name"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.first_name" class="mt-2" />
                </div>

                <!-- Nachname -->
                <div>
                    <InputLabel for="last_name" value="Nachname" />
                    <TextInput
                        id="last_name"
                        v-model="form.last_name"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.last_name" class="mt-2" />
                </div>

                <!-- Passwort -->
                <div>
                    <InputLabel for="password" value="Passwort (optional)" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <!-- Passwort-Bestätigung -->
                <div>
                    <InputLabel for="password_confirmation" value="Passwort bestätigen" />
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password_confirmation" class="mt-2" />
                </div>
            </div>

            <!-- Projekte & Standorte -->
            <div class="mt-8">
                <h2 class="text-lg font-bold mb-3">Projekte & Standorte</h2>

                <div
                    v-for="(p, index) in projektList"
                    :key="index"
                    class="p-4 border rounded mb-3 bg-gray-50"
                >
                    <div class="grid grid-cols-2 gap-4">

                        <!-- Projekt auswählen -->
                        <div>
                            <InputLabel :for="`projekt_${index}`" value="Projekt" />
                            <select
                                :id="`projekt_${index}`"
                                v-model="p.projekt_id"
                                @change="resetProjektAssignments(p)"
                                class="mt-1 block w-full border p-2 rounded"
                            >
                                <option value="">-- Projekt auswählen --</option>
                                <option
                                    v-for="projekt in alleProjekte"
                                    :key="projekt.id"
                                    :value="projekt.id"
                                >
                                    {{ projekt.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Standorte als MultiSelect -->
                        <div class="relative z-50">
                            <InputLabel :for="`standorte_${index}`" value="Standorte" />
                           <MultiSelect
                                :id="`standorte_${index}`"
                                v-model="p.standort_ids"
                                :options="alleStandorte"
                                optionLabel="name"
                                optionValue="id"
                                display="chip"
                                class="w-full mt-1"
                                placeholder="Standorte auswählen..."
                            />

                        </div>

                        <div>
                            <InputLabel :for="`bereiche_${index}`" value="Bereiche" />
                            <MultiSelect
                                :id="`bereiche_${index}`"
                                v-model="p.bereich_ids"
                                :options="bereicheForProjekt(p.projekt_id)"
                                optionLabel="name"
                                optionValue="id"
                                display="chip"
                                filter
                                class="w-full mt-1"
                                placeholder="Bereiche auswÃ¤hlen..."
                                :disabled="!p.projekt_id"
                                @change="normalizeDefaultBereich(p)"
                            />
                            <InputError :message="errorFor(`projekt_zuweisungen.${index}.bereich_ids`)" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel :for="`standard_bereich_${index}`" value="Standardbereich" />
                            <select
                                :id="`standard_bereich_${index}`"
                                v-model="p.default_bereich_id"
                                class="mt-1 block w-full border p-2 rounded disabled:bg-gray-100"
                                :disabled="selectedBereicheForRow(p).length === 0"
                            >
                                <option :value="null">Kein Standard</option>
                                <option
                                    v-for="bereich in selectedBereicheForRow(p)"
                                    :key="bereich.id"
                                    :value="bereich.id"
                                >
                                    {{ bereich.name }}
                                </option>
                            </select>
                            <InputError :message="errorFor(`projekt_zuweisungen.${index}.default_bereich_id`)" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel :for="`buero_raeume_${index}`" value="Büros" />
                            <MultiSelect
                                :id="`buero_raeume_${index}`"
                                v-model="p.buero_raum_ids"
                                :options="bueroRoomsForProject(props.alleProjekte, p.projekt_id)"
                                :optionLabel="roomLabel"
                                optionValue="id"
                                display="chip"
                                filter
                                class="w-full mt-1"
                                placeholder="Büros auswählen..."
                                :disabled="!p.projekt_id"
                                @change="normalizeDefaultRoom(p, 'buero_raum_ids', 'default_buero_raum_id')"
                            />
                            <InputError :message="errorFor(`projekt_zuweisungen.${index}.buero_raum_ids`)" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel :for="`standard_buero_${index}`" value="Standardbüro" />
                            <select
                                :id="`standard_buero_${index}`"
                                v-model="p.default_buero_raum_id"
                                class="mt-1 block w-full border p-2 rounded disabled:bg-gray-100"
                                :disabled="selectedBueroRaeumeForRow(p).length === 0"
                            >
                                <option :value="null">Kein Standard</option>
                                <option
                                    v-for="raum in selectedBueroRaeumeForRow(p)"
                                    :key="raum.id"
                                    :value="raum.id"
                                >
                                    {{ roomLabel(raum) }}
                                </option>
                            </select>
                            <InputError :message="errorFor(`projekt_zuweisungen.${index}.default_buero_raum_id`)" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel :for="`arbeitsbereich_raeume_${index}`" value="Arbeitsbereiche" />
                            <MultiSelect
                                :id="`arbeitsbereich_raeume_${index}`"
                                v-model="p.arbeitsbereich_raum_ids"
                                :options="arbeitsbereichRoomsForProject(props.alleProjekte, p.projekt_id)"
                                :optionLabel="roomLabel"
                                optionValue="id"
                                display="chip"
                                filter
                                class="w-full mt-1"
                                placeholder="Arbeitsbereiche auswählen..."
                                :disabled="!p.projekt_id"
                                @change="normalizeDefaultRoom(p, 'arbeitsbereich_raum_ids', 'default_arbeitsbereich_raum_id')"
                            />
                            <InputError :message="errorFor(`projekt_zuweisungen.${index}.arbeitsbereich_raum_ids`)" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel :for="`standard_arbeitsbereich_${index}`" value="Standard-Arbeitsbereich" />
                            <select
                                :id="`standard_arbeitsbereich_${index}`"
                                v-model="p.default_arbeitsbereich_raum_id"
                                class="mt-1 block w-full border p-2 rounded disabled:bg-gray-100"
                                :disabled="selectedArbeitsbereichRaeumeForRow(p).length === 0"
                            >
                                <option :value="null">Kein Standard</option>
                                <option
                                    v-for="raum in selectedArbeitsbereichRaeumeForRow(p)"
                                    :key="raum.id"
                                    :value="raum.id"
                                >
                                    {{ roomLabel(raum) }}
                                </option>
                            </select>
                            <InputError :message="errorFor(`projekt_zuweisungen.${index}.default_arbeitsbereich_raum_id`)" class="mt-2" />
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="removeProjekt(index)"
                        class="text-red-600 mt-3 text-sm"
                    >
                        Projekt entfernen
                    </button>
                </div>

                <button
                    type="button"
                    @click="addProjekt"
                    class="bg-gray-200 px-3 py-1 rounded text-sm"
                >
                    + Projekt hinzufügen
                </button>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="bg-zbb text-white px-4 py-2 rounded">
                    Speichern
                </button>
            </div>
        </form>
    </AppLayout>
</template>
