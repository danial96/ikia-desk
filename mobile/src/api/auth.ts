import { Platform } from 'react-native';
import * as Device from 'expo-device';

import { api } from './client';
import { APP_VERSION } from '@/config';
import type { AppConfig, UserPayload } from './types';

export async function login(email: string, password: string) {
  return api<{ token: string; user: UserPayload }>('auth/login', {
    method: 'POST',
    auth: false,
    body: {
      email: email.trim(),
      password,
      device_name: Device.deviceName ?? ([Device.brand, Device.modelName].filter(Boolean).join(' ') || 'Phone'),
      platform: Platform.OS === 'ios' ? 'ios' : 'android',
      app_version: APP_VERSION,
    },
  });
}

export async function fetchMe() {
  return (await api<{ user: UserPayload }>('auth/me')).user;
}

export async function logout() {
  return api<{ ok: boolean }>('auth/logout', { method: 'POST' });
}

export async function forgotPassword(email: string) {
  return api<{ ok: boolean; message: string }>('auth/forgot-password', {
    method: 'POST',
    auth: false,
    body: { email: email.trim() },
  });
}

export async function fetchAppConfig() {
  return api<AppConfig>('app/config', { auth: false });
}
