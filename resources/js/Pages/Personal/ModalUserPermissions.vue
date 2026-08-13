<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    visible: Boolean,
    person: Object,
    categories: { type: Array, default: () => [] },
})
const emit = defineEmits(['close'])

const search = ref('')
const form = useForm({ permission_ids: [] })

const account = computed(() => props.person?.user || null)
const inheritedIds = computed(() => new Set(
    (account.value?.roles || []).flatMap((role) => (role.permissions || []).map((permission) => permission.id))
))
const directIds = computed(() => new Set((account.value?.permissions || []).map((permission) => permission.id)))
const filteredCategories = computed(() => {
    const term = search.value.trim().toLowerCase()
    if (!term) return props.categories

    return props.categories
        .map((category) => ({
            ...category,
            permissions: (category.permissions || []).filter((permission) =>
                `${permission.name} ${permission.beschreibung || ''}`.toLowerCase().includes(term)
            ),
        }))
        .filter((category) => category.permissions.length > 0 || category.name.toLowerCase().includes(term))
})

watch(() => [props.visible, props.person], () => {
    if (!props.visible) return
    form.permission_ids = [...directIds.value]
    form.clearErrors()
    search.value = ''
}, { immediate: true, deep: true })

function save() {
    if (!account.value) return
    form.put(route('personal.permissions.update', account.value.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    })
}
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3 sm:p-6" @click.self="emit('close')">
        <form class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl" @submit.prevent="save">
            <header class="flex items-start justify-between border-b border-gray-200 p-4 sm:p-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Zusätzliche Berechtigungen</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ person?.vorname }} {{ person?.nachname }}</p>
                </div>
                <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100" @click="emit('close')"><i class="las la-times text-xl"></i></button>
            </header>

            <div v-if="!account" class="p-8 text-center text-gray-500">Dieser Mitarbeiter besitzt noch kein Benutzerkonto.</div>
            <template v-else>
                <div class="border-b border-gray-200 p-4 sm:px-6">
                    <div class="relative"><i class="las la-search absolute left-3 top-3 text-gray-400"></i><input v-model="search" type="search" class="w-full rounded-lg border-gray-300 pl-10" placeholder="Berechtigung oder Beschreibung suchen" /></div>
                    <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-600"><span><span class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-blue-500"></span>über Rolle vorhanden</span><span><span class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-orange-500"></span>direkt zusätzlich vergeben</span></div>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto bg-gray-50 p-4 sm:p-6">
                    <section v-for="category in filteredCategories" :key="category.id" class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                        <div class="border-b border-gray-100 px-4 py-3"><h3 class="font-semibold text-gray-900">{{ category.name }}</h3><p v-if="category.beschreibung" class="text-xs text-gray-500">{{ category.beschreibung }}</p></div>
                        <div class="divide-y divide-gray-100">
                            <label v-for="permission in category.permissions" :key="permission.id" class="flex items-start gap-3 p-4" :class="inheritedIds.has(permission.id) ? 'bg-blue-50/60' : 'hover:bg-gray-50'">
                                <input
                                    v-model="form.permission_ids"
                                    type="checkbox"
                                    :value="permission.id"
                                    :disabled="inheritedIds.has(permission.id)"
                                    class="mt-1 rounded border-gray-300 text-orange-600"
                                />
                                <span class="min-w-0 flex-1"><span class="block break-all text-sm font-medium text-gray-900">{{ permission.name }}</span><span v-if="permission.beschreibung" class="mt-1 block text-xs leading-5 text-gray-500">{{ permission.beschreibung }}</span></span>
                                <span v-if="inheritedIds.has(permission.id)" class="shrink-0 rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700">Rolle</span>
                                <span v-else-if="form.permission_ids.includes(permission.id)" class="shrink-0 rounded-full bg-orange-100 px-2 py-1 text-xs font-semibold text-orange-700">Zusätzlich</span>
                            </label>
                            <p v-if="category.permissions.length === 0" class="p-4 text-sm text-gray-500">Keine Berechtigungen in dieser Kategorie.</p>
                        </div>
                    </section>
                    <p v-if="filteredCategories.length === 0" class="py-8 text-center text-sm text-gray-500">Keine passende Berechtigung gefunden.</p>
                </div>

                <footer class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-white p-4 sm:flex-row sm:justify-end sm:px-6">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 font-semibold" @click="emit('close')">Abbrechen</button>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-orange-600 px-5 py-2 font-semibold text-white disabled:opacity-50">Zusatzberechtigungen speichern</button>
                </footer>
            </template>
        </form>
    </div>
</template>
