<template >
    <SidebarLayout>
        <!-- Übersicht -->
        <li class="text-white text-sm font-bold uppercase">
            <span>Übersicht</span>
        </li>
        <!-- Dashboard Submenu -->
        <li class="submenu">
            <a href="#" @click.prevent="toggleMenu('dashboard')" class="flex items-center text-white py-2 hover:bg-gray-700 transition duration-200">
                <i class="la la-dashboard mr-2"></i>
                <span>Dashboard</span>
                <span :class="{'rotate-180': activeMenu === 'dashboard'}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
            </a>
            <ul v-show="activeMenu === 'dashboard'" class="pl-6 mt-2 space-y-2">
                <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('dashboard')">Übersicht</Link></li>
            </ul>
        </li>
        <!-- Schule Submenu -->
        <li v-if="$page.props.roles.includes('Administrator')" class="submenu" >
            <a href="#" @click.prevent="toggleMenu('schule')" class="flex items-center text-white py-2 hover:bg-gray-700 transition duration-200">
                <i class="la la-dashboard mr-2"></i>
                <span>Abteilung</span>
                <span :class="{'rotate-180': activeMenu === 'schule'}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
            </a>
            <ul v-show="activeMenu === 'schule'" class="pl-6 mt-2 space-y-2">
                <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('user.index')">Schuleübersicht</Link></li>
            </ul>
        </li>
        <!-- Benutzer Submenu -->
        <li v-if="$page.props.roles.includes('Administrator')" class="submenu" >
            <a href="#" @click.prevent="toggleMenu('benutzer')" class="flex items-center text-white py-2 hover:bg-gray-700 transition duration-200">
                <i class="la la-dashboard mr-2"></i>
                <span>Benutzer</span>
                <span :class="{'rotate-180': activeMenu === 'benutzer'}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
            </a>
            <ul v-show="activeMenu === 'benutzer'" class="pl-6 mt-2 space-y-2">
                <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('user.index')">Benutzerübersicht</Link></li>
            </ul>
        </li>
        <!-- Weitere Menüpunkte -->
    </SidebarLayout>

</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import SidebarLayout from '../Sidebar/SidebarLayout.vue';

</script>
<script>

export default {
props: {
activeMenu: String, // Welches Menü ist aktiv
toggleMenu: Function, // Funktion, um Menüs umzuschalten
roles: Array, // Rollen vom Backend
permissions: Array, // Berechtigungen vom Backend
},

}
</script>
