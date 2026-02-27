import { createI18n } from 'vue-i18n';
import en from './en.json';
import es from './es.json';

const savedLocale = typeof localStorage !== 'undefined'
    ? localStorage.getItem('locale') || 'es'
    : 'es';

const i18n = createI18n({
    legacy: false,
    locale: savedLocale,
    fallbackLocale: 'en',
    messages: { en, es },
});

export default i18n;
