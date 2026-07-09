<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    rules: Array,
    events: Object,
    targetTypes: Object,
    scopes: Object,
    channels: Object,
    roles: Array,
    permissions: Array,
})

const emptyRule = () => ({
    event_key: Object.keys(props.events)[0] || '',
    label: props.events[Object.keys(props.events)[0]] || '',
    target_type: 'permission',
    target_value: '',
    scope: 'none',
    channels: ['database'],
    active: true,
    exclude_actor: true,
    sort_order: 100,
})

const localRules = ref([])
const form = reactive(emptyRule())
const savingIds = ref([])

const cloneRule = (rule) => ({
    id: rule.id,
    event_key: rule.event_key,
    label: rule.label,
    target_type: rule.target_type,
    target_value: rule.target_value || '',
    scope: rule.scope || 'none',
    channels: rule.channels?.length ? rule.channels : ['database'],
    active: Boolean(rule.active),
    exclude_actor: Boolean(rule.exclude_actor),
    sort_order: Number(rule.sort_order) || 100,
})

const syncRules = () => {
    localRules.value = (props.rules || []).map(cloneRule)
}

watch(() => props.rules, syncRules, { immediate: true, deep: true })

const eventOptions = computed(() => Object.entries(props.events || {}).map(([value, label]) => ({ value, label })))
const targetTypeOptions = computed(() => Object.entries(props.targetTypes || {}).map(([value, label]) => ({ value, label })))
const scopeOptions = computed(() => Object.entries(props.scopes || {}).map(([value, label]) => ({ value, label })))

const targetValueOptions = (targetType) => {
    if (targetType === 'role') {
        return props.roles || []
    }

    if (targetType === 'permission') {
        return props.permissions || []
    }

    return []
}

const targetNeedsValue = (targetType) => ['permission', 'role'].includes(targetType)

const applyEventLabel = (rule) => {
    if (!rule.label || Object.values(props.events || {}).includes(rule.label)) {
        rule.label = props.events?.[rule.event_key] || rule.label
    }
}

const normalizeRule = (rule) => ({
    ...rule,
    target_value: targetNeedsValue(rule.target_type) ? rule.target_value : null,
    channels: rule.channels?.length ? rule.channels : ['database'],
    active: Boolean(rule.active),
    exclude_actor: Boolean(rule.exclude_actor),
    sort_order: Number(rule.sort_order) || 100,
})

const setSaving = (id, saving) => {
    if (saving) {
        savingIds.value = [...new Set([...savingIds.value, id])]
        return
    }

    savingIds.value = savingIds.value.filter((value) => value !== id)
}

const isSaving = (id) => savingIds.value.includes(id)

const storeRule = () => {
    router.post(route('notification-rules.store'), normalizeRule(form), {
        preserveScroll: true,
        onSuccess: () => Object.assign(form, emptyRule()),
    })
}

const updateRule = (rule) => {
    setSaving(rule.id, true)

    router.put(route('notification-rules.update', rule.id), normalizeRule(rule), {
        preserveScroll: true,
        onFinish: () => setSaving(rule.id, false),
    })
}

