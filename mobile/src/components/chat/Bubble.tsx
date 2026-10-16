import { memo } from 'react';
import { Pressable, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import { tickState, type ChatMessage } from '@/api/chat';
import { Avatar } from '@/components/Avatar';
import { RichText } from '@/components/RichText';
import { formatShort } from '@/lib/time';
import { spacing, useColors } from '@/theme';

type Props = {
  msg: ChatMessage;
  meId: number;
  /** Show the sender's name and photo (groups only; in a direct chat it is always the other person). */
  showAuthor: boolean;
  direct: boolean;
  otherLastReadTs: number | null;
  otherLastSeenTs: number | null;
  timeZone: string;
  onLongPress: (m: ChatMessage) => void;
  onReact: (m: ChatMessage, emoji: string) => void;
  onRetry: (m: ChatMessage) => void;
};

export const Bubble = memo(function Bubble({ msg, meId, showAuthor, direct, otherLastReadTs, otherLastSeenTs, timeZone, onLongPress, onReact, onRetry }: Props) {
  const c = useColors();
  const mine = msg.isMine;
  const tick = tickState(msg, otherLastReadTs, otherLastSeenTs, direct);
  const bg = mine ? c.primary : c.card;
  const ink = mine ? c.onPrimary : c.text;
  const sub = mine ? 'rgba(255,255,255,0.75)' : c.subtext;
  const emojis = Object.entries(msg.reactions ?? {});
  const time = msg.local ? '' : formatShort(new Date(msg.createdTs * 1000).toISOString(), timeZone).split(', ')[1] ?? msg.time;

  return (
    <View style={{ flexDirection: 'row', justifyContent: mine ? 'flex-end' : 'flex-start', paddingHorizontal: spacing.sm, paddingVertical: 2, gap: 6 }}>
      {!mine && showAuthor ? <Avatar uri={msg.author.avatar} name={msg.author.name} size={28} /> : !mine && !direct ? <View style={{ width: 28 }} /> : null}
      <View style={{ maxWidth: '82%' }}>
        <Pressable
          onLongPress={() => (msg.local === 'failed' ? onRetry(msg) : onLongPress(msg))}
          onPress={msg.local === 'failed' ? () => onRetry(msg) : undefined}
          delayLongPress={300}
          accessibilityLabel={`${mine ? 'You' : msg.author.name}: ${msg.text.slice(0, 80)}`}
          accessibilityActions={[{ name: 'more', label: 'More actions' }]}
          onAccessibilityAction={() => onLongPress(msg)}
          style={{ backgroundColor: bg, borderRadius: 16, borderBottomRightRadius: mine ? 4 : 16, borderBottomLeftRadius: mine ? 16 : 4, paddingHorizontal: 12, paddingVertical: 8, gap: 4, borderWidth: mine ? 0 : 1, borderColor: c.border, opacity: msg.local === 'sending' ? 0.7 : 1 }}>
          {!mine && showAuthor ? (
            <Text style={{ color: c.primary, fontSize: 12, fontWeight: '800' }} numberOfLines={1}>
              {msg.author.name}
            </Text>
          ) : null}
          {msg.parentPreview ? (
            <View style={{ borderLeftWidth: 3, borderLeftColor: mine ? c.onPrimary : c.primary, paddingLeft: 8, opacity: 0.9 }}>
              <Text style={{ color: mine ? c.onPrimary : c.primary, fontSize: 12, fontWeight: '700' }}>{msg.parentPreview.author}</Text>
              <Text style={{ color: sub, fontSize: 13 }} numberOfLines={2}>
                {msg.parentPreview.text}
              </Text>
            </View>
          ) : null}
          <RichText text={msg.text} color={ink} />
          <View style={{ flexDirection: 'row', alignSelf: 'flex-end', alignItems: 'center', gap: 4 }}>
            {msg.editedAt ? <Text style={{ color: sub, fontSize: 11 }}>edited</Text> : null}
            <Text style={{ color: sub, fontSize: 11 }}>{time}</Text>
            {tick ? <Ticks state={tick} color={sub} /> : null}
          </View>
        </Pressable>
        {msg.local === 'failed' ? <Text style={{ color: c.danger, fontSize: 12, textAlign: 'right', marginTop: 2 }}>Not sent. Tap to retry.</Text> : null}
        {emojis.length > 0 ? (
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 4, marginTop: 3, justifyContent: mine ? 'flex-end' : 'flex-start' }}>
            {emojis.map(([emoji, ids]) => {
              const on = ids.includes(meId);
              return (
                <Pressable
                  key={emoji}
                  accessibilityRole="button"
                  accessibilityLabel={`${emoji} ${ids.length}${on ? ', you reacted' : ''}`}
                  onPress={() => onReact(msg, emoji)}
                  style={{ flexDirection: 'row', gap: 3, paddingVertical: 2, paddingHorizontal: 7, borderRadius: 11, borderWidth: 1, borderColor: on ? c.primary : c.border, backgroundColor: c.card }}>
                  <Text style={{ fontSize: 12 }}>{emoji}</Text>
                  <Text style={{ fontSize: 11, color: c.text, fontWeight: '700' }}>{ids.length}</Text>
                </Pressable>
              );
            })}
          </View>
        ) : null}
      </View>
    </View>
  );
});

function Ticks({ state, color }: { state: NonNullable<ReturnType<typeof tickState>>; color: string }) {
  if (state === 'sending') return <Ionicons name="time-outline" size={12} color={color} accessibilityLabel="Sending" />;
  if (state === 'failed') return <Ionicons name="alert-circle" size={13} color="#ff7b6b" accessibilityLabel="Failed" />;
  if (state === 'sent') return <Ionicons name="checkmark" size={14} color={color} accessibilityLabel="Sent" />;
  return <Ionicons name="checkmark-done" size={14} color={state === 'read' ? '#7fe3ff' : color} accessibilityLabel={state === 'read' ? 'Read' : 'Delivered'} />;
}
