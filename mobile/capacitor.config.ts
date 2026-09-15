import type { CapacitorConfig } from '@capacitor/cli';

const appUrl = process.env.CRM_APP_URL || 'https://crm.erlanpro.kg';

const config: CapacitorConfig = {
  appId: 'kg.erlanpro.crm.messenger',
  appName: 'ErlanPro Messenger',
  webDir: 'www',
  server: {
    // Live CRM — only messenger entry (compact UI + login).
    url: `${appUrl.replace(/\/$/, '')}/app`,
    cleartext: true,
    allowNavigation: [
      'crm.erlanpro.kg',
      'localhost',
      '127.0.0.1',
    ],
  },
  plugins: {
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert'],
    },
    SplashScreen: {
      launchAutoHide: true,
      backgroundColor: '#0f172a',
    },
  },
  android: {
    allowMixedContent: true,
  },
};

export default config;
