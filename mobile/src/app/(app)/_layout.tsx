import type { ColorValue } from 'react-native';
import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

import { useNotifications } from '@/api/queries';
import { useColors } from '@/theme';

type IconName = React.ComponentProps<typeof Ionicons>['name'];

export default function AppTabs() {
  const c = useColors();
  const { data } = useNotifications();
  const unread = data?.unread_count ?? 0;

  const icon = (name: IconName, focused: IconName) => ({ color, focused: f, size }: { color: ColorValue; focused: boolean; size: number }) => (
    <Ionicons name={f ? focused : name} color={color} size={size} />
  );

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: c.primary,
        tabBarInactiveTintColor: c.subtext,
        tabBarStyle: { backgroundColor: c.card, borderTopColor: c.border },
      }}>
      <Tabs.Screen name="tasks" options={{ title: 'Tasks', tabBarIcon: icon('checkbox-outline', 'checkbox') }} />
      <Tabs.Screen name="messenger" options={{ title: 'Messenger', tabBarIcon: icon('chatbubble-ellipses-outline', 'chatbubble-ellipses') }} />
      <Tabs.Screen
        name="notifications"
        options={{
          title: 'Alerts',
          tabBarIcon: icon('notifications-outline', 'notifications'),
          tabBarBadge: unread > 0 ? (unread > 99 ? '99+' : unread) : undefined,
        }}
      />
      <Tabs.Screen name="profile" options={{ title: 'Profile', tabBarIcon: icon('person-circle-outline', 'person-circle') }} />
      <Tabs.Screen name="index" options={{ href: null }} />
    </Tabs>
  );
}
