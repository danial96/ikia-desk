import { useEffect, useState } from 'react';
import { Modal, Pressable, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';

import { Avatar } from '@/components/Avatar';
import { Backdrop } from '@/components/Backdrop';
import { spacing } from '@/theme';
import { callEngine, useCall } from './index';

const clock = (s: number) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;

/** The full-screen call: ringing in, ringing out, talking. Sits on top of whatever screen the person is on. */
export function CallOverlay() {
  const call = useCall();
  const [, force] = useState(0);

  // the running time: only while connected
  useEffect(() => {
    if (call.phase !== 'active' || !call.connectedAt) return;
    const t = setInterval(() => force((n) => n + 1), 500);
    return () => clearInterval(t);
  }, [call.phase, call.connectedAt]);

  if (call.phase === 'idle' || !call.peer) return null;

  const talking = call.phase === 'active' && call.connectedAt;
  const status =
    call.phase === 'incoming' ? 'Incoming voice call'
    : call.phase === 'outgoing' ? (call.message ?? 'Calling…')
    : call.phase === 'ended' ? (call.message ?? 'Call ended')
    : talking ? (call.message ?? clock(Math.max(0, Math.floor((Date.now() - call.connectedAt!) / 1000))))
    : (call.message ?? 'Connecting…');

  return (
    <Modal visible animationType="fade" onRequestClose={() => void callEngine.hangup()} statusBarTranslucent>
      <Backdrop>
        <SafeAreaView style={{ flex: 1, justifyContent: 'space-between', alignItems: 'center', padding: spacing.lg }}>
          <View style={{ alignItems: 'center', gap: spacing.md, marginTop: spacing.xl * 2 }}>
            <Avatar uri={call.peer.avatar} name={call.peer.name} size={120} />
            <Text style={{ color: '#fff', fontSize: 28, fontWeight: '800', textAlign: 'center' }} accessibilityRole="header">
              {call.peer.name}
            </Text>
            <Text style={{ color: 'rgba(255,255,255,0.8)', fontSize: 18 }} accessibilityLiveRegion="polite">
              {status}
            </Text>
          </View>

          {call.phase === 'incoming' ? (
            <View style={{ flexDirection: 'row', gap: spacing.xl * 2, marginBottom: spacing.xl }}>
              <RoundButton label="Decline" color="#d64545" icon="call" rotate onPress={() => void callEngine.decline()} />
              <RoundButton label="Answer" color="#2eb872" icon="call" onPress={() => void callEngine.accept()} />
            </View>
          ) : call.phase === 'ended' ? (
            <View style={{ marginBottom: spacing.xl }} />
          ) : (
            <View style={{ alignItems: 'center', gap: spacing.xl, marginBottom: spacing.xl }}>
              <View style={{ flexDirection: 'row', gap: spacing.xl * 1.5 }}>
                <RoundButton label={call.muted ? 'Unmute' : 'Mute'} color={call.muted ? '#ffffff' : 'rgba(255,255,255,0.18)'} dark={call.muted} icon={call.muted ? 'mic-off' : 'mic'} onPress={callEngine.toggleMute} small />
                <RoundButton label="Speaker" color={call.speaker ? '#ffffff' : 'rgba(255,255,255,0.18)'} dark={call.speaker} icon={call.speaker ? 'volume-high' : 'volume-medium'} onPress={callEngine.toggleSpeaker} small />
              </View>
              <RoundButton label="End call" color="#d64545" icon="call" rotate onPress={() => void callEngine.hangup()} />
            </View>
          )}
        </SafeAreaView>
      </Backdrop>
    </Modal>
  );
}

function RoundButton({ label, color, icon, onPress, rotate, small, dark }: { label: string; color: string; icon: React.ComponentProps<typeof Ionicons>['name']; onPress: () => void; rotate?: boolean; small?: boolean; dark?: boolean }) {
  const size = small ? 60 : 74;
  return (
    <View style={{ alignItems: 'center', gap: 8 }}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={label}
        onPress={onPress}
        style={({ pressed }) => ({ width: size, height: size, borderRadius: size / 2, backgroundColor: color, alignItems: 'center', justifyContent: 'center', opacity: pressed ? 0.8 : 1 })}>
        <Ionicons name={icon} size={small ? 26 : 32} color={dark ? '#04252b' : '#fff'} style={rotate ? { transform: [{ rotate: '135deg' }] } : undefined} />
      </Pressable>
      <Text style={{ color: 'rgba(255,255,255,0.85)', fontSize: 13 }}>{label}</Text>
    </View>
  );
}
