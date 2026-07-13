<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    modules: { type: Array, default: () => [] },
})

const saving = ref(null)

function assignmentFor(module) {
    return (module.assignments || []).find((assignment) => assignment.scope_key === 'global')
}

function effectiveEnabled(module) {
    const assignment = assignmentFor(module)
    return assignment ? Boolean(assignment.enabled) : Boolean(module.global_enabled)
}

function update(module, enabled) {
    saving.value = module.id

    router.put(route('module-settings.update', module.id), {
        enabled,
    }, {
        preserveScroll: true,
        onFinish: () => { saving.value = null },
    })
}
</script>

<template>
    <AppLayout title="Module">
        <Head title="Module" />

        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Modulverwaltung</h2>
                <p class="mt-1 text-sm text-gray-500">Deaktivieren blendet Funktionen aus und sperrt den Backend-Zugriff. Daten bleiben erhalten.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="rounded-lg bg-white p-5 shadow">
                    <h3 class="text-sm font-semibold text-gray-800">Globale Matrix-Module</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Module gelten für die gesamte Plattform. Welche Projekte und Aktionen ein Mitarbeiter verwenden darf, wird über Projektzuweisungen, Rollen und Berechtigungen gesteuert.
                    </p>
                </section>

                <section class="grid gap-4 md:grid-cols-2">
                    <article v-for="module in modules" :key="module.id" class="rounded-lg bg-white p-5 shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ module.category }}</p>
                                <h3 class="mt-1 text-lg font-semibold text-gray-900">{{ module.name }}</h3>
                                <p class="mt-2 text-sm text-gray-600">{{ module.description }}</p>
                                <p class="mt-2 font-mono text-xs text-gray-400">{{ module.key }}</p>
                            </div>

                            <button
                                type="button"
                                class="rounded-full px-4 py-2 text-sm font-semibold transition"
                                :class="effectiveEnabled(module) ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700'"
                                :disabled="saving === module.id || !module.is_enforced || (module.is_system_module && effectiveEnabled(module))"
                                @click="update(module, !effectiveEnabled(module))"
                            >
                                {{ saving === module.id ? 'Speichert …' : (effectiveEnabled(module) ? 'Aktiv' : 'Inaktiv') }}
                            </button>
                        </div>

                        <p v-if="!module.is_enforced" class="mt-3 text-xs text-amber-700">
                            Vorgemerkt – Backend-Schutz wird in einer spaeteren Pilotphase angeschlossen.
                        </p>
                    </article>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
