<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import DashboardSidebar from '@/Components/Sidebar/DashboardSidebar.vue'; // Standardmäßig importierte Sidebar
import NavigationMenu from '@/Components/Header/NavbarHeader.vue';

defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};


</script>
<script>
export default {
    components: {

  },

  data() {
    return {
      activeMenu: null, // Tracks the currently open menu
      showingNavigationDropdown: false,
      sidebarOpen: false,
      permissions: [],
      roles: [],
    };
  },
  props: {
        href: {
            type: String,
            required: true, // Hier wird ein Fehler auftreten, wenn href nicht übergeben wird.
            default: '#', // Optional: Standardwert, wenn href nicht angegeben ist

        },
    },
  methods: {
    toggleMenu(menu) {
      if (this.activeMenu === menu) {
        // Close if already open
        this.activeMenu = '';
      } else {
        // Open the selected menu and close all others
        this.activeMenu = menu;
      }
    },
    toggleSidebar() {
        this.sidebarOpen = !this.sidebarOpen;
        // Schließe Dropdown wenn Sidebar geöffnet wird
        if (this.sidebarOpen) {
            this.showingNavigationDropdown = false;
        }
    },
    toggleNavigationDropdown() {
        this.showingNavigationDropdown = !this.showingNavigationDropdown;
        // Schließe Sidebar, wenn Navigation Dropdown geöffnet wird
        if (this.showingNavigationDropdown) {
            this.sidebarOpen = false;
        }
    },
    setLocale(locale) {
            // Ändere die Sprache in Vue i18n
            this.$i18n.locale = locale;

            // Aktualisiere auch die Sprache im Backend (Laravel)
            fetch('/set-locale', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({ locale: locale })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Optional: Seite neu laden
                    location.reload(); // Optional: Seite neu laden
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        },

  },
};

</script>
<template>
    <div>
        <Head :title="title" />

        <Banner />
        <div id="app" class="main-wrapper ">
            <div class="min-h-screen bg-gray-100">
                    <NavigationMenu/>

                    <!-- Page Heading -->
                    <header v-if="$slots.header" class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            <slot name="header" />
                        </div>
                    </header>


                <!-- Page Sidbar -->
                <div class="flex">
                    <!-- Hamburger Button (nur auf Mobilgeräten sichtbar) -->
                    <button @click="sidebarOpen = !sidebarOpen" class="md:hidden text-black ml-10 p-4 z-50 absolute top-1 left-12">
                        <i class="la la-bars text-2xl"></i> <!-- Icon-Größe angepasst -->
                    </button>
                    <!-- Sidebar -->
                    <slot name="sidebar">
                        <!-- Default Sidebar wenn kein Slot verwendet wird -->
                        <dashboard-sidebar :sidebarOpen="sidebarOpen" :activeMenu="activeMenu" :toggleMenu="toggleMenu"></dashboard-sidebar>
                    </slot>




                    <main class="w-full md:w-5/6 bg-gray-100 h-full p-2">
                        <slot />
                    </main>
                </div>

            </div>
        </div>
    </div>


</template>
