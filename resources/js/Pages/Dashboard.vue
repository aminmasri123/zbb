<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Welcome from '@/Components/Welcome.vue';


// Beispiel-Daten (später dynamisch ersetzen)
const stats = [
  { label: 'Projekte', value: 7, icon: '📁', color: 'bg-blue-100 text-blue-800' },
  { label: 'Teilnehmer', value: 120, icon: '👥', color: 'bg-green-100 text-green-800' },
  { label: 'Räumlichkeiten', value: 5, icon: '🏢', color: 'bg-yellow-100 text-yellow-800' },
  { label: 'Dienstwagen', value: 3, icon: '🚗', color: 'bg-red-100 text-red-800' },
  { label: 'Geräte', value: 42, icon: '💻', color: 'bg-purple-100 text-purple-800' },
];


</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>{{$t('Dashboard')}}</template>

        <div class="py-2 bg-gray-50 min-h-screen">
            <!-- Begrüßung -->
            <div class="max-w-7xl mx-auto px-4 mb-8">
                <div class="bg-gradient-to-r from-orange-500 to-orange-200 rounded-lg shadow-lg px-8 py-4 text-white">
                    <h1 class="text-2xl font-bold mb-2">Willkommen in Webbasierte Phönix 2 🚀</h1>
                    <p class="text-lg">Die Lösung für modernes Projektmanagement.</p>
                </div>
            </div>

            <!-- Statistiken -->
            <div class="max-w-7xl mx-auto px-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-6 mb-10">
                <div v-for="stat in stats" :key="stat.label" :class="['rounded-lg shadow p-6 flex items-center gap-4', stat.color]">
                <span class="text-3xl">{{ stat.icon }}</span>
                <div>
                    <div class="text-2xl font-bold">{{ stat.value }}</div>
                    <div class="text-sm">{{ stat.label }}</div>
                </div>
                </div>
            </div>

        </div>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <Welcome />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
