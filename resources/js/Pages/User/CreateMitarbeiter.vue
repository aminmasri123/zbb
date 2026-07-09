<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import MultiSelect from 'primevue/multiselect';

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
    password: '',
    password_confirmation: '',
    rollen: [],
    projekt_zuweisungen: [
        {
            projekt_id: null,
            standort_ids: [],
        },
    ],
});

const errorFor = (field) => errors.value[field]?.[0] || '';

const addProjekt = () => {
    form.projekt_zuweisungen.push({
        projekt_id: null,
        standort_ids: [],
    });
};

const removeProjekt = (index) => {
    form.projekt_zuweisungen.splice(index, 1);
};

const submit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        await axios.post(route('user.store'), {
            ...form,
            projekt_zuweisungen: form.projekt_zuweisungen.filter((row) => row.projekt_id),
        });

        Swal.fire('Gespeichert!', 'Mitarbeiter wurde angelegt.', 'success');
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

                <div>
                    <InputLabel for="password" value="Passwort" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-2" :message="errorFor('password')" />
                </div>

                <div>
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
