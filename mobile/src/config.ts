import Constants from 'expo-constants';

/**
 * Where the Desk server lives. Builds use app.json `extra.apiBaseUrl`; for local work put
 * EXPO_PUBLIC_API_URL=http://192.168.x.x:8000 in mobile/.env.local (a phone cannot reach "localhost").
 */
const configured =
  process.env.EXPO_PUBLIC_API_URL ??
  (Constants.expoConfig?.extra as { apiBaseUrl?: string } | undefined)?.apiBaseUrl ??
  'https://desk.ikiatech.com';

export const API_BASE_URL = configured.replace(/\/+$/, '');
export const API_URL = `${API_BASE_URL}/api/v1`;

/** Sent as X-App-Version so the server can tell an outdated build to update (HTTP 426). */
export const APP_VERSION = Constants.expoConfig?.version ?? '0.0.0';
