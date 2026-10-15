import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

import type { UserPayload } from '@/api/types';

// Keychain (iOS) / Keystore (Android). AFTER_FIRST_UNLOCK so a push that arrives while the phone is locked can
// still read the token.
const OPTIONS: SecureStore.SecureStoreOptions = { keychainAccessible: SecureStore.AFTER_FIRST_UNLOCK };
const TOKEN_KEY = 'ikia.token';
const USER_KEY = 'ikia.user';

// A browser has no Keychain: expo-secure-store does not run there. Web is only used for development, where
// localStorage stands in. Phones always use the secure store.
const get = (key: string) => (Platform.OS === 'web' ? Promise.resolve(globalThis.localStorage?.getItem(key) ?? null) : SecureStore.getItemAsync(key, OPTIONS));
const set = (key: string, value: string) =>
  Platform.OS === 'web' ? Promise.resolve(void globalThis.localStorage?.setItem(key, value)) : SecureStore.setItemAsync(key, value, OPTIONS);
const del = (key: string) => (Platform.OS === 'web' ? Promise.resolve(void globalThis.localStorage?.removeItem(key)) : SecureStore.deleteItemAsync(key, OPTIONS));

export async function getToken(): Promise<string | null> {
  try {
    return await get(TOKEN_KEY);
  } catch {
    return null;
  }
}

/** The last known profile, so the app opens straight into its screens even with no network. */
export async function getUser(): Promise<UserPayload | null> {
  try {
    const raw = await get(USER_KEY);
    return raw ? (JSON.parse(raw) as UserPayload) : null;
  } catch {
    return null;
  }
}

export async function saveSession(token: string, user: UserPayload) {
  await set(TOKEN_KEY, token);
  await saveUser(user);
}

export async function saveUser(user: UserPayload) {
  await set(USER_KEY, JSON.stringify(user));
}

export async function clearSession() {
  await Promise.allSettled([
    del(TOKEN_KEY),
    del(USER_KEY),
  ]);
}
