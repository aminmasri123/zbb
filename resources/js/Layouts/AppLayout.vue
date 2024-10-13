<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import NavigationMenu from '@/Components/Header/NavbarHeader.vue';

// Sidebar-Komponenten importieren
import DashboardSidebar from '@/Components/Sidebar/DashboardSidebar.vue';
import ProfileSidebar from '@/Components/Sidebar/ProfileSidebar.vue';
import OrganisationSidebar from '@/Components/Sidebar/OrganisationSidebar.vue';
defineProps({
    title: String,
});

// Aktuelle Seite/Route
const page = usePage();

// Dynamisch die Sidebar auswählen basierend auf der Route oder Seite
const currentSidebar = computed(() => {
  const url = page.url; // Aktuelle URL abrufen
  if (url.startsWith('/dashboard')) {
    return DashboardSidebar;
  } else if (url.startsWith('/user')) {
    return ProfileSidebar;
  } else if (url.startsWith('/organisation')) {
    return OrganisationSidebar;
  }
  return DashboardSidebar; // Standardmäßig DashboardSidebar, wenn kein anderer Pfad passt
});
const sidebarOpen = ref(false); // Für die mobile Ansicht Sidebar umschalten



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
                <!-- Page Sidebar -->
                <div class="flex">
                    <!-- Hamburger Button (nur auf Mobilgeräten sichtbar) -->
                    <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="md:hidden text-black ml-10 p-4 z-50 absolute top-1 left-12"
                    >
                    <i class="la la-bars text-2xl"></i> <!-- Icon-Größe angepasst -->
                    </button>

                    <!-- Sidebar -->
                    <component :is="currentSidebar" :sidebarOpen="sidebarOpen" :activeMenu="activeMenu" :toggleMenu="toggleMenu"/>

                    <main class="w-full md:w-5/6 bg-gray-100 h-full ">
                        <!-- Page Heading -->
                        <header v-if="$slots.header" class="bg-white shadow mb-5">
                            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                                <slot name="header" />
                            </div>
                        </header>
                        <div class="mx-7">
                            <slot  />
                        </div>
                    </main>
                </div>

            </div>
        </div>
    </div>


</template>
