<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
    section: { type: String, default: 'internal' },
    conversations: { type: Array, default: () => [] },
    selectedConversationId: Number,
    messages: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
    canUseParticipantMessages: { type: Boolean, default: false },
    participantConversations: { type: Array, default: () => [] },
    selectedParticipantConversationId: Number,
    participantMessages: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    prefillMaterialRequestId: Number,
    privacy: { type: Object, default: () => ({}) },
})

const conversationPane = ref(null)
const createOpen = ref(false)
const search = ref(props.filters.search || '')
const selectedConversation = computed(() => props.conversations.find((item) => item.id === props.selectedConversationId))
const selectedParticipantConversation = computed(() => props.participantConversations.find((item) => item.id === props.selectedParticipantConversationId))
const participantItems = ref([...(props.participantMessages || [])])
const participantBody = ref('')
const participantSending = ref(false)
let pollTimer = null

watch(() => props.participantMessages, (messages) => { participantItems.value = [...(messages || [])] })

const conversationForm = useForm({
    type: 'direct',
    name: '',
    member_ids: [],
    project_id: '',
    materialanforderung_id: props.prefillMaterialRequestId || null,
})

const messageForm = useForm({
    body: '',
    attachments: [],
    materialanforderung_id: props.prefillMaterialRequestId || null,
})

function searchConversations() {
    router.get(route('chat.index'), {
        section: props.section,
        search: search.value || undefined,
        materialanforderung: messageForm.materialanforderung_id || undefined,
    }, { preserveState: true, replace: true })
}

function openConversation(id) {
    router.get(route('chat.index'), {
        section: 'internal',
        conversation: id,
        search: search.value || undefined,
        materialanforderung: messageForm.materialanforderung_id || undefined,
    }, {
        preserveState: false,
        onSuccess: scrollDown,
    })
}

function openSection(section) {
    router.get(route('chat.index'), {
        section,
        search: search.value || undefined,
    }, { preserveState: false })
}

function openParticipant(id) {
    router.get(route('chat.index'), {
        section: 'participants',
        participant: id,
        search: search.value || undefined,
    }, {
        preserveState: false,
        onSuccess: scrollDown,
    })
}

async function sendParticipantMessage() {
    if (!props.selectedParticipantConversationId || !participantBody.value.trim() || participantSending.value) return
    participantSending.value = true
    try {
        const response = await axios.post(route('teilnehmer.messages.store', props.selectedParticipantConversationId), {
            body: participantBody.value,
        })
        participantItems.value.push(response.data.item)
        participantBody.value = ''
        await scrollDown()
        router.reload({
            only: ['participantConversations', 'staffChatUnreadCount', 'flash'],
            preserveState: true,
            preserveScroll: true,
        })
    } catch (error) {
        Swal.fire('Nicht gesendet', error.response?.data?.message || 'Die Nachricht konnte nicht gesendet werden.', 'error')
    } finally {
        participantSending.value = false
    }
}

function createConversation() {
    conversationForm.post(route('chat.conversations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false
            conversationForm.reset()
            conversationForm.type = 'direct'
            conversationForm.materialanforderung_id = messageForm.materialanforderung_id
        },
    })
}

function chooseFiles(event) {
    messageForm.attachments = Array.from(event.target.files || []).slice(0, 5)
}

function sendMessage() {
    if (!props.selectedConversationId || (!messageForm.body.trim() && !messageForm.attachments.length)) return
    messageForm.post(route('chat.messages.store', props.selectedConversationId), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            messageForm.reset('body', 'attachments')
            messageForm.materialanforderung_id = null
            scrollDown()
        },
    })
}

async function deleteMessage(message) {
    const result = await Swal.fire({
        title: 'Eigene Nachricht löschen?',
        text: 'Nachricht und Anhänge werden physisch gelöscht.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Löschen',
        cancelButtonText: 'Abbrechen',
        confirmButtonColor: '#dc2626',
    })
    if (!result.isConfirmed) return
    router.delete(route('chat.messages.destroy', message.id), { preserveScroll: true })
}

function scrollDown() {
    nextTick(() => {
        if (conversationPane.value) conversationPane.value.scrollTop = conversationPane.value.scrollHeight
    })
}

