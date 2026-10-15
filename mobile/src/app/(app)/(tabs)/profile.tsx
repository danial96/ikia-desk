import { useState } from 'react';
import { Alert, Image, ScrollView, Text, View } from 'react-native';

import { useSession } from '@/auth/session';
import { Button, Screen } from '@/components/ui';
import { API_BASE_URL, APP_VERSION } from '@/config';
import { spacing, useColors } from '@/theme';

export default function Profile() {
  const c = useColors();
  const { user, signOut } = useSession();
  const [busy, setBusy] = useState(false);

  async function confirmSignOut() {
    Alert.alert('Sign out?', 'You will need to sign in again on this phone.', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Sign out',
        style: 'destructive',
        onPress: async () => {
          setBusy(true);
          try {
            await signOut();
          } finally {
            setBusy(false);
          }
        },
      },
    ]);
  }

  if (!user) return <Screen><View /></Screen>;

  return (
    <Screen>
      <ScrollView contentContainerStyle={{ padding: spacing.md, gap: spacing.md }}>
        <View style={{ alignItems: 'center', gap: spacing.sm, paddingVertical: spacing.md }}>
          <Image source={{ uri: user.avatar }} style={{ width: 96, height: 96, borderRadius: 48, backgroundColor: c.border }} />
          <Text style={{ color: c.text, fontSize: 22, fontWeight: '800' }}>{user.name}</Text>
          {user.position ? <Text style={{ color: c.subtext, fontSize: 14 }}>{user.position}</Text> : null}
        </View>

        <View style={{ backgroundColor: c.card, borderRadius: 14, padding: spacing.md, gap: spacing.sm, borderWidth: 1, borderColor: c.border }}>
          <Row label="Email" value={user.email} />
          {user.phone ? <Row label="Phone" value={user.phone} /> : null}
          <Row label="Role" value={user.role.replace(/_/g, ' ')} />
          <Row label="Time zone" value={user.time_zone} />
        </View>

        <Button title="Sign out" onPress={confirmSignOut} variant="danger" loading={busy} />

        <Text style={{ color: c.subtext, fontSize: 12, textAlign: 'center' }}>
          IKIA Desk {APP_VERSION} · {API_BASE_URL.replace(/^https?:\/\//, '')}
        </Text>
      </ScrollView>
    </Screen>
  );

  function Row({ label, value }: { label: string; value: string }) {
    return (
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: spacing.md }}>
        <Text style={{ color: c.subtext, fontSize: 14 }}>{label}</Text>
        <Text style={{ color: c.text, fontSize: 14, flexShrink: 1, textAlign: 'right', textTransform: label === 'Role' ? 'capitalize' : 'none' }}>{value}</Text>
      </View>
    );
  }
}
