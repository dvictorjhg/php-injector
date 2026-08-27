import translations from './translations.js';

const themeStorageKey = 'phpinjector-theme';
const languageStorageKey = 'phpinjector-language';
const defaultLanguage = 'en';
const supportedThemes = new Set(['light', 'dark']);
const supportedLanguages = new Set(Object.keys(translations));
const root = document.documentElement;

const readStorage = (key) => {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
};

const writeStorage = (key, value) => {
    try {
        localStorage.setItem(key, value);
    } catch {
    }
};

const applyTheme = (theme, persist = true) => {
    const nextTheme = supportedThemes.has(theme) ? theme : 'light';
    root.dataset.theme = nextTheme;

    document.querySelectorAll('[data-theme-choice]').forEach((button) => {
        button.setAttribute('aria-pressed', String(button.dataset.themeChoice === nextTheme));
    });

    if (persist) {
        writeStorage(themeStorageKey, nextTheme);
    }
};

const applyLanguage = (language, persist = true) => {
    const nextLanguage = supportedLanguages.has(language) ? language : defaultLanguage;
    const dictionary = translations[nextLanguage];

    root.lang = nextLanguage;
    document.title = dictionary['document.title'];

    const description = document.querySelector('meta[name="description"]');
    if (description) {
        description.setAttribute('content', dictionary['document.description']);
    }

    document.querySelectorAll('[data-i18n]').forEach((element) => {
        const value = dictionary[element.dataset.i18n];
        if (value !== undefined) {
            element.textContent = value;
        }
    });

    document.querySelectorAll('[data-i18n-html]').forEach((element) => {
        const value = dictionary[element.dataset.i18nHtml];
        if (value !== undefined) {
            element.innerHTML = value;
        }
    });

    document.querySelectorAll('[data-i18n-aria-label]').forEach((element) => {
        const value = dictionary[element.dataset.i18nAriaLabel];
        if (value !== undefined) {
            element.setAttribute('aria-label', value);
        }
    });

    const languageSelect = document.querySelector('#language-select');
    if (languageSelect) {
        languageSelect.value = nextLanguage;
    }

    if (persist) {
        writeStorage(languageStorageKey, nextLanguage);
    }
};

const initialize = () => {
    document.querySelectorAll('[data-theme-choice]').forEach((button) => {
        button.addEventListener('click', () => applyTheme(button.dataset.themeChoice));
    });

    document.querySelector('#language-select')?.addEventListener('change', (event) => {
        applyLanguage(event.currentTarget.value);
    });

    applyTheme(readStorage(themeStorageKey) ?? root.dataset.theme ?? 'light', false);
    applyLanguage(readStorage(languageStorageKey) ?? defaultLanguage, false);
};

applyTheme(readStorage(themeStorageKey) ?? 'light', false);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
} else {
    initialize();
}
