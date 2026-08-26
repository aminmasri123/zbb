<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/utils/permissions';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    project: { type: Object, required: true },
    portalUsers: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();
const search = ref('');
const status = ref('all');

const statusDefinitions = {
    active_used: { label: 'Portal genutzt', classes: 'bg-green-100 text-green-700' },
    active_never: { label: 'Noch nicht erfasst', classes: 'bg-blue-100 text-blue-700' },
    invited: { label: 'Einladung offen', classes: 'bg-amber-100 text-amber-700' },
    expired: { label: 'Einladung abgelaufen', classes: 'bg-red-100 text-red-700' },
};

const filteredRows = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.portalUsers.filter((row) => {
        const matchesStatus = status.value === 'all'
            || (status.value === 'accounts' && ['active_used', 'active_never'].includes(row.status))
            || row.status === status.value;
        const matchesSearch = !term
            || row.participant_name?.toLowerCase().includes(term)
            || row.email?.toLowerCase().includes(term)
            || String(row.participant_id).includes(term);

        return matchesStatus && matchesSearch;
    });
});

const formatDateTime = (value) => value
    ? new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '–';

const lastLoginText = (row) => {
    if (row.status === 'active_never') return 'Seit Einführung noch nicht erfasst';
    if (!row.last_login_at) return '–';
    return formatDateTime(row.last_login_at);
};

const sendInvite = async (row) => {
    if (!can('teilnehmer.portal.invite') || !row?.participation_id) return;

    const emailDefault = row.email || '';
    const result = await Swal.fire({
        title: 'Einladung verschicken',
        input: 'email',
        inputLabel: 'E-Mail-Adresse',
        inputValue: emailDefault,
        inputPlaceholder: 'name@beispiel.de',
        showCancelButton: true,
        confirmButtonText: 'Einladung senden',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#f97316',
        allowOutsideClick: false,
        preConfirm: (value) => {
            if (!value) {
                Swal.showValidationMessage('Bitte eine gültige E-Mail-Adresse angeben.');
                return false;
            }
            return value;
        },
    });

    if (!result.isConfirmed || !result.value) return;

    try {
        const { data } = await axios.post(route('teilnehmer.portal.invite', row.participation_id), { email: result.value });
        await Swal.fire({
            title: 'Gesendet',
            text: data?.message || 'Portal-Einladung wurde erstellt.',
            icon: 'success',
        });
        window.location.reload();
    } catch (error) {
        await Swal.fire({
            title: 'Fehler',
            text: error.response?.data?.message || 'Einladung konnte nicht gesendet werden.',
            icon: 'error',
        });
    }
};
</script>

