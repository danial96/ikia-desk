import { Pressable, Text, View } from 'react-native';

import type { ActivityItem, CommentItem } from '@/api/types';
import { Avatar } from '@/components/Avatar';
import { RichText } from '@/components/RichText';
import { activityText } from '@/lib/activity';
import { plainText } from '@/lib/richText';
import { formatShort } from '@/lib/time';
import { spacing, useColors } from '@/theme';

type Props = {
  item: CommentItem;
  meId: number;
  timeZone: string;
  onReply: () => void;
  onReact: (emoji: string) => void;
  onMore: () => void;
};

export function CommentView({ item, meId, timeZone, onReply, onReact, onMore }: Props) {
  const c = useColors();

  if (item.isSystem) {
    return (
      <View style={{ paddingVertical: 4, paddingHorizontal: spacing.md }}>
        <Text style={{ color: c.subtext, fontSize: 13, fontStyle: 'italic', textAlign: 'center' }}>{plainText(item.text)}</Text>
      </View>
    );
  }

  const emojis = Object.entries(item.reactions ?? {});
  return (
    <Pressable
      onLongPress={onMore}
      delayLongPress={300}
      accessibilityActions={[{ name: 'more', label: 'More actions' }]}
      onAccessibilityAction={onMore}
      style={{ flexDirection: 'row', gap: spacing.sm, paddingHorizontal: spacing.md, paddingVertical: spacing.sm }}>
      <Avatar uri={item.author.avatar} name={item.author.name} size={34} />
      <View style={{ flex: 1, gap: 4 }}>
        <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: spacing.sm }}>
          <Text style={{ color: c.text, fontSize: 14, fontWeight: '800', flexShrink: 1 }} numberOfLines={1}>
            {item.author.name}
          </Text>
          <Text style={{ color: c.subtext, fontSize: 12 }}>
            {formatShort(item.at, timeZone)}
            {item.editedAt ? ' · edited' : ''}
          </Text>
        </View>

        {item.parentPreview ? (
          <View style={{ borderLeftWidth: 3, borderLeftColor: c.primary, paddingLeft: 8 }}>
            <Text style={{ color: c.primary, fontSize: 12, fontWeight: '700' }}>{item.parentPreview.author}</Text>
            <Text style={{ color: c.subtext, fontSize: 13 }} numberOfLines={2}>
              {item.parentPreview.text}
            </Text>
          </View>
        ) : null}

        <RichText text={item.text} />

        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6, alignItems: 'center', marginTop: 2 }}>
          {emojis.map(([emoji, ids]) => {
            const mine = ids.includes(meId);
            return (
              <Pressable
                key={emoji}
                accessibilityRole="button"
                accessibilityLabel={`${emoji} ${ids.length}${mine ? ', you reacted' : ''}`}
                onPress={() => onReact(emoji)}
                style={{ flexDirection: 'row', gap: 4, paddingVertical: 3, paddingHorizontal: 8, borderRadius: 12, borderWidth: 1, borderColor: mine ? c.primary : c.border, backgroundColor: mine ? c.bg : c.card }}>
                <Text style={{ fontSize: 13 }}>{emoji}</Text>
                <Text style={{ fontSize: 12, color: c.text, fontWeight: '700' }}>{ids.length}</Text>
              </Pressable>
            );
          })}
          <Pressable accessibilityRole="button" onPress={onReply} hitSlop={8}>
            <Text style={{ color: c.primary, fontSize: 13, fontWeight: '700' }}>Reply</Text>
          </Pressable>
          <Pressable accessibilityRole="button" accessibilityLabel="More actions" onPress={onMore} hitSlop={8}>
            <Text style={{ color: c.subtext, fontSize: 16, fontWeight: '800' }}>⋯</Text>
          </Pressable>
        </View>
      </View>
    </Pressable>
  );
}

export function ActivityRow({ item, timeZone }: { item: ActivityItem; timeZone: string }) {
  const c = useColors();
  return (
    <View style={{ paddingVertical: 3, paddingHorizontal: spacing.md }}>
      <Text style={{ color: c.subtext, fontSize: 12 }}>
        {activityText(item, timeZone)} · {formatShort(item.at, timeZone)}
      </Text>
    </View>
  );
}
