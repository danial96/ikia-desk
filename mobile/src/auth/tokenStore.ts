import * as SecureStore from 'expo-secure-store';

import type { UserPayload } from '@/api/types';

// Keychain (iOS) / Keystore (Android). AFTER_FIRST_UNLOCK so a push that arrives while the phone is locked can
// still read the token.
const OPTIONS: SecureStore.SecureStoreOptions = { keychainAccessible: SecureStore.AFTER_FIRST_UNLOCK };
const TOKEN_KEY = 'ikia.token';
const USER_KEY = 'ikia.user';

export async function getToken(): Promise<string | null> {
  try {
    return await SecureStore.getItemAsync(TOKEN_KEY, OPTIONS);
  } catch {
    return null;
  }
}

/** The last known profile, so the app opens straight into its screens even with no network. */
export async function getUser(): Promise<UserPayload | null> {
  try {
    const raw = await SecureStore.getItemAsync(USER_KEY, OPTIONS);
    return raw ? (JSON.parse(raw) as UserPayload) : null;
  } catch {
    return null;
  }
}

export async function saveSession(token: string, user: UserPayload) {
  await SecureStore.setItemAsync(TOKEN_KEY, token, OPTIONS);
  await saveUser(user);
}

export async function saveUser(user: UserPayload) {
  await SecureStore.setItemAsync(USER_KEY, JSON.stringify(user), OPTIONS);
}

export async function clearSession() {
  await Promise.allSettled([
    SecureStore.deleteItemAsync(TOKEN_KEY, OPTIONS),
    SecureStore.deleteItemAsync(USER_KEY, OPTIONS),
  ]);
}
