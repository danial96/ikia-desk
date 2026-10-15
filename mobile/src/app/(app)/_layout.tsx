import { Stack } from 'expo-router';

import { useColors } from '@/theme';

/** The signed-in app: the tab bar, with task pages and forms pushed on top of it. */
export default function AppStack() {
  const c = useColors();
  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: c.card },
        headerTintColor: c.primary,
        headerTitleStyle: { color: c.text },
        contentStyle: { backgroundColor: c.bg },
      }}>
      <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
      <Stack.Screen name="task/[id]/index" options={{ title: 'Task' }} />
      <Stack.Screen name="task/[id]/edit" options={{ title: 'Edit task', presentation: 'modal' }} />
      <Stack.Screen name="task/new" options={{ title: 'New task', presentation: 'modal' }} />
      <Stack.Screen name="trash" options={{ title: 'Trash' }} />
    </Stack>
  );
}
