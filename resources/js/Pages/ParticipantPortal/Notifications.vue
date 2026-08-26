<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ParticipantPortalLayout from '@/Layouts/ParticipantPortalLayout.vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    notifications: { type: Object, required: true },
    unreadCount: { type: Number, default: 0 },
});

const markAsRead = (notification) => router.post(
    route('participant-portal.notifications.read', notification.id),
    {},
    { preserveScroll: true },
);
const markAllAsRead = () => router.post(
    route('participant-portal.notifications.read-all'),
    {},
    { preserveScroll: true },
);
</script>

<template>
    <Head title="Hinweise" />
    <ParticipantPortalLayout
        title="Hinweise"
        subtitle="Aktuelle Aufgaben, Termine, Nachrichten und weitere Benachrichtigungen aus Ihren aktiven Projekten."
    >
        <template #actions>
            <Link :href="route('participant-portal.notification-preferences.index')" class="rounded-xl border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-sm font-semibold">
                Einstellungen
            </Link>
            <button v-if="unreadCount" type="button" class="rounded-xl bg-[var(--buttonPrimary)] px-4 py-2 text-sm font-semibold text-[var(--buttonTextPrimary)]" @click="markAllAsRead">
                Alle als gelesen markieren
            </button>
        </template>

        <div class="mb-5 rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-[var(--secondary)]">Ungelesene Hinweise</p>
            <p class="mt-1 text-3xl font-bold">{{ unreadCount }}</p>
        </div>

        <section class="space-y-3">
            <article
                v-for="notification in notifications.data"
                :key="notification.id"
                class="rounded-2xl border bg-[var(--card)] p-5 shadow-sm"
                :class="notification.is_read ? 'border-[var(--border)]' : 'border-cyan-400 ring-1 ring-cyan-200'"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-[var(--surfaceTint)] px-2.5 py-1 text-xs font-bold uppercase">{{ notification.typ }}</span>
                            <span class="text-xs text-[var(--secondary)]">{{ notification.created_at }}</span>
                            <span v-if="!notification.is_read" class="rounded-full bg-cyan-100 px-2 py-1 text-xs font-semibold text-cyan-800">Neu</span>
                        </div>
                        <h2 class="mt-3 font-bold">{{ notification.message }}</h2>
                        <p v-if="notification.detail" class="mt-1 text-sm leading-6 text-[var(--secondary)]">{{ notification.detail }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Link v-if="notification.link" :href="notification.link" class="rounded-lg border border-[var(--border)] px-3 py-2 text-sm font-semibold">Öffnen</Link>
                        <button v-if="!notification.is_read" type="button" class="rounded-lg border border-emerald-500 px-3 py-2 text-sm font-semibold text-emerald-700" @click="markAsRead(notification)">Gelesen</button>
                    </div>
                </div>
            </article>
            <div v-if="!notifications.data.length" class="rounded-2xl border border-dashed border-[var(--border)] bg-[var(--card)] p-10 text-center text-sm text-[var(--secondary)]">
                Aktuell liegen keine Hinweise vor.
            </div>
        </section>

        <Pagination class="mt-5" :pagination="notifications" />
    </ParticipantPortalLayout>
</template>
