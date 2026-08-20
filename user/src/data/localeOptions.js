export const LOCALE_STORAGE_KEY = 'electava-locale';
export const LOCALE_CHANGE_EVENT = 'electava-locale-change';
export const DEFAULT_LOCALE_ID = 'en-us';

export const localeOptions = [
  {
    id: 'en-us',
    label: 'English (USD)',
    language: 'English',
    region: 'United States',
    currency: 'USD',
    description: 'Best for global buyers viewing product pricing in US dollars.',
  },
  {
    id: 'en-in',
    label: 'English (INR)',
    language: 'English',
    region: 'India',
    currency: 'INR',
    description: 'Ideal for teams that prefer English content with Indian pricing context.',
  },
  {
    id: 'hi-in',
    label: 'Hindi (INR)',
    language: 'Hindi',
    region: 'India',
    currency: 'INR',
    description: 'Useful for India-based users who want a Hindi-first preference saved.',
  },
  {
    id: 'de-de',
    label: 'German (EUR)',
    language: 'German',
    region: 'Germany',
    currency: 'EUR',
    description: 'A good choice for teams sourcing across the European market.',
  },
  {
    id: 'fr-fr',
    label: 'French (EUR)',
    language: 'French',
    region: 'France',
    currency: 'EUR',
    description: 'Helpful for French-speaking buyers and procurement teams.',
  },
  {
    id: 'ja-jp',
    label: 'Japanese (JPY)',
    language: 'Japanese',
    region: 'Japan',
    currency: 'JPY',
    description: 'Useful for hardware teams working with Japan-based suppliers and pricing.',
  },
];

export function getLocaleById(localeId) {
  return localeOptions.find(option => option.id === localeId) ?? localeOptions[0];
}
