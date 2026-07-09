<template>
    <nav class="sticky top-0 w-full z-50 border-b border-[var(--border)] bg-[var(--headerBg)] text-[var(--primary)] shadow-sm">

        <!-- Primary Navigation Menu -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-[var(--primary)]">
            <div class="flex justify-between h-20 ">

                <div class="flex ">
                    <!-- Hamburger Button (nur auf Mobilgeräten sichtbar) -->
                    <button
                        @click="$emit('toggle-sidebar')"
                        class="text-[var(--primary)] pr-4 block md:hidden"
                        :class="{' ':sidebarOpen}"
                    >
                        <i class="la la-bars text-2xl"></i>
                    </button>

                    <!-- Knopf Sidebar displayHideTextSidebar -->
                    <button
                        @click="$emit('toggle-sidebar-text')"
                        class="text-[var(--primary)] pr-4 hidden md:block"
                        :class="{' ':displayHideTextSidebar}"
                    >
                        <i class="la la-bars text-2xl"></i>
                    </button>
                     <!-- Logo -->
                     <div class="shrink-0 flex items-center">
                        <Link :href="route('dashboard')">
                            <ApplicationMark class="block h-9 w-auto" />
                        </Link>
                    </div>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:flex text-center ">
                    <NavLink v-if="canAny(dashboardNavPermissions)" :href="route('dashboard')"
                        :active="route().current('dashboard')
                            || route().current('dashboard.*')
                            || route().current('user.*')
                            || route().current('kooperationspartner.*')
                            || route().current('abteilung.*')
                            || route().current('projekt.*')
                            || route().current('bereich.*')
                            || route().current('klassenbuch.*')
                            "
                    class="text-[17px] text-[var(--primary)]">
                        {{ $t('dashboard') }}
                    </NavLink>
                    <NavLink v-if="canAny(organisationNavPermissions)" :href="route('organisation.index')" :active="route().current('organisation.index')" class="text-[17px] text-[var(--primary)]">
                        {{ $t('organisation') }}
                    </NavLink>
                    <NavLink v-if="canAny(ressourcenNavPermissions)" :href="route('ressourcen.index')" :active="route().current('ressourcen.index')" class="text-[17px] text-[var(--primary)]">
                        {{ $t('Ressourcen') }}
                    </NavLink>
                    <NavLink v-if="canAny(finanzenNavPermissions)" :href="route('finanzen.index')" :active="route().current('finanzen.index')" class="text-[17px] text-[var(--primary)]">
                        {{ $t('Finanzen') }}
                    </NavLink>
                </div>

                <div class="flex items-center sm:ml-6">
                    <!-- Teams Dropdown -->
                    <Dropdown v-if="$page.props.auth.user.projekte" align="right" width="60">
                        <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex max-w-[220px] items-center gap-2 rounded-md border border-[var(--border)] bg-white/40 px-3 py-2 text-sm font-medium leading-4 text-primary shadow-sm transition duration-150 ease-in-out hover:text-buttonPrimary"
                                    :title="`Aktives Projekt: ${currentProjektName}`"
                                >
                                    <i class="la text-lg la-briefcase shrink-0" aria-hidden="true"></i>
                                    <span class="hidden sm:inline min-w-0 truncate text-gray-800">{{ currentProjektName }}</span>
                                </button>
                        </template>
                        <template #content>
                            <div class="w-40 ">
                                <!-- Team Management -->
                                <template v-if="$page.props.auth.user.projekte">
                                    <div class="block px-4 py-2 text-xs text-gray-400">
                                        {{$t('team')}}
                                    </div>

                                    <!-- Team Settings -->
                                    <DropdownLink :href="route('dashboard', $page.props.auth.user.current_team)">
                                        {{$t('teameinstellung')}}
                                    </DropdownLink>

                                    <DropdownLink v-if="$page.props.jetstream.canCreateTeams" :href="route('dashboard')">
                                        {{$t('Neues_Team_beitreten')}}
                                    </DropdownLink>

                                    <div class="border-t border-gray-200" />

                                    <!-- Team Switcher -->
                                    <div class="block px-4 py-2 text-xs text-gray-800 dark:text-gray-800">
                                        {{$t('projekt_wechseln')}}
                                    </div>

                                    <template v-for="projekt in $page.props.auth.user.projekte" :key="projekt.id" >
                                        <button
                                            @click="switchToProjekt(projekt)"
                                            class="border-t w-full text-left px-4 py-2"
                                        >
                                            <div class="flex items-center justify-center">
                                                <svg v-if="projekt.id == $page.props.auth.user.current_team_id"
                                                    class="mr-2 h-5 w-5 text-green-400"
                                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <div class="text-gray-800 dark:text-gray-800">{{ projekt.name }}</div>
                                            </div>
                                        </button>
                                    </template>


                                </template>
                            </div>
                        </template>
                    </Dropdown>

                    <!-- Sprache Dropdown -->
                    <Dropdown align="right" width="48">
                        <template #trigger >
                            <button class="inline-flex items-center px-3 py-2 mx-1 border border-transparent text-sm leading-4 font-medium rounded-md text-primary hover:text-buttonPrimary focus:outline-none transition ease-in-out duration-150">
                                <i class="las la-globe text-lg"></i>
                            </button>
                        </template>
                        <template #content>
                            <div class="block px-4 py-2 text-xs text-gray-400">{{$t('sprachen')}}</div>
                            <DropdownLink
                                :href="'#'"
                                @click.prevent="changeLocale('de')"
                                :class="{ 'bg-gray-200 decoration-black': currentLang  === 'de' }"
                            >
                                <i class="las la-flag text-lg"></i> <!-- Deutsch Flagge Icon -->
                                <span>{{$t('deutsch')}}</span>
                            </DropdownLink>
                            <DropdownLink :href="'#'" @click.prevent="changeLocale('en')"
                            :class="{ 'bg-gray-200 decoration-black': currentLang  === 'en' }">
                                <i class="las la-flag text-lg"></i> <!-- Englisch Flagge Icon -->
                                <span>{{$t('english')}}</span>
                            </DropdownLink>
                            <DropdownLink :href="'#'" @click.prevent="changeLocale('fr')"
                            :class="{ 'bg-gray-200 decoration-black': currentLang  === 'fr' }"  >
                                <i class="las la-flag text-lg"></i> <!-- Französisch Flagge Icon -->
                                <span>{{$t('französich')}}</span>
                            </DropdownLink>
                            <div class="border-t border-gray-200"></div>
                            <span class=" flex text-xs text-red-500 text-center p-2 bg-yellow-300">"Die Übersetzung ist in Bearbeitung."</span>
                        </template>
                        <!-- Dropdown content -->
                    </Dropdown>

                    <button
                        @click="cycleTheme"
                        class="inline-flex items-center py-2 mr-3 border border-transparent text-sm leading-4 font-medium rounded-md text-primary hover:text-buttonPrimary focus:outline-none transition ease-in-out duration-150">
                            <i class="las la-adjust text-lg"></i>
                    </button>



                    <!-- Notification Dropdown -->
                    <Dropdown align="right" width="80">
                        <template #trigger >
                            <button class="inline-flex items-center py-2 mx-1 border border-transparent text-sm leading-4 font-medium rounded-md text-primary hover:text-buttonPrimary focus:outline-none transition ease-in-out duration-150">
                                <i class="las la-bell text-lg"></i>
                                <span v-if="notifications.length > 0" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full text-xs w-5 h-5 flex items-center justify-center">
                                    {{ notifications.length }}
                                </span>

                            </button>
                        </template>
                        <template #content>
                            <div class="block px-4 py-2 text-xs text-gray-400 border-b border-gray-200">{{$t('Benachrichtigungen')}}</div>
                            <li v-for="notification in notifications" :key="notification.id" class="list-none text-sm py-2 px-3 border-b border-gray-200 hover:bg-slate-100 dark:text-gray-700">
                                <a :href="notification.data.link" target="_blank" class="text-decoration-none">
                                    {{ notification.data.message }}
                                </a>
                            </li>



                            <Link :href="route('notifications.index')" class="flex text-xs text-gray-700 justify-center cursor-pointer p-2 bg-gray-100 hover:bg-gray-200">
                                {{$t('Alle anzeigen')}}
                            </Link>
                            <span v-if="can('notifications.readAll')" @click="markAllAsRead" class=" flex text-xs text-gray-100 justify-center cursor-pointer p-2 bg-gray-700 hover:bg-gray-900">{{$t('Alle als gelesen markieren')}}</span>
                        </template>
                        <!-- Dropdown content -->
                    </Dropdown>

                    <!-- Settings Dropdown -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                <img class="h-12 w-12 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </button>

                            <span v-else class="inline-flex rounded-md">
                                <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                    <i class="la la-lg la-user" aria-hidden="true"></i>
                                </button>
                            </span>
                        </template>
                        <template #content>
                            <!-- Account Management -->
                            <div class="block px-4 pt-2 text-xs text-gray-400">
                                Manage Account
                            </div>
                            <div class="block px-4 pb-2 text-xs">
                                {{ $page.props.auth.user.first_name }} {{ $page.props.auth.user.last_name }}
                            </div>

                            <DropdownLink :href="route('profile.show')">
                                Profile
                            </DropdownLink>

                            <DropdownLink :href="route('notifications.index')">
                                {{ $t('Benachrichtigungen') }}
                            </DropdownLink>

                            <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                API Tokens
                            </DropdownLink>

                            <div class="border-t border-gray-200" />

                            <!-- Authentication -->
                            <form @submit.prevent="logout">
                                <DropdownLink as="button">
                                    <p>{{ $t('abmelden') }}</p>

                                </DropdownLink>
                            </form>
                        </template>
                        <!-- Dropdown content -->
                    </Dropdown>
                </div>
                <!-- Hamburger Menu -->
                <div class="-mr-2 flex items-center sm:hidden">
                    <button class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out" @click="toggleNavigationDropdown">
                        <svg
                            class="h-6 w-6"
                            stroke="currentColor"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <path
                                :class="{'hidden': showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                            <path
                                :class="{'hidden': !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Responsive Navigation Menu -->
        <div :class="{'block': showingNavigationDropdown, 'hidden': !showingNavigationDropdown}" class="sm:hidden">
            <div class="pt-2 pb-3 space-y-1">
                <ResponsiveNavLink v-if="canAny(dashboardNavPermissions)" :href="route('dashboard')" :active="['dashboard','benutzer','partner','abteilung','projekt','bereich','einstellung'].includes(route().current()) || route().current('klassenbuch.*')">
                    {{$t('dashboard')}}
                </ResponsiveNavLink>
                <ResponsiveNavLink v-if="canAny(organisationNavPermissions)" :href="route('organisation.index')" :active="route().current('organisation.index')">
                    {{$t('organisation')}}
                </ResponsiveNavLink>
                <ResponsiveNavLink v-if="canAny(ressourcenNavPermissions)" :href="route('ressourcen.index')" :active="route().current('ressourcen.index')">
                    {{ $t('Ressourcen') }}
                </ResponsiveNavLink>
                <ResponsiveNavLink v-if="canAny(finanzenNavPermissions)" :href="route('finanzen.index')" :active="route().current('finanzen.index')">
                    {{ $t('Finanzen') }}
                </ResponsiveNavLink>
            </div>

            <!-- Responsive Settings Options -->
            <div class="pt-4 pb-1 border-t border-gray-200">
                <div class="flex items-center px-4">
                    <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 mr-3">
                        <img class="h-10 w-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                    </div>

                    <div>
                        <div class="font-medium text-sm text-gray-500">{{ $page.props.auth.user.first_name }} {{ $page.props.auth.user.last_name }}</div>
                        <div class="font-medium text-sm text-gray-500">{{ $page.props.auth.user.email }} </div>
                    </div>
                </div>

                <div class="mt-3 space-y-1">
                    <!-- Profile -->
                    <ResponsiveNavLink :href="route('profile.show')">Profile</ResponsiveNavLink>

                    <!-- Logout -->
                    <form method="POST" :action="route('logout')" @submit.prevent="logout">
                        <ResponsiveNavLink as="button">
                            {{$t('abmelden')}}
                        </ResponsiveNavLink>
                    </form>
                </div>
            </div>
        </div>
    </nav>
