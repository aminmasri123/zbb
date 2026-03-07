<template>
    <nav class="sticky top-0 w-full  z-50 dark:bg-gray-800 bg-gray-100 dark:text-gray-100">

        <!-- Primary Navigation Menu -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-black">
            <div class="flex justify-between h-20 ">

                <div class="flex ">
                    <!-- Hamburger Button (nur auf Mobilgeräten sichtbar) -->
                    <button
                        @click="$emit('toggle-sidebar')"
                        class=" text-black pr-4 block md:hidden dark:text-gray-100"
                        :class="{' ':sidebarOpen}"
                    >
                        <i class="la la-bars text-2xl"></i>
                    </button>

                    <!-- Knopf Sidebar displayHideTextSidebar -->
                    <button
                        @click="$emit('toggle-sidebar-text')"
                        class=" text-black  pr-4 hidden md:block dark:text-gray-100"
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
                    <NavLink :href="route('dashboard')"
                        :active="route().current('dashboard')
                            || route().current('dashboard.*')
                            || route().current('user.*')
                            || route().current('kooperationspartner.*')
                            || route().current('abteilung.*')
                            || route().current('projekt.*')
                            || route().current('bereich.*')
                            "
                    class="dark:text-gray-100 text-[17px] ">
                        {{ $t('dashboard') }}
                    </NavLink>
                    <NavLink :href="route('organisation.index')" :active="route().current('organisation.index')" class="dark:text-gray-100 text-[17px] ">
                        {{ $t('organisation') }}
                    </NavLink>
                    <NavLink :href="route('ressourcen.index')" :active="route().current('ressourcen.index')" class="dark:text-gray-100  text-[17px] ">
                        {{ $t('Ressourcen') }}
                    </NavLink>
                    <NavLink :href="route('finanzen.index')" :active="route().current('finanzen.index')" class="dark:text-gray-100  text-[17px] ">
                        {{ $t('Finanzen') }}
                    </NavLink>
                </div>

                <div class="flex items-center sm:ml-6">
                    <!-- Teams Dropdown -->
                    <Dropdown v-if="$page.props.auth.user.projekte" align="right" width="60">
                        <template #trigger>
                                <button type="button" class="inline-flex items-center py-2 border border-transparent text-sm leading-4 font-medium rounded-md dark:text-white dark:hover:text-gray-300 hover:text-gray-700 transition ease-in-out duration-150">
                                    <i class="la text-lg la-briefcase" aria-hidden="true"></i>
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
                            <button class="inline-flex items-center px-3 py-2 mx-1 border border-transparent text-sm leading-4 font-medium rounded-md dark:text-white dark:hover:text-gray-300 hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
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
                        @click="switchTheme"
                        class="inline-flex items-center py-2 mr-3 border border-transparent text-sm leading-4 font-medium rounded-md  dark:text-white dark:hover:text-gray-300 hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <i class="las la-adjust text-lg"></i>
                    </button>



                    <!-- Notification Dropdown -->
                    <Dropdown align="right" width="80">
                        <template #trigger >
                            <button class="inline-flex items-center py-2 mx-1 border border-transparent text-sm leading-4 font-medium rounded-md dark:text-white dark:hover:text-gray-300 hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                <i class="las la-bell text-lg"></i>
                            </button>
                        </template>
                        <template #content>
                            <div class="block px-4 py-2 text-xs text-gray-400 border-b border-gray-200">{{$t('Benachrichtigungen')}}</div>
                            <li v-for="notification in notifications" :key="notification.id" class="list-none text-sm py-2 px-3 border-b border-gray-200 hover:bg-slate-100 dark:text-gray-700">
                                {{ notification.data.message }}
                            </li>



                            <span @click="markAllAsRead" class=" flex text-xs text-gray-100 justify-center cursor-pointer p-2 bg-gray-700 hover:bg-gray-900">{{$t('Alle als gelesen markieren')}}</span>
                        </template>
                        <!-- Dropdown content -->
                    </Dropdown>

                    <!-- Settings Dropdown -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                <img class="h-12 w-12 rounded-full object-cover"  :src="`/storage/${$page.props.auth.user.profile_photo_path}`"  :alt="$page.props.auth.user.name">
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
                <ResponsiveNavLink :href="route('dashboard')" :active="['dashboard','benutzer','partner','abteilung','projekt','bereich','einstellung'].includes(route().current())">
                    {{$t('dashboard')}}
                </ResponsiveNavLink>
                <ResponsiveNavLink :href="route('organisation.index')" :active="route().current('organisation.index')">
                    {{$t('organisation')}}
                </ResponsiveNavLink>
                <ResponsiveNavLink :href="route('ressourcen.index')" :active="route().current('ressourcen.index')">
                    {{ $t('Ressourcen') }}
                </ResponsiveNavLink>
            </div>

            <!-- Responsive Settings Options -->
            <div class="pt-4 pb-1 border-t border-gray-200">
                <div class="flex items-center px-4">
                    <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 mr-3">
                        <img class="h-10 w-10 rounded-full object-cover"  :src="`/storage/${$page.props.auth.user.profile_photo_path}`"  :alt="$page.props.auth.user.name">
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
    import { ref } from 'vue';
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
    import { Inertia } from '@inertiajs/inertia'

    const sidebarTextHidden = ref(false);
    const props = defineProps({
    //sidebarOpen: Boolean,
    displayHideTextSidebar: Boolean,
    });



function switchToProjekt(projekt) {
    Inertia.post(route('projekt.switch'), { projekt_id: projekt.id }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            console.log('Projekt gewechselt, bleibt auf derselben Seite');
        }
    })
}



const page = usePage();
const notifications = ref(page.props.notify?.notifications || []);


const markAllAsRead = async () => {
    console.log('bin drin');
  try {
    await axios.post(route('notifications.readAll'));
    notifications.value = []; // sofort im Dropdown leeren
  } catch (error) {
    console.error('Fehler beim Markieren:', error);
  }
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
