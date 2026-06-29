const themeNames = ['air', 'dark', 'womanly', 'champion', 'sprint', 'arena', 'pulse', 'trail', 'bazaar', 'vital'];
const themeClasses = themeNames.map((theme) => `theme-${theme}`);

const themeVariables = {
    air: {
        bg: '#eef8ff',
        card: '#ffffff',
        primary: '#102033',
        secondary: '#5d6b7e',
        success: '#22c55e',
        error: '#ef4444',
        border: '#d7e4ef',
        borderHover: '#38bdf8',
        muted: '#eaf4fb',
        buttonPrimary: '#0ea5e9',
        buttonPrimaryHover: '#0284c7',
        buttonTextPrimary: '#ffffff',
        buttonTextSecondary: '#123047',
        inputBg: '#ffffff',
        table: '#f1f7fb',
        progress: '#0ea5e9',
        sidebarBg: '#0b3554',
        headerBg: '#dff3ff',
        surfaceTint: '#e7f6ff',
    },
    dark: {
        bg: '#0c1016',
        card: '#121821',
        primary: '#f4f7fb',
        secondary: '#aab6c5',
        success: '#34d399',
        error: '#fb7185',
        border: '#263241',
        borderHover: '#60a5fa',
        muted: '#1d2633',
        buttonPrimary: '#f4f7fb',
        buttonPrimaryHover: '#c7d2fe',
        buttonTextPrimary: '#0c1016',
        buttonTextSecondary: '#f4f7fb',
        inputBg: '#0f1520',
        table: '#151b23',
        progress: '#60a5fa',
        sidebarBg: '#121821',
        headerBg: '#121821',
        surfaceTint: '#1d2633',
    },
    womanly: {
        bg: '#fff7fb',
        card: '#ffffff',
        primary: '#43223b',
        secondary: '#8a5b7d',
        success: '#16a34a',
        error: '#e11d48',
        border: '#f1cfe0',
        borderHover: '#db2777',
        muted: '#fde8f2',
        buttonPrimary: '#be185d',
        buttonPrimaryHover: '#9d174d',
        buttonTextPrimary: '#ffffff',
        buttonTextSecondary: '#5c294f',
        inputBg: '#fffafd',
        table: '#fff0f7',
        progress: '#db2777',
        sidebarBg: '#43223b',
        headerBg: '#fde8f2',
        surfaceTint: '#fff0f7',
    },
    champion: {
        bg: '#fffaf0',
        card: '#ffffff',
        primary: '#2f2412',
        secondary: '#7a5a22',
        success: '#16a34a',
        error: '#dc2626',
        border: '#f2d89b',
        borderHover: '#d97706',
        muted: '#fff1c2',
        buttonPrimary: '#b45309',
        buttonPrimaryHover: '#92400e',
        buttonTextPrimary: '#ffffff',
        buttonTextSecondary: '#4a3412',
        inputBg: '#fffdf7',
        table: '#fff6d8',
        progress: '#d97706',
        sidebarBg: '#2f2412',
        headerBg: '#fff1c2',
        surfaceTint: '#fff6d8',
    },
    sprint: {
        bg: '#f5fff9',
        card: '#ffffff',
        primary: '#0b2f24',
        secondary: '#437063',
        success: '#059669',
        error: '#e11d48',
        border: '#bfe8d8',
        borderHover: '#10b981',
        muted: '#dcfce7',
        buttonPrimary: '#059669',
        buttonPrimaryHover: '#047857',
        buttonTextPrimary: '#ffffff',
        buttonTextSecondary: '#0f3f31',
        inputBg: '#fbfffd',
        table: '#ecfdf5',
        progress: '#10b981',
        sidebarBg: '#0b2f24',
        headerBg: '#dcfce7',
        surfaceTint: '#ecfdf5',
    },
    arena: {
        bg: '#f8fafc',
        card: '#ffffff',
        primary: '#1e293b',
        secondary: '#64748b',
        success: '#16a34a',
        error: '#dc2626',
        border: '#cbd5e1',
        borderHover: '#475569',
        muted: '#e2e8f0',
        buttonPrimary: '#334155',
        buttonPrimaryHover: '#1e293b',
        buttonTextPrimary: '#ffffff',
        buttonTextSecondary: '#334155',
        inputBg: '#ffffff',
        table: '#f1f5f9',
        progress: '#64748b',
        sidebarBg: '#1e293b',
        headerBg: '#e2e8f0',
        surfaceTint: '#f1f5f9',
    },
    pulse: {
        bg: '#fff7ed',
        card: '#ffffff',
        primary: '#3b1d0f',
        secondary: '#8a4b2a',
        success: '#16a34a',
        error: '#dc2626',
        border: '#fed7aa',
        borderHover: '#f97316',
        muted: '#ffedd5',
        buttonPrimary: '#ea580c',
        buttonPrimaryHover: '#c2410c',
        buttonTextPrimary: '#ffffff',
        buttonTextSecondary: '#5f2f17',
        inputBg: '#fffaf5',
        table: '#fff3e0',
        progress: '#f97316',
        sidebarBg: '#3b1d0f',
        headerBg: '#ffedd5',
        surfaceTint: '#fff3e0',
    },
    trail: {
        bg: '#f6f8f2',
        card: '#ffffff',
        primary: '#24301b',
        secondary: '#657252',
        success: '#15803d',
        error: '#dc2626',
        border: '#d6dfc6',
        borderHover: '#65a30d',
        muted: '#edf5df',
        buttonPrimary: '#4d7c0f',
        buttonPrimaryHover: '#3f6212',
        buttonTextPrimary: '#ffffff',
        buttonTextSecondary: '#31411f',
        inputBg: '#fbfdf7',
        table: '#eef6e2',
        progress: '#65a30d',
        sidebarBg: '#24301b',
        headerBg: '#edf5df',
        surfaceTint: '#eef6e2',
    },
    bazaar: {
        bg: '#e7f8fb',
        card: '#ffffff',
        primary: '#172033',
        secondary: '#667085',
        success: '#16a34a',
        error: '#dc2626',
        border: '#b8e3ea',
        borderHover: '#ff8a00',
        muted: '#fff3df',
        buttonPrimary: '#ff8a00',
        buttonPrimaryHover: '#e67600',
        buttonTextPrimary: '#ffffff',
        buttonTextSecondary: '#172033',
        inputBg: '#ffffff',
        table: '#f4fbfc',
        progress: '#00a8c6',
        sidebarBg: '#172033',
        headerBg: '#c8eef4',
        surfaceTint: '#f4fbfc',
    },
    vital: {
        bg: '#FFFFFF',
        card: '#FFFFFF',
        primary: '#262827',
        secondary: '#5D625F',
        success: '#6CC63A',
        error: '#dc2626',
        border: '#D7DED7',
        borderHover: '#6CC63A',
        muted: '#EDEDED',
        buttonPrimary: '#6CC63A',
        buttonPrimaryHover: '#58A932',
        buttonTextPrimary: '#151515',
        buttonTextSecondary: '#262827',
        inputBg: '#FFFFFF',
        table: '#F6F8F5',
        progress: '#8ED34A',
        sidebarBg: '#262827',
        headerBg: '#EDEDED',
        surfaceTint: '#F6F8F5',
    },
};

