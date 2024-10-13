import { createI18n } from 'vue-i18n';

// Sprachdateien importieren (falls du JSON-Dateien verwendest)
import en from './locales/en.json';
import de from './locales/de.json';
import fr from './locales/fr.json';

// I18n-Instanz erstellen
const i18n = createI18n({
    legacy: false, // Legacy Mode deaktivieren

  locale: 'de', // Standardsprache
  fallbackLocale: 'de', // Fallback-Sprache
  messages: {
    en, // Englisch
    de, // Deutsch
    fr, // Französisch
  },
});

export default i18n;
