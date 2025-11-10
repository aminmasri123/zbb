<script setup>
import { ref, computed, onMounted, onBeforeUnmount  } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import NavigationMenu from '@/Components/Header/NavbarHeader.vue';
import ToastSuccessError from '@/Components/Utils/ToastSuccessError.vue';
// Sidebar-Komponenten importieren
import DashboardSidebar from '@/Components/Sidebar/DashboardSidebar.vue';
import ProfileSidebar from '@/Components/Sidebar/ProfileSidebar.vue';
import OrganisationSidebar from '@/Components/Sidebar/OrganisationSidebar.vue';
import RessourcenSidebar from '@/Components/Sidebar/RessourcenSidebar.vue';
import FinanzenSidebar from '@/Components/Sidebar/FinanzenSidebar.vue';

import SweetalertSuccessError from '@/Components/Utils/SweetalertSuccessError.vue';

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
  } else if (url.startsWith('/ressourcen')) {
        return RessourcenSidebar;
    }  else if (url.startsWith('/finanzen')) {
        return FinanzenSidebar;
    } return DashboardSidebar; // Standardmäßig DashboardSidebar, wenn kein anderer Pfad passt
});
const sidebarOpen = ref(false); // Für die mobile Ansicht Sidebar umschalten
const displayHideTextSidebar = ref(false);

onMounted(() => {
    const syncLogout = (event) => {
        if (event.key === 'logout') {
            router.visit(route('welcome'))
        }
    }

    window.addEventListener('storage', syncLogout)
    onBeforeUnmount(() => {
        window.removeEventListener('storage', syncLogout)
    })
})

</script>
<script>
export default {
  data() {
    return {
      activeMenu: null, // Tracks the currently open menu
      showingNavigationDropdown: false,
      sidebarOpen: false,
      permissions: [],
      roles: [],
      sidebarTextHidden:[],
    };
  },
  props: {
        href: {
            type: String,
            //required: true, // Hier wird ein Fehler auftreten, wenn href nicht übergeben wird.
            default: '#', // Optional: Standardwert, wenn href nicht angegeben ist

        },
        title:{
            type: String,
        }
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
        <!-- Sweetalert Success Error Message -->
             <ToastSuccessError />

             <SweetalertSuccessError />

             <!-- End Sweetalert Success Error Message -->

             <Banner />

             <div id="app" class="main-wrapper ">
                 <div class="min-h-screen bg-gray-100">
                <!-- Page Sidebar -->
                <!-- Sidebar -->
                <NavigationMenu :sidebar-open="sidebarOpen" :display-hide-text-sidebar="displayHideTextSidebar"
                        @toggle-sidebar="sidebarOpen = !sidebarOpen"
                        @toggle-sidebar-text="displayHideTextSidebar = !displayHideTextSidebar"
                    />

                <div class="flex">
                    <component class="min-h-screen" :is="currentSidebar" :displayHideTextSidebar="displayHideTextSidebar" :sidebarOpen="sidebarOpen" :activeMenu="activeMenu" :toggleMenu="toggleMenu"/>

                    <main class="w-full bg-gray-100 h-full "
                        :class="{'hidden sm:block':sidebarOpen}">

                        <!-- Page Heading -->
                        <header v-if="$slots.header" class="bg-white relative w-full shadow mb-5 z-20">
                            <div class="text-center sm:text-left max-w-7xl mx-36 py-6">
                                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                                    <slot name="header" />
                                </h2>
                            </div>
                        </header>
                        <div class="px-10">
                            <slot  />
                        </div>
                    </main>
                </div>

            </div>
        </div>
    </div>

</template>
