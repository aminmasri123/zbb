<template>
    <div :class="{'block': sidebarOpen, 'hidden': !sidebarOpen, 'md:block': true}" class=" w-64 bg-gray-800 z-40 transition duration-300">
      <div class="overflow-y-auto">
        <div id="sidebar-menu" class="p-4">
          <ul class="space-y-4 pl-0">
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
          </ul>
        </div>
      </div>
    </div>
  </template>

  <script>

  export default {
    props: ['sidebarOpen', 'activeMenu', 'toggleMenu']
  }
  </script>