function applyThemeVariables(theme) {
    const variables = themeVariables[theme] || themeVariables.air;

    Object.entries(variables).forEach(([key, value]) => {
        document.documentElement.style.setProperty(`--${key}`, value);
    });
}

function setTheme(theme = 'air') {
    const selectedTheme = themeNames.includes(theme) ? theme : 'air';

    document.documentElement.classList.remove(...themeClasses, 'dark');
    [...document.documentElement.classList]
        .filter((className) => className.startsWith('theme-'))
        .forEach((className) => document.documentElement.classList.remove(className));

    document.documentElement.classList.add(`theme-${selectedTheme}`);
    document.documentElement.dataset.theme = selectedTheme;
    applyThemeVariables(selectedTheme);

    if (selectedTheme === 'dark') {
        document.documentElement.classList.add('dark');
    }

    localStorage.theme = selectedTheme;
}

function setThemeOnLoad(theme) {
    const serverTheme = theme || document.documentElement.className.match(/theme-([a-z]+)/)?.[1];
    setTheme(serverTheme || localStorage.getItem('theme') || 'air');
}

function switchTheme() {
    const currentTheme = localStorage.getItem('theme') || 'air';
    const currentIndex = themeNames.indexOf(currentTheme);
    const nextTheme = themeNames[(currentIndex + 1) % themeNames.length];

    setTheme(nextTheme);

    return nextTheme;
}

export { themeNames, themeVariables, setTheme, setThemeOnLoad, switchTheme };