<template>
    <Head title="Portal-Nutzer" />

    <AppLayout>
        <template #header>
            <div>
                <div>Portal-Nutzer</div>
                <p class="text-xs font-normal text-gray-500">{{ project.name }}</p>
            </div>
        </template>

        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link :href="route('teilnehmer.index')" class="inline-flex items-center rounded border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:border-zbb hover:text-zbb">
                    <i class="las la-arrow-left mr-2"></i>
                    Zur Teilnehmerübersicht
                </Link>
                <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-xs text-blue-800">
                    Erfasst wird nur die letzte erfolgreiche Anmeldung – keine IP-Adresse und kein Anmeldeverlauf.
                </div>
            </div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Portal-Kennzahlen">
                <button type="button" class="rounded-xl border bg-white p-4 text-left shadow-sm" :class="status === 'accounts' ? 'border-zbb ring-1 ring-zbb' : 'border-gray-200'" @click="status = status === 'accounts' ? 'all' : 'accounts'">
                    <p class="text-xs font-semibold uppercase text-gray-500">Portal-Konten</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.accounts ?? 0 }}</p>
                </button>
                <button type="button" class="rounded-xl border bg-white p-4 text-left shadow-sm" :class="status === 'active_used' ? 'border-green-500 ring-1 ring-green-500' : 'border-gray-200'" @click="status = status === 'active_used' ? 'all' : 'active_used'">
                    <p class="text-xs font-semibold uppercase text-green-600">Bereits genutzt</p>
                    <p class="mt-1 text-2xl font-bold text-green-700">{{ stats.used ?? 0 }}</p>
                </button>
                <button type="button" class="rounded-xl border bg-white p-4 text-left shadow-sm" :class="status === 'active_never' ? 'border-blue-500 ring-1 ring-blue-500' : 'border-gray-200'" @click="status = status === 'active_never' ? 'all' : 'active_never'">
                    <p class="text-xs font-semibold uppercase text-blue-600">Noch nicht erfasst</p>
                    <p class="mt-1 text-2xl font-bold text-blue-700">{{ stats.never_used ?? 0 }}</p>
                </button>
                <button type="button" class="rounded-xl border bg-white p-4 text-left shadow-sm" :class="status === 'invited' ? 'border-amber-500 ring-1 ring-amber-500' : 'border-gray-200'" @click="status = status === 'invited' ? 'all' : 'invited'">
                    <p class="text-xs font-semibold uppercase text-amber-600">Offene Einladungen</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ stats.pending_invitations ?? 0 }}</p>
                </button>
                <button type="button" class="rounded-xl border bg-white p-4 text-left shadow-sm" :class="status === 'expired' ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200'" @click="status = status === 'expired' ? 'all' : 'expired'">
                    <p class="text-xs font-semibold uppercase text-red-600">Abgelaufen</p>
                    <p class="mt-1 text-2xl font-bold text-red-700">{{ stats.expired_invitations ?? 0 }}</p>
                </button>
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4">
                    <div>
                        <h2 class="font-semibold text-gray-800">Konten und Einladungen</h2>
                        <p class="mt-1 text-xs text-gray-500">Es werden nur Teilnehmer des aktiven Projekts angezeigt, für die du eine Zugriffsberechtigung hast.</p>
                    </div>
                    <input v-model="search" type="search" class="w-full rounded-lg border-gray-300 text-sm sm:w-80" placeholder="Name, E-Mail oder ID suchen …" />
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                            <tr>
                                <th class="px-4 py-3">Teilnehmer</th>
                                <th class="px-4 py-3">E-Mail</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Konto/Einladung seit</th>
                                <th class="px-4 py-3">Letzte Anmeldung</th>
                                <th v-if="can('teilnehmer.portal.invite')" class="px-4 py-3">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filteredRows" :key="row.id" class="border-t border-gray-100 hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <Link v-if="can('teilnehmer.update')" :href="route('teilnehmer.edit', row.participant_id)" class="font-semibold text-zbb hover:underline">
                                        {{ row.participant_name }}
                                    </Link>
                                    <span v-else class="font-semibold text-gray-800">{{ row.participant_name }}</span>
                                    <p class="text-xs text-gray-500">ID: {{ row.participant_id }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ row.email }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusDefinitions[row.status]?.classes">
                                        {{ statusDefinitions[row.status]?.label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ formatDateTime(row.account_created_at || row.invited_at) }}
                                    <p v-if="row.status === 'invited'" class="mt-1 text-xs text-amber-700">Gültig bis {{ formatDateTime(row.invitation_expires_at) }}</p>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-700">{{ lastLoginText(row) }}</td>
                                <td v-if="can('teilnehmer.portal.invite')" class="px-4 py-3">
                                    <button
                                        v-if="row.participation_id && row.status !== 'active_used' && row.status !== 'active_never'"
                                        type="button"
                                        class="rounded border border-zbb px-3 py-2 text-xs font-medium text-zbb hover:bg-zbb hover:text-white"
                                        @click="sendInvite(row)"
                                    >
                                        Einladung senden
                                    </button>
                                    <span v-else class="text-xs text-gray-400">Bereits Konto vorhanden</span>
                                </td>
                            </tr>
                            <tr v-if="filteredRows.length === 0">
                                <td :colspan="can('teilnehmer.portal.invite') ? 6 : 5" class="px-4 py-12 text-center text-gray-500">Keine passenden Portal-Nutzer oder Einladungen gefunden.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
