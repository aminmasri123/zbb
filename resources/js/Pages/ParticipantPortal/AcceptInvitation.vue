<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ token: String, email: String, participantName: String, expiresAt: String });
const form = useForm({ password: '', password_confirmation: '' });
const submit = () => form.post(route('participant-portal.invitation.accept', props.token), { onFinish: () => form.reset() });
</script>

<template>
    <Head title="Portal aktivieren" />
    <main class="flex min-h-screen items-center justify-center bg-sky-50 px-6 py-12">
        <form class="w-full max-w-lg rounded-3xl border bg-white p-8 shadow-xl" @submit.prevent="submit">
            <p class="text-sm font-semibold uppercase tracking-widest text-zbb">Matrix Teilnehmerportal</p>
            <h1 class="mt-2 text-2xl font-bold text-gray-900">Willkommen, {{ participantName }}</h1>
            <p class="mt-3 text-sm text-gray-600">Aktivieren Sie den Zugang für <strong>{{ email }}</strong>. Der Einladungslink ist nur einmal nutzbar.</p>
            <div class="mt-6 space-y-4">
                <label class="block text-sm font-medium text-gray-700">Passwort
                    <input v-model="form.password" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border-gray-300" />
                </label>
                <label class="block text-sm font-medium text-gray-700">Passwort wiederholen
                    <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border-gray-300" />
                </label>
                <p v-if="form.errors.password" class="text-sm text-red-600">{{ form.errors.password }}</p>
                <button class="w-full rounded-lg bg-zbb px-5 py-3 font-semibold text-white disabled:opacity-50" :disabled="form.processing">Portal aktivieren</button>
            </div>
        </form>
    </main>
</template>