</template>

<script setup>
    import { computed, ref } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import ApplicationMark from '@/Components/ApplicationMark.vue';
    import NavLink from '@/Components/NavLink.vue';
    import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownLink from '@/Components/DropdownLink.vue';
    import { usePage } from '@inertiajs/vue3';
    import axios from 'axios';
    import { useI18n } from 'vue-i18n';
    import { switchTheme } from '../../theme';
    import { usePermissions } from '@/utils/permissions';

    const sidebarTextHidden = ref(false);
    const props = defineProps({
    //sidebarOpen: Boolean,
    displayHideTextSidebar: Boolean,
    });



function switchToProjekt(projekt) {
    const offeneGruppe = page.component === 'Gruppe/GruppeHasTeilnehmer/Index'
        ? page.props.gruppe
        : null;

    router.post(route('projekt.switch'), {
        projekt_id: projekt.id,
        gruppe_id: offeneGruppe?.id || null,
    }, {
        preserveScroll: false,
        preserveState: false,
        onSuccess: () => {
            console.log('Projekt gewechselt, bleibt auf derselben Seite');
        }
    })
}



const page = usePage();
const { can, canAny } = usePermissions();
const notifications = ref(page.props.notify?.notifications || []);
const dashboardNavPermissions = [
    'dashboard.index',
    'apps.index',
    'benutzer.index',
    'kooperationspartner.index',
    'standort.index',
    'abteilung.index',
    'projekt.index',
    'bereich.index',
    'gruppe.index',
    'teilnehmer.index',
    'klassenbuch.index',
    'berechtigung.index',
    'materialanforderung.index',
];
const organisationNavPermissions = [
    'organisation.index',
    'kooperationspartner.index',
    'benutzer.index',
];
const ressourcenNavPermissions = [
    'ressourcen.index',
    'personal.index',
    'dienstwagen.index',
    'dienstwagen.fahrtenbuch.index',
    'dienstwagen.wartung.index',
    'dienstwagen.reports.index',
    'raeumlichkeiten.index',
    'lager.index',
    'geraet.index',
    'geraet.ausgabe.index',
    'geraet.rueckgabe.index',
];
const finanzenNavPermissions = [
    'finanzen.index',
    'fahrtarten.index',
    'fahrtkosten.index',
    'fahrtkostenAbrechnung.store',
    'printing.index',
];
const currentProjektName = computed(() => {
    if (page.props.currentProjekt?.name) {
        return page.props.currentProjekt.name;
    }

    const currentProjekt = page.props.auth.user.projekte?.find((projekt) => projekt.id === page.props.auth.user.current_team_id);

    return currentProjekt?.name || 'Kein Projekt';
});


