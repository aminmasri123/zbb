<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Teilnehmerportal" />
    <main class="min-h-screen bg-sky-50 px-6 py-16">
        <div class="mx-auto max-w-3xl rounded-3xl border border-sky-100 bg-white p-8 shadow-xl sm:p-12">
            <p class="text-sm font-semibold uppercase tracking-widest text-zbb">Matrix</p>
            <h1 class="mt-3 text-3xl font-bold text-gray-900 sm:text-4xl">Teilnehmerportal</h1>
            <p class="mt-4 text-lg text-gray-600">
                Hier entstehen Ihr persönliches Profil, Aufgaben und Termine, Jobsuche, Bewerbungen sowie freigeschaltete Kurse.
            </p>
            <div class="mt-8 rounded-2xl bg-sky-50 p-5 text-sm text-gray-700">
                Der Zugang wird von Ihrer zuständigen Einrichtung persönlich freigeschaltet. Teilnehmerkonten und Einladungen werden in der nächsten Ausbaustufe aktiviert.
            </div>
            <div class="mt-8 flex flex-wrap gap-3">
                <Link :href="route('participant-portal.login')" class="inline-flex rounded-lg bg-zbb px-5 py-3 font-semibold text-white">Zum Portal-Login</Link>
                <Link href="/" class="inline-flex rounded-lg border border-gray-300 px-5 py-3 font-semibold text-gray-700">Zur Matrix-Startseite</Link>
            </div>
        </div>
    </main>
</template>
