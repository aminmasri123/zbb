<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
    notifications: Object,
    filters: Object,
    types: Array,
    stats: Object,
})

const selectedStatus = ref(props.filters?.status || 'unread')
const selectedType = ref(props.filters?.type || '')

watch(() => props.filters, (filters) => {
    selectedStatus.value = filters?.status || 'unread'
    selectedType.value = filters?.type || ''
})

const statusOptions = [
    { value: 'unread', label: 'Ungelesen', icon: 'las la-envelope' },
    { value: 'all', label: 'Alle', icon: 'las la-list' },
    { value: 'read', label: 'Gelesen', icon: 'las la-envelope-open' },
]

const visit = () => {
    router.get(route('notifications.index'), {
        status: selectedStatus.value,
        type: selectedType.value || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

const setStatus = (status) => {
    selectedStatus.value = status
    visit()
}

const markAllAsRead = () => {
    router.post(route('notifications.readAll'), {}, {
        preserveScroll: true,
    })
}

const markAsRead = (notification) => {
    router.post(route('notifications.read', notification.id), {}, {
        preserveScroll: true,
    })
}

const markAsUnread = (notification) => {
    router.post(route('notifications.unread', notification.id), {}, {
        preserveScroll: true,
    })
}

const destroyNotification = (notification) => {
    if (!window.confirm('Diese Benachrichtigung entfernen?')) {
        return
    }

    router.delete(route('notifications.destroy', notification.id), {
        preserveScroll: true,
    })
}

const resetFilters = () => {
    selectedStatus.value = 'unread'
    selectedType.value = ''
    visit()
}
</script>

<template>
    <Head title="Benachrichtigungen" />

    <AppLayout>
        <template #header>Benachrichtigungen</template>

        <div class="space-y-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="border border-gray-200 bg-white px-4 py-3">
                    <div class="text-xs uppercase text-gray-500">Ungelesen</div>
                    <div class="mt-1 text-2xl font-semibold text-red-600">{{ stats.unread }}</div>
                </div>
                <div class="border border-gray-200 bg-white px-4 py-3">
                    <div class="text-xs uppercase text-gray-500">Gelesen</div>
                    <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ stats.read }}</div>
                </div>
                <div class="border border-gray-200 bg-white px-4 py-3">
                    <div class="text-xs uppercase text-gray-500">Gesamt</div>
                    <div class="mt-1 text-2xl font-semibold text-zbb">{{ stats.total }}</div>
                </div>
            </div>

            <div class="flex flex-col gap-3 border border-gray-200 bg-white p-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in statusOptions"
                        :key="option.value"
                        type="button"
                        @click="setStatus(option.value)"
                        :class="[
                            selectedStatus === option.value
                                ? 'border-zbb bg-zbb text-white'
                                : 'border-gray-300 bg-white text-gray-700 hover:border-orange-500 hover:text-zbb',
                            'inline-flex items-center gap-2 border px-3 py-2 text-sm font-medium'
                        ]"
                    >
                        <i :class="option.icon"></i>
                        <span>{{ option.label }}</span>
                    </button>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <select
                        v-model="selectedType"
                        @change="visit"
                        class="border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-zbb focus:ring-zbb"
                    >
                        <option value="">Alle Typen</option>
                        <option v-for="type in types" :key="type" :value="type">{{ type }}</option>
                    </select>

                    <button
                        type="button"
                        @click="resetFilters"
                        class="inline-flex items-center justify-center gap-2 border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:border-orange-500 hover:text-zbb"
                        title="Filter zurücksetzen"
                    >
                        <i class="las la-undo"></i>
                        <span>Reset</span>
                    </button>

                    <button
                        type="button"
                        @click="markAllAsRead"
                        :disabled="stats.unread === 0"
                        class="inline-flex items-center justify-center gap-2 border border-emerald-700 bg-emerald-700 px-3 py-2 text-sm text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:border-gray-300 disabled:bg-gray-200 disabled:text-gray-500"
                        title="Alle als gelesen markieren"
                    >
                        <i class="las la-check-double"></i>
                        <span>Alle gelesen</span>
                    </button>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    :class="[
                        notification.is_read ? 'border-l-emerald-600' : 'border-l-red-500',
                        'border border-l-4 border-gray-200 bg-white p-4 shadow-sm'
                    ]"
                >
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="border border-gray-300 px-2 py-1 text-xs font-semibold uppercase text-gray-600">
                                    {{ notification.typ }}
                                </span>
                                <span
                                    v-if="notification.status"
                                    class="border border-orange-200 bg-orange-50 px-2 py-1 text-xs font-medium text-orange-800"
                                >
                                    {{ notification.status }}
                                </span>
                                <span class="text-xs text-gray-500">{{ notification.created_at_formatted }}</span>
                            </div>

                            <a
                                v-if="notification.link"
                                :href="notification.link"
                                class="block break-words text-sm font-medium text-gray-900 hover:text-zbb"
                            >
                                {{ notification.message }}
                            </a>
                            <p v-else class="break-words text-sm font-medium text-gray-900">
                                {{ notification.message }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            <a
                                v-if="notification.link"
                                :href="notification.link"
                                class="inline-flex h-9 w-9 items-center justify-center border border-gray-300 bg-white text-gray-700 hover:border-zbb hover:text-zbb"
                                title="Öffnen"
                            >
                                <i class="las la-external-link-alt"></i>
                            </a>

                            <button
                                v-if="!notification.is_read"
                                type="button"
                                @click="markAsRead(notification)"
                                class="inline-flex h-9 w-9 items-center justify-center border border-emerald-700 bg-white text-emerald-700 hover:bg-emerald-700 hover:text-white"
                                title="Als gelesen markieren"
                            >
                                <i class="las la-envelope-open"></i>
                            </button>

                            <button
                                v-else
                                type="button"
                                @click="markAsUnread(notification)"
                                class="inline-flex h-9 w-9 items-center justify-center border border-gray-400 bg-white text-gray-700 hover:bg-gray-700 hover:text-white"
                                title="Als ungelesen markieren"
                            >
                                <i class="las la-envelope"></i>
                            </button>

                            <button
                                type="button"
                                @click="destroyNotification(notification)"
                                class="inline-flex h-9 w-9 items-center justify-center border border-red-500 bg-white text-red-600 hover:bg-red-600 hover:text-white"
                                title="Entfernen"
                            >
                                <i class="las la-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="notifications.data.length === 0"
                    class="border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500"
                >
                    Keine Benachrichtigungen gefunden.
                </div>
            </div>

            <Pagination :pagination="notifications" />
        </div>
    </AppLayout>
</template>
