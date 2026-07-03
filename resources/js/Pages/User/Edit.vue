<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import MultiSelect from 'primevue/multiselect';
import FloatLabel from 'primevue/floatlabel';

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
    }))
);

const addProjekt = () => {
    projektList.value.push({
        projekt_id: null,
        standort_ids: [],
    });
};

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
