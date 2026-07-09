<script setup>
import { ref, computed, onMounted, onBeforeUnmount  } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import NavigationMenu from '@/Components/Header/NavbarHeader.vue';
//import ToastSuccessError from '@/Components/Utils/ToastSuccessError.vue';
// Sidebar-Komponenten importieren
import DashboardSidebar from '@/Components/Sidebar/DashboardSidebar.vue';
import ProfileSidebar from '@/Components/Sidebar/ProfileSidebar.vue';
import OrganisationSidebar from '@/Components/Sidebar/OrganisationSidebar.vue';
import RessourcenSidebar from '@/Components/Sidebar/RessourcenSidebar.vue';
import FinanzenSidebar from '@/Components/Sidebar/FinanzenSidebar.vue';

import SweetalertSuccessError from '@/Components/Utils/SweetalertSuccessError.vue';

// Aktuelle Seite/Route
const page = usePage();

const normalizePath = (url) => {
  const parsedUrl = new URL(url, window.location.origin);
  const basePath = window.assetBaseUrl
    ? new URL(window.assetBaseUrl, window.location.origin).pathname.replace(/\/$/, '')
    : '';

  let path = parsedUrl.pathname;

  if (basePath && path === basePath) {
    path = '/';
  } else if (basePath && path.startsWith(`${basePath}/`)) {
    path = path.slice(basePath.length);
  }

  return path || '/';
};

// Dynamisch die Sidebar auswählen basierend auf der Route oder Seite
const currentSidebar = computed(() => {
  const url = normalizePath(page.url); // Aktuelle URL ohne Installations-Unterordner
  if (url.startsWith('/dashboard') || url.startsWith('/anwesenheitsdaten') || url.startsWith('/klassenbuecher')) {
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
const dismissedAppPopups = ref([]);

const visibleAppPopups = computed(() => {
    const popups = page.props.appPopups || [];
    return popups.filter((popup) => !dismissedAppPopups.value.includes(popup.id));
});

onMounted(() => {
    dismissedAppPopups.value = JSON.parse(localStorage.getItem('dismissedAppPopups') || '[]');

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

function dismissAppPopup(id) {
    dismissedAppPopups.value = [...new Set([...dismissedAppPopups.value, id])];
    localStorage.setItem('dismissedAppPopups', JSON.stringify(dismissedAppPopups.value));
}

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
  mounted() {
    this.syncActiveMenuWithPage();
  },
  watch: {
    '$page.component'() {
      this.syncActiveMenuWithPage();
    },
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
    syncActiveMenuWithPage() {
      if (this.$page.component?.startsWith('Einstellung') && !this.activeMenu) {
        this.activeMenu = 'admin';
      }
    },
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
             <!-- <ToastSuccessError /> -->

             <SweetalertSuccessError />

             <!-- End Sweetalert Success Error Message -->

             <div v-if="visibleAppPopups.length" class="fixed right-5 top-20 z-50 w-full max-w-sm space-y-3">
                <div
                    v-for="popup in visibleAppPopups"
                    :key="popup.id"
                    :class="[
                        'rounded border bg-white p-4 shadow-lg',
                        popup.level === 'danger' ? 'border-red-300' : popup.level === 'warning' ? 'border-yellow-300' : popup.level === 'success' ? 'border-green-300' : 'border-orange-300'
                    ]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">{{ popup.title }}</h3>
                            <p class="mt-1 text-sm text-gray-700">{{ popup.message }}</p>
                        </div>
                        <button class="text-xl leading-none text-gray-400 hover:text-gray-700" @click="dismissAppPopup(popup.id)">&times;</button>
                    </div>
                </div>
             </div>

             <Banner />

             <div id="app" class="main-wrapper ">
                 <div class="min-h-screen bg-[var(--bg)] text-[var(--primary)]">
                <!-- Page Sidebar -->
                <!-- Sidebar -->
                <NavigationMenu :sidebar-open="sidebarOpen" :display-hide-text-sidebar="displayHideTextSidebar"
                        @toggle-sidebar="sidebarOpen = !sidebarOpen"
                        @toggle-sidebar-text="displayHideTextSidebar = !displayHideTextSidebar"
                    />

                <div class="flex">
                    <component class="min-h-screen" :is="currentSidebar" :displayHideTextSidebar="displayHideTextSidebar" :sidebarOpen="sidebarOpen" :activeMenu="activeMenu" :toggleMenu="toggleMenu"/>

                    <main class="w-full bg-[var(--bg)] h-full "
                        :class="{'hidden sm:block':sidebarOpen}">

                        <!-- Page Heading -->
                        <header v-if="$slots.header" class="bg-[var(--surfaceTint)] border-b border-[var(--border)] relative w-full shadow mb-5 z-20">
                            <div class="px-10">
                                <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                                    <div class="font-semibold text-xl text-[var(--primary)] leading-tight">
                                        <slot name="header" />
                                    </div>
                                </div>
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