const destroyRule = (rule) => {
    if (!window.confirm('Diese Regel entfernen?')) {
        return
    }

    router.delete(route('notification-rules.destroy', rule.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Benachrichtigungsregeln" />

    <AppLayout>
        <template #header>Benachrichtigungsregeln</template>

        <div class="space-y-5">
            <div class="border border-gray-200 bg-white p-4">
                <div class="grid gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-3">
                        <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Ereignis</label>
                        <select
                            v-model="form.event_key"
                            @change="applyEventLabel(form)"
                            class="w-full border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option v-for="event in eventOptions" :key="event.value" :value="event.value">
                                {{ event.label }}
                            </option>
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Empfänger</label>
                        <select v-model="form.target_type" class="w-full border border-gray-300 px-3 py-2 text-sm">
                            <option v-for="type in targetTypeOptions" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Zielwert</label>
                        <select
                            v-if="targetNeedsValue(form.target_type)"
                            v-model="form.target_value"
                            class="w-full border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option value="">Bitte wählen</option>
                            <option v-for="option in targetValueOptions(form.target_type)" :key="option.id" :value="option.name">
                                {{ option.name }}
                            </option>
                        </select>
                        <div v-else class="border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                            Wird automatisch ermittelt
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Scope</label>
                        <select v-model="form.scope" class="w-full border border-gray-300 px-3 py-2 text-sm">
                            <option v-for="scope in scopeOptions" :key="scope.value" :value="scope.value">
                                {{ scope.label }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-end lg:col-span-2">
                        <button
                            type="button"
                            @click="storeRule"
                            class="inline-flex w-full items-center justify-center gap-2 border border-zbb bg-zbb px-3 py-2 text-sm font-medium text-white hover:bg-orange-700"
                        >
                            <i class="las la-plus"></i>
                            <span>Regel anlegen</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto border border-gray-200 bg-white">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                        <tr>
                            <th class="px-3 py-3">Aktiv</th>
                            <th class="px-3 py-3">Ereignis</th>
                            <th class="px-3 py-3">Empfänger</th>
                            <th class="px-3 py-3">Zielwert</th>
                            <th class="px-3 py-3">Scope</th>
                            <th class="px-3 py-3">Auslöser</th>
                            <th class="px-3 py-3">Sort</th>
                            <th class="px-3 py-3 text-right">Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="rule in localRules" :key="rule.id" class="border-t border-gray-200 align-top">
                            <td class="px-3 py-3">
                                <input v-model="rule.active" type="checkbox" class="border-gray-300 text-zbb focus:ring-zbb" />
                            </td>
                            <td class="px-3 py-3">
                                <select
                                    v-model="rule.event_key"
                                    @change="applyEventLabel(rule)"
                                    class="w-72 border border-gray-300 px-2 py-1 text-xs"
                                >
                                    <option v-for="event in eventOptions" :key="event.value" :value="event.value">
                                        {{ event.label }}
                                    </option>
                                </select>
                                <input v-model="rule.label" class="mt-2 w-72 border border-gray-300 px-2 py-1 text-xs" />
                            </td>
                            <td class="px-3 py-3">
                                <select v-model="rule.target_type" class="w-48 border border-gray-300 px-2 py-1 text-xs">
                                    <option v-for="type in targetTypeOptions" :key="type.value" :value="type.value">
                                        {{ type.label }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-3 py-3">
                                <select
                                    v-if="targetNeedsValue(rule.target_type)"
                                    v-model="rule.target_value"
                                    class="w-80 border border-gray-300 px-2 py-1 text-xs"
                                >
                                    <option value="">Bitte wählen</option>
                                    <option v-for="option in targetValueOptions(rule.target_type)" :key="option.id" :value="option.name">
                                        {{ option.name }}
                                    </option>
                                </select>
                                <span v-else class="text-xs text-gray-500">Automatisch</span>
                            </td>
                            <td class="px-3 py-3">
                                <select v-model="rule.scope" class="w-44 border border-gray-300 px-2 py-1 text-xs">
                                    <option v-for="scope in scopeOptions" :key="scope.value" :value="scope.value">
                                        {{ scope.label }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-3 py-3">
                                <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                                    <input v-model="rule.exclude_actor" type="checkbox" class="border-gray-300 text-zbb focus:ring-zbb" />
                                    <span>ausschließen</span>
                                </label>
                            </td>
                            <td class="px-3 py-3">
                                <input v-model="rule.sort_order" type="number" min="0" class="w-20 border border-gray-300 px-2 py-1 text-xs" />
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        @click="updateRule(rule)"
                                        :disabled="isSaving(rule.id)"
                                        class="inline-flex h-8 w-8 items-center justify-center border border-emerald-700 text-emerald-700 hover:bg-emerald-700 hover:text-white disabled:opacity-60"
                                        title="Speichern"
                                    >
                                        <i class="las la-save"></i>
                                    </button>
                                    <button
                                        type="button"
                                        @click="destroyRule(rule)"
                                        class="inline-flex h-8 w-8 items-center justify-center border border-red-500 text-red-600 hover:bg-red-600 hover:text-white"
                                        title="Entfernen"
                                    >
                                        <i class="las la-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="localRules.length === 0">
                            <td colspan="8" class="px-3 py-10 text-center text-gray-500">
                                Keine Regeln vorhanden.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
