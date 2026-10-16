import { useEffect, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { useAudioPlayer, useAudioPlayerStatus } from 'expo-audio';
import { Ionicons } from '@expo/vector-icons';

import { fileHeaders, toApiFileUrl } from '@/api/client';
import { spacing, useColors } from '@/theme';

const clock = (s: number) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

/** A voice message. The player is only created when someone taps play, so a long chat does not open dozens of them. */
export function VoiceNote({ url, duration }: { url: string; duration: string | null }) {
  const c = useColors();
  const [started, setStarted] = useState(false);

  if (!started) {
    return (
      <Pressable accessibilityRole="button" accessibilityLabel="Play voice message" onPress={() => setStarted(true)} style={{ flexDirection: 'row', alignItems: 'center', gap: spacing.sm, minWidth: 160, padding: 6 }}>
        <Ionicons name="play-circle" size={34} color={c.primary} />
        <View style={{ flex: 1, height: 4, borderRadius: 2, backgroundColor: c.border }} />
        <Text style={{ color: c.subtext, fontSize: 12 }}>{duration ?? '0:00'}</Text>
      </Pressable>
    );
  }
  return <ActivePlayer url={url} duration={duration} />;
}

function ActivePlayer({ url, duration }: { url: string; duration: string | null }) {
  const c = useColors();
  const player = useAudioPlayer({ uri: toApiFileUrl(url), headers: fileHeaders() });
  const status = useAudioPlayerStatus(player);

  useEffect(() => {
    player.play();
  }, [player]);

  const total = status.duration || 0;
  const pct = total > 0 ? Math.min(1, status.currentTime / total) : 0;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={status.playing ? 'Pause voice message' : 'Play voice message'}
      onPress={() => {
        if (status.playing) player.pause();
        else {
          if (total > 0 && status.currentTime >= total - 0.2) player.seekTo(0);
          player.play();
        }
      }}
      style={{ flexDirection: 'row', alignItems: 'center', gap: spacing.sm, minWidth: 160, padding: 6 }}>
      <Ionicons name={status.playing ? 'pause-circle' : 'play-circle'} size={34} color={c.primary} />
      <View style={{ flex: 1, height: 4, borderRadius: 2, backgroundColor: c.border }}>
        <View style={{ width: `${pct * 100}%`, height: 4, borderRadius: 2, backgroundColor: c.primary }} />
      </View>
      <Text style={{ color: c.subtext, fontSize: 12 }}>{total > 0 ? clock(status.playing || status.currentTime > 0 ? status.currentTime : total) : (duration ?? '0:00')}</Text>
    </Pressable>
  );
}
