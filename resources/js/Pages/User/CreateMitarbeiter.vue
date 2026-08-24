<script setup>
import { reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import MultiSelect from 'primevue/multiselect';
import {
    arbeitsbereichRoomsForProject,
    bueroRoomsForProject,
    emptyRoomAssignmentFields,
    normalizeDefaultRoom,
    resetRoomAssignments,
    roomLabel,
    selectedRoomsForRow,
} from '@/utils/projectRoomAssignments';

const props = defineProps({
    rollen: { type: Array, default: () => [] },
    alleProjekte: { type: Array, default: () => [] },
    alleStandorte: { type: Array, default: () => [] },
});

const processing = ref(false);
const errors = ref({});

const form = reactive({
    first_name: '',
    last_name: '',
    username: '',
    email: '',
    account_setup_method: 'email_invitation',
    password: '',
    password_confirmation: '',
    rollen: [],
    projekt_zuweisungen: [
        {
            projekt_id: null,
            standort_ids: [],
            bereich_ids: [],
            default_bereich_id: null,
            ...emptyRoomAssignmentFields(),
        },
    ],
});

const errorFor = (field) => errors.value[field]?.[0] || '';

watch(() => form.account_setup_method, (method) => {
    if (method === 'email_invitation') {
        form.password = '';
        form.password_confirmation = '';
    }
});

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
    form.projekt_zuweisungen.push({
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
    form.projekt_zuweisungen.splice(index, 1);
};

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const response = await axios.post(route('user.store'), {
            ...form,
            projekt_zuweisungen: form.projekt_zuweisungen.filter((row) => row.projekt_id),
        });

        const invitationFailed = response.data?.invitation_sent === false;
        await Swal.fire({
            title: invitationFailed ? 'Konto angelegt, E-Mail fehlgeschlagen' : 'Gespeichert!',
            text: response.data?.message || 'Mitarbeiter wurde angelegt.',
            icon: invitationFailed ? 'warning' : 'success',
        });
        router.visit(route('user.index'));
    } catch (error) {
        errors.value = error.response?.data?.errors || {};
        const message = error.response?.data?.message || 'Speichern fehlgeschlagen.';
        Swal.fire('Fehler', message, 'error');
    } finally {
        processing.value = false;
        form.password = '';
        form.password_confirmation = '';
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Benutzer anlegen" />

        <form @submit.prevent="submit" class="bg-white p-6 rounded-lg shadow-md">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-xl font-bold">Mitarbeiter anlegen</h1>
                <Link :href="route('user.index')" class="border px-3 py-2 text-sm">
                    Zurück
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                    <InputError class="mt-2" :message="errorFor('first_name')" />
                </div>

                <div>
                    <InputLabel for="last_name" value="Nachname" />
                    <TextInput
                        id="last_name"
                        v-model="form.last_name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="errorFor('last_name')" />
                </div>

                <div>
                    <InputLabel for="username" value="Benutzername" />
                    <TextInput
                        id="username"
                        v-model="form.username"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="errorFor('username')" />
                </div>

                <div>
                    <InputLabel for="email" value="E-Mail" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="errorFor('email')" />
                </div>

                <fieldset class="md:col-span-2">
                    <legend class="mb-2 text-sm font-semibold text-gray-700">Zugang einrichten</legend>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label
                            class="cursor-pointer rounded-lg border p-4"
                            :class="form.account_setup_method === 'email_invitation' ? 'border-zbb bg-orange-50' : 'border-gray-200'"
                        >
                            <span class="flex items-start gap-3">
                                <input v-model="form.account_setup_method" type="radio" value="email_invitation" class="mt-1 text-zbb" />
                                <span><strong class="block">Einladung per E-Mail</strong><small class="text-gray-600">Empfohlen: Der Mitarbeiter legt sein Passwort über einen sieben Tage gültigen Einmal-Link selbst fest.</small></span>
                            </span>
                        </label>
                        <label
                            class="cursor-pointer rounded-lg border p-4"
                            :class="form.account_setup_method === 'manual' ? 'border-zbb bg-orange-50' : 'border-gray-200'"
                        >
                            <span class="flex items-start gap-3">
                                <input v-model="form.account_setup_method" type="radio" value="manual" class="mt-1 text-zbb" />
                                <span><strong class="block">Passwort selbst vergeben</strong><small class="text-gray-600">Sie bestimmen ein Startpasswort und übergeben es dem Mitarbeiter separat.</small></span>
                            </span>
                        </label>
                    </div>
                    <InputError class="mt-2" :message="errorFor('account_setup_method')" />
                </fieldset>

                <div v-if="form.account_setup_method === 'manual'">
                    <InputLabel for="password" value="Passwort" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="new-password"
                    />
                    <p class="mt-1 text-xs text-gray-500">Mindestens 10 Zeichen mit Groß- und Kleinbuchstaben sowie einer Zahl.</p>
                    <InputError class="mt-2" :message="errorFor('password')" />
                </div>

                <div v-if="form.account_setup_method === 'manual'">
                    <InputLabel for="password_confirmation" value="Passwort bestätigen" />
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-2" :message="errorFor('password_confirmation')" />
                </div>

                <div class="md:col-span-2">
                    <InputLabel for="rollen" value="Rollen" />
                    <MultiSelect
                        id="rollen"
                        v-model="form.rollen"
                        :options="props.rollen"
                        optionLabel="name"
                        optionValue="id"
                        display="chip"
                        filter
                        filterPlaceholder="Rolle suchen …"
                        class="mt-1 w-full"
                        placeholder="Rollen auswählen"
                    />
                    <InputError class="mt-2" :message="errorFor('rollen')" />
                </div>
            </div>

            <div class="mt-8">
                <h2 class="mb-3 text-lg font-bold">Projekte & Standorte</h2>

                <div
                    v-for="(row, index) in form.projekt_zuweisungen"
                    :key="index"
                    class="mb-3 rounded border bg-gray-50 p-4"
                >
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel :for="`projekt_${index}`" value="Projekt" />
                            <select
                                :id="`projekt_${index}`"
                                v-model="row.projekt_id"
                                @change="resetProjektAssignments(row)"
                                class="mt-1 block w-full rounded border p-2"
                            >
                                <option :value="null">Projekt auswählen</option>
                                <option
                                    v-for="projekt in props.alleProjekte"
                                    :key="projekt.id"
                                    :value="projekt.id"
                                >
                                    {{ projekt.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="errorFor(`projekt_zuweisungen.${index}.projekt_id`)" />
                        </div>

                        <div>
                            <InputLabel :for="`standorte_${index}`" value="Standorte" />
                            <MultiSelect
                                :id="`standorte_${index}`"
                                v-model="row.standort_ids"
                                :options="props.alleStandorte"
                                optionLabel="name"
                                optionValue="id"
                                display="chip"
                                filter
                                class="mt-1 w-full"
                                placeholder="Standorte auswählen"
                            />
                            <InputError class="mt-2" :message="errorFor(`projekt_zuweisungen.${index}.standort_ids`)" />
                        </div>

                        <div>
                            <InputLabel :for="`bereiche_${index}`" value="Bereiche" />
                            <MultiSelect
                                :id="`bereiche_${index}`"
                                v-model="row.bereich_ids"
                                :options="bereicheForProjekt(row.projekt_id)"
                                optionLabel="name"
                                optionValue="id"
                                display="chip"
                                filter
                                class="mt-1 w-full"
                                placeholder="Bereiche auswÃ¤hlen"
                                :disabled="!row.projekt_id"
                                @change="normalizeDefaultBereich(row)"
                            />
                            <InputError class="mt-2" :message="errorFor(`projekt_zuweisungen.${index}.bereich_ids`)" />
                        </div>

                        <div>
                            <InputLabel :for="`standard_bereich_${index}`" value="Standardbereich" />
                            <select
                                :id="`standard_bereich_${index}`"
                                v-model="row.default_bereich_id"
                                class="mt-1 block w-full rounded border p-2 disabled:bg-gray-100"
                                :disabled="selectedBereicheForRow(row).length === 0"
                            >
                                <option :value="null">Kein Standard</option>
                                <option
                                    v-for="bereich in selectedBereicheForRow(row)"
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
                                v-model="row.buero_raum_ids"
                                :options="bueroRoomsForProject(props.alleProjekte, row.projekt_id)"
                                :optionLabel="roomLabel"
                                optionValue="id"
                                display="chip"
                                filter
                                class="mt-1 w-full"
                                placeholder="Büros auswählen"
                                :disabled="!row.projekt_id"
                                @change="normalizeDefaultRoom(row, 'buero_raum_ids', 'default_buero_raum_id')"
                            />
                            <InputError class="mt-2" :message="errorFor(`projekt_zuweisungen.${index}.buero_raum_ids`)" />
                        </div>

                        <div>
                            <InputLabel :for="`standard_buero_${index}`" value="Standardbüro" />
                            <select
                                :id="`standard_buero_${index}`"
                                v-model="row.default_buero_raum_id"
                                class="mt-1 block w-full rounded border p-2 disabled:bg-gray-100"
                                :disabled="selectedBueroRaeumeForRow(row).length === 0"
                            >
                                <option :value="null">Kein Standard</option>
                                <option
                                    v-for="raum in selectedBueroRaeumeForRow(row)"
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
                                v-model="row.arbeitsbereich_raum_ids"
                                :options="arbeitsbereichRoomsForProject(props.alleProjekte, row.projekt_id)"
                                :optionLabel="roomLabel"
                                optionValue="id"
                                display="chip"
                                filter
                                class="mt-1 w-full"
                                placeholder="Arbeitsbereiche auswählen"
                                :disabled="!row.projekt_id"
                                @change="normalizeDefaultRoom(row, 'arbeitsbereich_raum_ids', 'default_arbeitsbereich_raum_id')"
                            />
                            <InputError class="mt-2" :message="errorFor(`projekt_zuweisungen.${index}.arbeitsbereich_raum_ids`)" />
                        </div>

                        <div>
                            <InputLabel :for="`standard_arbeitsbereich_${index}`" value="Standard-Arbeitsbereich" />
                            <select
                                :id="`standard_arbeitsbereich_${index}`"
                                v-model="row.default_arbeitsbereich_raum_id"
                                class="mt-1 block w-full rounded border p-2 disabled:bg-gray-100"
                                :disabled="selectedArbeitsbereichRaeumeForRow(row).length === 0"
                            >
                                <option :value="null">Kein Standard</option>
                                <option
                                    v-for="raum in selectedArbeitsbereichRaeumeForRow(row)"
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
                        v-if="form.projekt_zuweisungen.length > 1"
                        type="button"
                        @click="removeProjekt(index)"
                        class="mt-3 text-sm text-red-600"
                    >
                        Projekt entfernen
                    </button>
                </div>

                <button type="button" @click="addProjekt" class="rounded bg-gray-200 px-3 py-1 text-sm">
                    + Projekt hinzufügen
                </button>
            </div>

            <div class="mt-6 flex justify-end">
                <button
                    type="submit"
                    class="rounded bg-zbb px-4 py-2 text-white disabled:opacity-50"
                    :disabled="processing"
                >
                    Speichern
                </button>
            </div>
        </form>
    </AppLayout>
</template>
