<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '' });
const submit = () => form.post(route('participant-portal.login.store'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Portal-Anmeldung" />
    <main class="min-h-screen bg-gradient-to-b from-slate-50 via-sky-50 to-white px-6 py-10 sm:px-8">
        <div class="mx-auto grid w-full max-w-6xl gap-8 lg:grid-cols-[1fr_1fr]">
            <section class="order-2 rounded-[2rem] border border-slate-200 bg-white p-8 shadow-lg sm:p-10 lg:order-1">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-zbb">Teilnehmerportal-Login</p>
                <h1 class="mt-4 text-3xl font-black text-slate-900 sm:text-4xl">Sicher anmelden</h1>
                <p class="mt-3 text-slate-600">
                    Mit deiner E-Mail und deinem Passwort auf den Bereich für Kurse, Bewerbungen und Termine zugreifen.
                </p>

                <form class="mt-8 space-y-4" @submit.prevent="submit">
                    <label class="block text-sm font-semibold text-slate-700">
                        E-Mail-Adresse
                        <input v-model="form.email" type="email" required autocomplete="email" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-3 focus:border-sky-400 focus:ring-sky-300" />
                        <span v-if="form.errors.email" class="mt-1 block text-xs text-red-600">{{ form.errors.email }}</span>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">
                        Passwort
                        <input v-model="form.password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-3 focus:border-sky-400 focus:ring-sky-300" />
                    </label>
                    <p v-if="form.errors.password" class="text-xs text-red-600">{{ form.errors.password }}</p>
                    <button class="w-full rounded-xl bg-zbb px-5 py-3 font-semibold text-white transition hover:opacity-95 disabled:opacity-50" :disabled="form.processing">Anmelden</button>
                </form>

                <div class="mt-8 grid gap-3 sm:grid-cols-2">
                    <Link :href="route('participant-portal.welcome')" class="rounded-xl border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700">
                        Zurück
                    </Link>
                    <a href="/" class="rounded-xl bg-sky-50 px-4 py-2 text-center text-sm font-semibold text-sky-700">
                        Matrix-Startseite
                    </a>
                </div>
                <p class="mt-6 rounded-xl bg-sky-50 p-3 text-xs text-sky-700">
                    Hinweis: Wenn du noch kein Passwort hast, musst du über eine Einladung freigeschaltet werden.
                </p>
            </section>

            <aside class="order-1 rounded-[2rem] border border-emerald-200 bg-emerald-50 p-6 sm:p-8 lg:order-2">
                <img src="/img/participant-portal/login-visual.svg" alt="Illustration zur sicheren Anmeldung" class="w-full rounded-3xl border border-emerald-100 bg-white p-3 shadow-lg" />
                <div class="mt-6 rounded-2xl bg-white p-5">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-emerald-800">Du bist nicht allein</h2>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600">
                        <li class="flex items-start gap-2">• Prüfe zuerst, ob dein Zugang freigeschaltet ist.</li>
                        <li class="flex items-start gap-2">• Gib deine Daten nur weiter, wenn du dich sicher fühlst.</li>
                        <li class="flex items-start gap-2">• Bei Problemen: Wende dich direkt an deine Betreuungsperson.</li>
                    </ul>
                </div>
            </aside>
        </div>
    </main>
</template>
