<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '' });
const submit = () => form.post(route('participant-portal.login.store'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Portal-Anmeldung" />
    <main class="flex min-h-screen items-center justify-center bg-sky-50 px-6 py-12">
        <form class="w-full max-w-md rounded-3xl border bg-white p-8 shadow-xl" @submit.prevent="submit">
            <p class="text-sm font-semibold uppercase tracking-widest text-zbb">Matrix Teilnehmerportal</p>
            <h1 class="mt-2 text-2xl font-bold text-gray-900">Anmelden</h1>
            <div class="mt-6 space-y-4">
                <label class="block text-sm font-medium text-gray-700">E-Mail
                    <input v-model="form.email" type="email" required autocomplete="email" class="mt-1 w-full rounded-lg border-gray-300" />
                    <span v-if="form.errors.email" class="mt-1 block text-xs text-red-600">{{ form.errors.email }}</span>
                </label>
                <label class="block text-sm font-medium text-gray-700">Passwort
                    <input v-model="form.password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border-gray-300" />
                </label>
                <button class="w-full rounded-lg bg-zbb px-5 py-3 font-semibold text-white disabled:opacity-50" :disabled="form.processing">Anmelden</button>
            </div>
            <Link :href="route('participant-portal.welcome')" class="mt-5 block text-center text-sm text-gray-500">Zurück</Link>
        </form>
    </main>
</template>
