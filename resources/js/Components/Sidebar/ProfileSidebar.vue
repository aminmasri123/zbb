<template>
    <SidebarLayout>
            <!-- Profile Settings -->
            <li v-if="!displayHideTextSidebar" class="text-white text-sm font-bold uppercase">
              <span>{{$t('Einstellungen')}}</span>
            </li>
            <li class="submenu">
              <a href="#" @click.prevent="toggleMenu('settings')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                <i class="las la-address-card la-lg mr-2"></i>
                <span v-if="!displayHideTextSidebar" class="pr-16">{{$t('Profil')}}</span>
                <span :class="{'rotate-180': activeMenu === 'settings'}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
              </a>
              <ul v-show="activeMenu === 'settings'" class="pl-6 mt-2 space-y-2">
                <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('user.profil', $page.props.auth.user.id)">{{$t('Mein_Profil')}}</Link></li>
              </ul>
            </li>


            <!-- Profile Settings -->
            <li class="submenu">
              <a href="#" @click.prevent="toggleMenu('settings')" class="flex items-center text-white hover:bg-gray-700 transition duration-200">
                <i class="la la-cog la-lg mr-2"></i>
                <span v-if="!displayHideTextSidebar" class="pr-16">{{$t('Profil-Einstellungen')}}</span>
                <span :class="{'rotate-180': activeMenu === 'settings'}" class="ml-auto transform transition-transform duration-300 menu-arrow"></span>
              </a>
              <ul v-show="activeMenu === 'settings'" class="pl-6 mt-2 space-y-2">
                <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('profile.show')">{{$t('Passwort_ändern')}}</Link></li>
                <li><Link class="text-gray-400 hover:text-white transition duration-200" :href="route('notifications.index')">{{$t('Benachrichtigungen')}}</Link></li>
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
displayHideTextSidebar: Boolean,

},

}
</script>