function dateTime(value) {
    return value ? new Date(value).toLocaleString('de-DE') : '–'
}

function fileSize(value) {
    const bytes = Number(value || 0)
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / 1048576).toFixed(1)} MB`
}

onMounted(() => {
    scrollDown()
    pollTimer = window.setInterval(() => {
        if (document.hidden || messageForm.processing || conversationForm.processing) return
        router.reload({
            only: ['conversations', 'selectedConversationId', 'messages', 'participantConversations', 'selectedParticipantConversationId', 'participantMessages', 'staffChatUnreadCount', 'flash'],
            preserveState: true,
            preserveScroll: true,
        })
    }, 15000)
})

onBeforeUnmount(() => {
    if (pollTimer) window.clearInterval(pollTimer)
})
</script>

<template>
    <Head title="Nachrichten" />
    <AppLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="mr-2">Nachrichten</span>
                    <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="section === 'internal' ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700'" @click="openSection('internal')">Team-Chat</button>
                    <button v-if="canUseParticipantMessages" type="button" class="relative rounded-lg px-3 py-2 text-sm font-semibold" :class="section === 'participants' ? 'bg-cyan-700 text-white' : 'border border-slate-300 bg-white text-slate-700'" @click="openSection('participants')">Teilnehmer<span v-if="$page.props.staffParticipantChatUnreadCount" class="ml-2 rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $page.props.staffParticipantChatUnreadCount }}</span></button>
                </div>
                <div v-if="section === 'internal'" class="flex gap-2">
                    <a :href="route('chat.export')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700">
                        <i class="las la-download mr-1"></i> Meine Daten
                    </a>
                    <button type="button" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white" @click="createOpen = true">
                        <i class="las la-plus mr-1"></i> Neue Unterhaltung
                    </button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 pb-8">
            <div v-if="section === 'internal'" class="flex items-start gap-3 rounded-xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-950">
                <i class="las la-user-shield mt-0.5 text-xl"></i>
                <div>
                    <p class="font-semibold">Dienstliche, geschützte Kommunikation</p>
                    <p class="mt-1">{{ privacy.notice }} Aktuelle Frist: {{ privacy.retention_days }} Tage. Sichtbar sind Inhalte ausschließlich für die Mitglieder der jeweiligen Unterhaltung.</p>
                </div>
            </div>

            <div v-if="section === 'internal'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="grid min-h-[680px] lg:grid-cols-[330px_1fr]">
                    <aside class="border-b border-slate-200 bg-slate-50 lg:border-b-0 lg:border-r">
                        <form class="border-b border-slate-200 p-4" @submit.prevent="searchConversations">
                            <div class="flex gap-2">
                                <input v-model="search" type="search" maxlength="100" placeholder="Unterhaltungen durchsuchen …" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm" />
                                <button class="rounded-lg bg-slate-800 px-3 text-white" aria-label="Suchen"><i class="las la-search"></i></button>
                            </div>
                        </form>

                        <div class="max-h-[620px] overflow-y-auto p-2">
                            <button
                                v-for="conversation in conversations"
                                :key="conversation.id"
                                type="button"
                                class="mb-1 w-full rounded-xl p-3 text-left transition"
                                :class="conversation.id === selectedConversationId ? 'bg-slate-900 text-white shadow-md' : 'hover:bg-white'"
                                @click="openConversation(conversation.id)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold">{{ conversation.title }}</p>
                                        <p class="mt-1 truncate text-xs" :class="conversation.id === selectedConversationId ? 'text-slate-300' : 'text-slate-500'">
                                            {{ conversation.last_message || (conversation.type === 'project' ? 'Projektunterhaltung' : 'Noch keine Nachricht') }}
                                        </p>
                                    </div>
                                    <span v-if="conversation.unread_count" class="shrink-0 rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white">{{ conversation.unread_count }}</span>
                                </div>
                            </button>
                            <div v-if="!conversations.length" class="px-4 py-12 text-center text-sm text-slate-500">
                                Noch keine Unterhaltungen vorhanden.
                            </div>
                        </div>
                    </aside>

                    <section v-if="selectedConversation" class="flex min-w-0 flex-col">
                        <header class="border-b border-slate-200 px-5 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h1 class="font-bold text-slate-900">{{ selectedConversation.title }}</h1>
                                    <p class="mt-1 text-xs text-slate-500">{{ selectedConversation.members.map(item => item.name).join(', ') }}</p>
                                </div>
                                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Nur für Mitglieder</span>
                            </div>
                        </header>

                        <div ref="conversationPane" class="flex-1 space-y-4 overflow-y-auto bg-gradient-to-b from-slate-50 to-white p-5">
                            <article v-for="message in messages" :key="message.id" class="group flex" :class="message.sender?.id === $page.props.auth.user.id ? 'justify-end' : 'justify-start'">
                                <div class="max-w-[88%] sm:max-w-[72%]">
                                    <div
                                        class="rounded-2xl px-4 py-3 shadow-sm"
                                        :class="message.sender?.id === $page.props.auth.user.id ? 'rounded-br-md bg-slate-900 text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-800'"
                                    >
                                        <div class="flex items-center justify-between gap-4">
                                            <p class="text-[11px] font-bold opacity-60">{{ message.sender?.name || 'Ehemalige Person' }}</p>
                                            <button v-if="message.sender?.id === $page.props.auth.user.id" type="button" class="text-xs opacity-0 transition hover:text-red-300 group-hover:opacity-70" title="Eigene Nachricht löschen" @click="deleteMessage(message)"><i class="las la-trash"></i></button>
                                        </div>
                                        <p v-if="message.body" class="mt-1 whitespace-pre-wrap break-words text-sm leading-6">{{ message.body }}</p>

                                        <a v-if="message.materialanforderung" :href="message.materialanforderung.link" class="mt-3 flex items-center gap-2 rounded-lg border border-cyan-300/40 bg-cyan-50/10 p-3 text-sm font-semibold hover:bg-cyan-100/20">
                                            <i class="las la-shopping-bag text-lg"></i>
                                            Materialanforderung #{{ message.materialanforderung.id }}
                                        </a>

                                        <div v-if="message.attachments.length" class="mt-3 space-y-2">
                                            <a v-for="file in message.attachments" :key="file.id" :href="file.download_url" class="flex items-center justify-between gap-3 rounded-lg border border-current/20 px-3 py-2 text-xs hover:bg-black/5">
                                                <span class="min-w-0 truncate"><i class="las la-paperclip mr-1"></i>{{ file.name }}</span>
                                                <span class="shrink-0 opacity-60">{{ fileSize(file.size) }}</span>
                                            </a>
                                        </div>
                                        <p class="mt-2 text-right text-[10px] opacity-50">{{ dateTime(message.created_at) }}</p>
                                    </div>
                                </div>
                            </article>
                            <div v-if="!messages.length" class="grid min-h-80 place-items-center text-center text-slate-500">
                                <div><i class="las la-comments text-5xl text-slate-300"></i><p class="mt-3 font-semibold">Noch keine Nachrichten</p></div>
                            </div>
                        </div>

                        <form class="border-t border-slate-200 bg-white p-4" @submit.prevent="sendMessage">
                            <div v-if="messageForm.materialanforderung_id" class="mb-3 flex items-center justify-between rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-900">
                                <span><i class="las la-link mr-1"></i>Materialanforderung #{{ messageForm.materialanforderung_id }} wird verknüpft.</span>
                                <button type="button" aria-label="Verknüpfung entfernen" @click="messageForm.materialanforderung_id = null">&times;</button>
                            </div>
                            <div v-if="messageForm.attachments.length" class="mb-2 text-xs text-slate-600">{{ messageForm.attachments.map(file => file.name).join(', ') }}</div>
                            <div class="flex items-end gap-2">
                                <label class="grid h-11 w-11 shrink-0 cursor-pointer place-items-center rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50" title="Anhänge auswählen">
                                    <i class="las la-paperclip text-xl"></i>
                                    <input type="file" multiple class="hidden" @change="chooseFiles" />
                                </label>
                                <textarea v-model="messageForm.body" maxlength="10000" rows="2" placeholder="Dienstliche Nachricht …" class="min-h-11 flex-1 resize-none rounded-xl border-slate-300 text-sm focus:border-cyan-500 focus:ring-cyan-500"></textarea>
                                <button class="h-11 rounded-xl bg-cyan-700 px-5 text-sm font-bold text-white disabled:opacity-50" :disabled="messageForm.processing || (!messageForm.body.trim() && !messageForm.attachments.length)">
                                    {{ messageForm.processing ? 'Sendet …' : 'Senden' }}
                                </button>
                            </div>
                            <p v-if="messageForm.hasErrors" class="mt-2 text-xs text-red-600">{{ Object.values(messageForm.errors)[0] }}</p>
                        </form>
                    </section>

                    <section v-else class="grid min-h-[680px] place-items-center p-8 text-center text-slate-500">
                        <div><i class="las la-comment-dots text-6xl text-slate-300"></i><p class="mt-4 font-semibold">Unterhaltung auswählen oder neu erstellen</p></div>
                    </section>
                </div>
            </div>

            <div v-if="section === 'participants'" class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
                <i class="las la-user-lock mt-0.5 text-xl"></i>
                <div>
                    <p class="font-semibold">Nachrichten aus dem Teilnehmerportal</p>
                    <p class="mt-1">Angezeigt werden ausschließlich aktive Teilnehmer des aktuell gewählten Projekts, für die Sie eine Zugriffsberechtigung haben.</p>
                </div>
            </div>

            <div v-if="section === 'participants'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="grid min-h-[680px] lg:grid-cols-[330px_1fr]">
                    <aside class="border-b border-slate-200 bg-slate-50 lg:border-b-0 lg:border-r">
                        <form class="border-b border-slate-200 p-4" @submit.prevent="searchConversations">
                            <div class="flex gap-2">
                                <input v-model="search" type="search" maxlength="100" placeholder="Teilnehmer oder Nachricht suchen …" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm" />
                                <button class="rounded-lg bg-cyan-700 px-3 text-white" aria-label="Suchen"><i class="las la-search"></i></button>
                            </div>
                        </form>

                        <div class="max-h-[620px] overflow-y-auto p-2">
                            <button
                                v-for="conversation in participantConversations"
                                :key="conversation.id"
                                type="button"
                                class="mb-1 w-full rounded-xl p-3 text-left transition"
                                :class="conversation.id === selectedParticipantConversationId ? 'bg-cyan-800 text-white shadow-md' : 'hover:bg-white'"
                                @click="openParticipant(conversation.id)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold">{{ conversation.title }}</p>
                                        <p class="mt-0.5 truncate text-[11px]" :class="conversation.id === selectedParticipantConversationId ? 'text-cyan-100' : 'text-emerald-700'">{{ conversation.project?.name }}</p>
                                        <p class="mt-1 truncate text-xs" :class="conversation.id === selectedParticipantConversationId ? 'text-cyan-100' : 'text-slate-500'">{{ conversation.last_message }}</p>
                                    </div>
                                    <span v-if="conversation.unread_count" class="shrink-0 rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white">{{ conversation.unread_count }}</span>
                                </div>
                            </button>
                            <div v-if="!participantConversations.length" class="px-4 py-12 text-center text-sm text-slate-500">Keine Teilnehmer-Unterhaltungen im aktiven Projekt.</div>
                        </div>
                    </aside>

                    <section v-if="selectedParticipantConversation" class="flex min-w-0 flex-col">
                        <header class="border-b border-slate-200 px-5 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h1 class="font-bold text-slate-900">{{ selectedParticipantConversation.title }}</h1>
                                    <p class="mt-1 text-xs text-slate-500">{{ selectedParticipantConversation.project?.name }} · Teilnehmerportal</p>
                                </div>
                                <Link :href="route('teilnehmer.edit', selectedParticipantConversation.participant_id)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">Teilnehmerprofil öffnen</Link>
                            </div>
                        </header>

                        <div ref="conversationPane" class="flex-1 space-y-4 overflow-y-auto bg-gradient-to-b from-cyan-50/40 to-white p-5">
                            <article v-for="message in participantItems" :key="message.id" class="flex" :class="message.sender_kind === 'staff' ? 'justify-end' : 'justify-start'">
                                <div class="max-w-[88%] sm:max-w-[72%]">
                                    <div class="rounded-2xl px-4 py-3 shadow-sm" :class="message.sender_kind === 'staff' ? 'rounded-br-md bg-cyan-800 text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-800'">
                                        <p class="text-[11px] font-bold opacity-60">{{ message.sender_kind === 'staff' ? (message.sender?.name || 'Projektteam') : selectedParticipantConversation.title }}</p>
                                        <p class="mt-1 whitespace-pre-wrap break-words text-sm leading-6">{{ message.body }}</p>
                                        <p class="mt-2 text-right text-[10px] opacity-50">{{ dateTime(message.created_at) }}</p>
                                    </div>
                                </div>
                            </article>
                            <div v-if="!participantItems.length" class="grid min-h-80 place-items-center text-center text-slate-500"><div><i class="las la-comments text-5xl text-slate-300"></i><p class="mt-3 font-semibold">Noch keine Nachrichten</p></div></div>
                        </div>

                        <form class="border-t border-slate-200 bg-white p-4" @submit.prevent="sendParticipantMessage">
                            <div class="flex items-end gap-2">
                                <textarea v-model="participantBody" maxlength="5000" rows="2" placeholder="Nachricht an den Teilnehmer …" class="min-h-11 flex-1 resize-none rounded-xl border-slate-300 text-sm focus:border-cyan-500 focus:ring-cyan-500"></textarea>
                                <button class="h-11 rounded-xl bg-cyan-700 px-5 text-sm font-bold text-white disabled:opacity-50" :disabled="participantSending || !participantBody.trim()">{{ participantSending ? 'Sendet …' : 'Senden' }}</button>
                            </div>
                        </form>
                    </section>

                    <section v-else class="grid min-h-[680px] place-items-center p-8 text-center text-slate-500">
                        <div><i class="las la-user-friends text-6xl text-slate-300"></i><p class="mt-4 font-semibold">Teilnehmer-Unterhaltung auswählen</p></div>
                    </section>
                </div>
            </div>
        </div>

        <div v-if="createOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" @click.self="createOpen = false">
            <form class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl" @submit.prevent="createConversation">
                <header class="flex items-center justify-between border-b border-slate-200 p-5">
                    <div><h2 class="text-lg font-bold">Neue Unterhaltung</h2><p class="text-sm text-slate-500">Mitglieder erhalten ausschließlich Zugriff auf diese Unterhaltung.</p></div>
                    <button type="button" class="text-2xl text-slate-400" @click="createOpen = false">&times;</button>
                </header>
                <div class="space-y-4 p-5">
                    <label class="block text-sm font-semibold">Art
                        <select v-model="conversationForm.type" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="direct">Direktnachricht</option>
                            <option value="group">Gruppe</option>
                            <option value="project">Projektchat</option>
                        </select>
                    </label>

                    <label v-if="conversationForm.type === 'group'" class="block text-sm font-semibold">Gruppenname
                        <input v-model="conversationForm.name" maxlength="160" class="mt-1 w-full rounded-lg border-slate-300" placeholder="z. B. Bestellwesen" />
                    </label>

                    <label v-if="conversationForm.type === 'project'" class="block text-sm font-semibold">Projekt
                        <select v-model="conversationForm.project_id" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="">Bitte auswählen</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                        </select>
                    </label>

                    <label v-else class="block text-sm font-semibold">{{ conversationForm.type === 'direct' ? 'Mitarbeitende Person' : 'Mitglieder' }}
                        <select v-if="conversationForm.type === 'direct'" v-model="conversationForm.member_ids[0]" class="mt-1 w-full rounded-lg border-slate-300">
                            <option :value="undefined">Bitte auswählen</option>
                            <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }}</option>
                        </select>
                        <select v-else v-model="conversationForm.member_ids" multiple class="mt-1 w-full rounded-lg border-slate-300" size="7">
                            <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }}</option>
                        </select>
                        <span v-if="conversationForm.type === 'group'" class="mt-1 block text-xs font-normal text-slate-500">Mehrfachauswahl mit Strg/Cmd.</span>
                    </label>

                    <p v-if="conversationForm.hasErrors" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ Object.values(conversationForm.errors)[0] }}</p>
                </div>
                <footer class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 p-4">
                    <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 font-semibold" @click="createOpen = false">Abbrechen</button>
                    <button class="rounded-lg bg-cyan-700 px-4 py-2 font-semibold text-white" :disabled="conversationForm.processing">Erstellen</button>
                </footer>
            </form>
        </div>
    </AppLayout>
</template>
