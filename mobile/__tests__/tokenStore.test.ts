import * as SecureStore from 'expo-secure-store';

import type { UserPayload } from '@/api/types';
import * as tokenStore from '@/auth/tokenStore';

jest.mock('expo-secure-store', () => {
  const mem = new Map<string, string>();
  return {
    AFTER_FIRST_UNLOCK: 'AFTER_FIRST_UNLOCK',
    getItemAsync: jest.fn(async (k: string) => mem.get(k) ?? null),
    setItemAsync: jest.fn(async (k: string, v: string) => void mem.set(k, v)),
    deleteItemAsync: jest.fn(async (k: string) => void mem.delete(k)),
    __mem: mem,
  };
});

const user = { id: 1, name: 'A', email: 'a@example.com' } as UserPayload;

beforeEach(() => (SecureStore as any).__mem.clear());

describe('tokenStore', () => {
  it('keeps the token and profile in the secure store, readable after a restart', async () => {
    await tokenStore.saveSession('secret-token', user);

    expect(await tokenStore.getToken()).toBe('secret-token');
    expect(await tokenStore.getUser()).toEqual(user);
    expect(SecureStore.setItemAsync).toHaveBeenCalledWith('ikia.token', 'secret-token', { keychainAccessible: 'AFTER_FIRST_UNLOCK' });
  });

  it('clearSession forgets both', async () => {
    await tokenStore.saveSession('t', user);

    await tokenStore.clearSession();

    expect(await tokenStore.getToken()).toBeNull();
    expect(await tokenStore.getUser()).toBeNull();
  });

  it('reads of a broken or unavailable store come back empty instead of crashing', async () => {
    (SecureStore.getItemAsync as jest.Mock).mockRejectedValueOnce(new Error('keystore locked'));
    expect(await tokenStore.getToken()).toBeNull();

    (SecureStore.getItemAsync as jest.Mock).mockResolvedValueOnce('{not json');
    expect(await tokenStore.getUser()).toBeNull();
  });
});