const markAllAsRead = async () => {
    console.log('bin drin');
  try {
    await axios.post(route('notifications.readAll'));
    notifications.value = []; // sofort im Dropdown leeren
  } catch (error) {
    console.error('Fehler beim Markieren:', error);
  }
}

const persistTheme = async (theme) => {
    try {
        await axios.post(route('user.theme.update'), { theme });
    } catch (error) {
        console.error('Theme konnte nicht gespeichert werden:', error);
    }
}

const cycleTheme = async () => {
    const theme = switchTheme();
    await persistTheme(theme);
}

const emit = defineEmits(['toggle-sidebar', 'toggle-sidebar-text']);
    const { t, locale } = useI18n();

    const changeLocale = (lang) => {
        locale.value = lang; // Sprache wechseln
        document.documentElement.lang = lang; // HTML lang-Attribut setzen
    };
    const showingNavigationDropdown = ref(false);
    const toggleNavigationDropdown = () => {
        showingNavigationDropdown.value = !showingNavigationDropdown.value;
    };

    const logout = () => {
    router.post(route('logout'), {}, {
        onFinish: () => {
            // Signal für alle anderen Tabs setzen
            localStorage.setItem('logout', Date.now());
        }
    });
};





function switchToTeam(team) {
    router.get(route('teilnehmer.index'), { projekt_id: team.id })
}
</script>
<script>
    export default {
        data() {
            return {
            currentLang: 'de', // Fallback, falls keine Sprache gesetzt ist
            sidebarOpen: false,

            };
        },
        mounted() {
            // Stelle sicher, dass `document` verfügbar ist, wenn der DOM vollständig geladen ist
            this.currentLang = document.documentElement.lang || 'de'; // Default-Wert 'en', falls lang nicht gesetzt ist
        },
    };
</script>
