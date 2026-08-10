<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Teilnehmerportal" />
    <main class="relative min-h-screen overflow-hidden bg-gradient-to-b from-sky-50 via-white to-emerald-50 px-6 py-10 sm:px-8">
        <div class="mx-auto grid w-full max-w-6xl items-center gap-10 lg:grid-cols-[1.1fr_0.9fr]">
            <section class="relative rounded-[2rem] border border-sky-100 bg-white/90 p-8 shadow-[0_20px_70px_-30px_rgba(14,165,233,0.45)] backdrop-blur sm:p-10">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-zbb">Teilnehmerportal</p>
                <h1 class="mt-4 text-4xl font-black leading-tight text-slate-900 sm:text-5xl">
                    Willkommen im digitalen Bildungsportal
                </h1>
                <p class="mt-5 max-w-xl text-lg leading-8 text-slate-600">
                    Deine Aufgaben, Termine, Kurse und Nachrichten findest du hier an einem Ort. Das Portal ist speziell aufgebaut, damit du schnell verstehst, was als Nächstes ansteht.
                </p>
                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    <article class="rounded-2xl border border-emerald-100 bg-emerald-50/80 p-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Schnellstart</p>
                        <h2 class="mt-2 text-lg font-bold text-slate-900">1) Mit Einladung starten</h2>
                        <p class="mt-1 text-sm text-slate-600">Einladungscode oder Freischaltung durch deine Einrichtung nutzt du für den ersten Login.</p>
                    </article>
                    <article class="rounded-2xl border border-sky-100 bg-sky-50/80 p-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-sky-700">Was du hier hast</p>
                        <h2 class="mt-2 text-lg font-bold text-slate-900">Dein persönlicher Bereich</h2>
                        <p class="mt-1 text-sm text-slate-600">Profil, Kommunikation mit den Betreuenden, Bewerbungen und Lerninhalte in einer Übersicht.</p>
                    </article>
                </div>
                <div class="mt-8 grid gap-3 sm:grid-cols-2">
                    <Link :href="route('participant-portal.login')" class="inline-flex items-center justify-center rounded-xl bg-zbb px-5 py-3 text-center font-semibold text-white shadow-sm transition hover:opacity-95">
                        Zum Portal-Login
                    </Link>
                    <a href="/" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-center font-semibold text-slate-700 transition hover:border-slate-400">
                        Zurück zur Startseite
                    </a>
                </div>
            </section>
            <figure class="relative">
                <img src="/img/participant-portal/welcome-illustration.svg" alt="Illustration mit Startbildschirm des Teilnehmerportals" class="h-auto w-full rounded-[2rem] border border-sky-100 bg-white p-4 shadow-2xl" />
            </figure>
        </div>
    </main>
</template>
