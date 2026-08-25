<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import MultiSelect from 'primevue/multiselect';
import FloatLabel from 'primevue/floatlabel';
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

import AppLayout from '@/Layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();


const props = defineProps({
    user: Object,
    rollen: Array,
    alleProjekte: Array,
    alleStandorte: Array,
    zuweisungen: Array,
});

// Formular mit bestehenden Werten initialisieren
const form = useForm({
    id: props.user.id || '',
    first_name: props.user.person?.vorname || '',
    last_name: props.user.person?.nachname || '',
    geschlecht: props.user.person?.geschlecht || 'd',
    username: props.user.username || '',
    email: props.user.email || '',
    password: '',
    password_confirmation: '',
    rollen: props.user.roles?.map(r => r.id) || [] || '' // Array mit IDs
});

const projektList = ref(
    (props.zuweisungen || []).map((zuweisung) => ({
        projekt_id: zuweisung.projekt_id,
        standort_ids: Array.isArray(zuweisung.standort_ids) ? zuweisung.standort_ids : [],
        bereich_ids: Array.isArray(zuweisung.bereich_ids) ? zuweisung.bereich_ids : [],
        default_bereich_id: zuweisung.default_bereich_id ?? null,
        buero_raum_ids: Array.isArray(zuweisung.buero_raum_ids) ? zuweisung.buero_raum_ids : [],
        default_buero_raum_id: zuweisung.default_buero_raum_id ?? null,
        arbeitsbereich_raum_ids: Array.isArray(zuweisung.arbeitsbereich_raum_ids) ? zuweisung.arbeitsbereich_raum_ids : [],
        default_arbeitsbereich_raum_id: zuweisung.default_arbeitsbereich_raum_id ?? null,
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

const removeProjekt = (index) => {
    projektList.value.splice(index, 1);
};

 // Speichern (PUT/PATCH)
const submit = () => {
    form
        .transform((data) => ({
            ...data,
            projekt_zuweisungen: projektList.value,
        }))
        .put(route('user.update', props.user.id), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
};
</script>

<template>
    <app-layout>
        <Head title="Benutzer bearbeiten"/>
          <slot />

        <form @submit.prevent="submit" method="POST" class="bg-white p-6 rounded-lg shadow-md">
            <h1 class="text-xl font-bold mb-4">Benutzer bearbeiten</h1>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <InputLabel for="id" value="Identifikationsnummer" />
                    <TextInput
                        id="id"
                        disabled
                        type="text"
                        class="mt-1 block w-full bg-slate-200"
                        v-model="form.id"
                        required
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.id" />
                </div>
                <div class=" w-full mx-1">
                    <InputLabel for="rollen" value="Rollen" />
                    <MultiSelect
                        required
                        v-model="form.rollen"
                        :options="rollen"
                        optionLabel="name"
                        optionValue="id"
                        display="chip"
                        filter
                        filterPlaceholder="Rolle suchen …"
                        class="w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.rollen" />

                </div>
                 <div>
                    <InputLabel for="username" value="Benutzername" />
                    <TextInput
                        id="username"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.username"
                        required
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.username" />
                </div>
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
                <div>
                    <InputLabel for="first_name" value="Vorname" />
                    <TextInput
                        id="first_name"
                        v-model="form.first_name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.first_name" />
                </div>
                <div>
                    <InputLabel for="last_name" value="Nachname" />
                    <TextInput
                        id="last_name"
                        v-model="form.last_name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.last_name" />
                </div>

                <div>
                    <InputLabel for="geschlecht" value="Geschlecht" />
                    <select id="geschlecht" v-model="form.geschlecht" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                        <option value="w">weiblich</option>
                        <option value="m">männlich</option>
                        <option value="d">divers</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.geschlecht" />
                </div>

                <div>
                    <InputLabel for="password" value="Password" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>
                <div>
                    <InputLabel for="password_confirmation" value="Confirm Password" />
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

            </div>
            <div class="mt-8">
                <h2 class="text-lg font-bold mb-3">Projekte & Standorte</h2>

                <div
                    v-for="(projektZeile, index) in projektList"
                    :key="index"
                    class="p-4 border rounded mb-3 bg-gray-50"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel :for="`projekt_${index}`" value="Projekt" />
                            <select
                                :id="`projekt_${index}`"
                                v-model="projektZeile.projekt_id"
                                @change="resetProjektAssignments(projektZeile)"
                                class="mt-1 block w-full border p-2 rounded"
                            >
                                <option value="">-- Projekt auswahlen --</option>
                                <option
                                    v-for="projekt in alleProjekte"
                                    :key="projekt.id"
                                    :value="projekt.id"
                                >
                                    {{ projekt.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <InputLabel :for="`standorte_${index}`" value="Standorte" />
                            <MultiSelect
                                :id="`standorte_${index}`"
                                v-model="projektZeile.standort_ids"
                                :options="alleStandorte"
                                optionLabel="name"
                                optionValue="id"
                                display="chip"
                                class="w-full mt-1"
                                placeholder="Standorte auswahlen..."
                            />
                        </div>

                        <div>
                            <InputLabel :for="`bereiche_${index}`" value="Bereiche" />
                            <MultiSelect
                                :id="`bereiche_${index}`"
                                v-model="projektZeile.bereich_ids"
                                :options="bereicheForProjekt(projektZeile.projekt_id)"
                                optionLabel="name"
                                optionValue="id"
                                display="chip"
                                filter
                                class="w-full mt-1"
                                placeholder="Bereiche auswahlen..."
                                :disabled="!projektZeile.projekt_id"
                                @change="normalizeDefaultBereich(projektZeile)"
                            />
                            <InputError class="mt-2" :message="errorFor(`projekt_zuweisungen.${index}.bereich_ids`)" />
                        </div>

                        <div>
                            <InputLabel :for="`standard_bereich_${index}`" value="Standardbereich" />
                            <select
                                :id="`standard_bereich_${index}`"
                                v-model="projektZeile.default_bereich_id"
                                class="mt-1 block w-full border p-2 rounded disabled:bg-gray-100"
                                :disabled="selectedBereicheForRow(projektZeile).length === 0"
                            >
                                <option :value="null">Kein Standard</option>
                                <option
                                    v-for="bereich in selectedBereicheForRow(projektZeile)"
                                    :key="bereich.id"
                                    :value="bereich.id"
                                >
                                    {{ bereich.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="errorFor(`projekt_zuweisungen.${index}.default_bereich_id`)" />
                        </div>

                        <div>
                            <InputLabel :for="`buero_raeume_${index}`" value="Büros" />
                            <MultiSelect
                                :id="`buero_raeume_${index}`"
                                v-model="projektZeile.buero_raum_ids"
                                :options="bueroRoomsForProject(props.alleProjekte, projektZeile.projekt_id)"
                                :optionLabel="roomLabel"
                                optionValue="id"
                                display="chip"
                                filter
                                class="w-full mt-1"
                                placeholder="Büros auswählen..."
                                :disabled="!projektZeile.projekt_id"
                                @change="normalizeDefaultRoom(projektZeile, 'buero_raum_ids', 'default_buero_raum_id')"
                            />
                            <InputError class="mt-2" :message="errorFor(`projekt_zuweisungen.${index}.buero_raum_ids`)" />
                        </div>

                        <div>
                            <InputLabel :for="`standard_buero_${index}`" value="Standardbüro" />
                            <select
                                :id="`standard_buero_${index}`"
                                v-model="projektZeile.default_buero_raum_id"
                                class="mt-1 block w-full border p-2 rounded disabled:bg-gray-100"
                                :disabled="selectedBueroRaeumeForRow(projektZeile).length === 0"
                            >
                                <option :value="null">Kein Standard</option>
                                <option
                                    v-for="raum in selectedBueroRaeumeForRow(projektZeile)"
                                    :key="raum.id"
                                    :value="raum.id"
                                >
                                    {{ roomLabel(raum) }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="errorFor(`projekt_zuweisungen.${index}.default_buero_raum_id`)" />
                        </div>

                        <div>
                            <InputLabel :for="`arbeitsbereich_raeume_${index}`" value="Arbeitsbereiche" />
                            <MultiSelect
                                :id="`arbeitsbereich_raeume_${index}`"
                                v-model="projektZeile.arbeitsbereich_raum_ids"
                                :options="arbeitsbereichRoomsForProject(props.alleProjekte, projektZeile.projekt_id)"
                                :optionLabel="roomLabel"
                                optionValue="id"
                                display="chip"
                                filter
                                class="w-full mt-1"
                                placeholder="Arbeitsbereiche auswählen..."
                                :disabled="!projektZeile.projekt_id"
                                @change="normalizeDefaultRoom(projektZeile, 'arbeitsbereich_raum_ids', 'default_arbeitsbereich_raum_id')"
                            />
                            <InputError class="mt-2" :message="errorFor(`projekt_zuweisungen.${index}.arbeitsbereich_raum_ids`)" />
                        </div>

                        <div>
                            <InputLabel :for="`standard_arbeitsbereich_${index}`" value="Standard-Arbeitsbereich" />
                            <select
                                :id="`standard_arbeitsbereich_${index}`"
                                v-model="projektZeile.default_arbeitsbereich_raum_id"
                                class="mt-1 block w-full border p-2 rounded disabled:bg-gray-100"
                                :disabled="selectedArbeitsbereichRaeumeForRow(projektZeile).length === 0"
                            >
                                <option :value="null">Kein Standard</option>
                                <option
                                    v-for="raum in selectedArbeitsbereichRaeumeForRow(projektZeile)"
                                    :key="raum.id"
                                    :value="raum.id"
                                >
                                    {{ roomLabel(raum) }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="errorFor(`projekt_zuweisungen.${index}.default_arbeitsbereich_raum_id`)" />
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
                    + Projekt hinzufugen
                </button>
            </div>

            <div class="my-4">

            <button type="submit" class="bg-zbb text-white px-4 py-2 rounded">Hinzufügen</button>
            </div>
        </form>
    </app-layout>
</template>
