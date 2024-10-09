<template>
    <SidebarLayout>
            <!-- Profile Settings -->
            <li class="text-white text-sm font-bold uppercase">
              <span>Einstellungen</span>
            </li>
            <li class="submenu">
              <a href="#" @click.prevent="toggleMenu('settings')" class="flex items-center text-white py-2 hover:bg-gray-700 transition duration-200">
                <i class="la la-cog mr-2"></i>
                <span>Profil-Einstellungen</span>
                <span :class="{'rotate-180': activeMenu === 'settings'}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
              </a>
              <ul v-show="activeMenu === 'settings'" class="pl-6 mt-2 space-y-2">
                <li><a class="text-gray-400 hover:text-white transition duration-200" href="#">Passwort ändern</a></li>
                <li><a class="text-gray-400 hover:text-white transition duration-200" href="#">Benachrichtigungen</a></li>
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
sidebarOpen: Boolean, // Offen oder geschlossen
activeMenu: String, // Welches Menü ist aktiv
toggleMenu: Function, // Funktion, um Menüs umzuschalten

},

}
</script>
