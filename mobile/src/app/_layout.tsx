import { useEffect } from 'react';
import { SplashScreen, Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { QueryClientProvider } from '@tanstack/react-query';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { SessionProvider, useSession } from '@/auth/session';
import { createQueryClient, wireQueryClientToDevice } from '@/lib/queryClient';

// Keep the splash up until we know whether someone is signed in (no flash of the login screen).
SplashScreen.preventAutoHideAsync();

const queryClient = createQueryClient();

export default function Root() {
  useEffect(() => wireQueryClientToDevice(), []);

  return (
    <SafeAreaProvider>
      <QueryClientProvider client={queryClient}>
        <SessionProvider>
          <Navigator />
        </SessionProvider>
      </QueryClientProvider>
    </SafeAreaProvider>
  );
}

function Navigator() {
  const { status, updateInfo } = useSession();

  useEffect(() => {
    if (status !== 'loading') SplashScreen.hide();
  }, [status]);

  const blocked = !!updateInfo;
  return (
    <>
      <StatusBar style="auto" />
      <Stack screenOptions={{ headerShown: false }}>
        <Stack.Protected guard={blocked}>
          <Stack.Screen name="update-required" />
        </Stack.Protected>
        <Stack.Protected guard={!blocked && status === 'signedOut'}>
          <Stack.Screen name="sign-in" />
        </Stack.Protected>
        <Stack.Protected guard={!blocked && status === 'signedIn'}>
          <Stack.Screen name="(app)" />
        </Stack.Protected>
      </Stack>
    </>
  );
}
