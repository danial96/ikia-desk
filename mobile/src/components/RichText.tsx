import { memo, useMemo, useState } from 'react';
import { Alert, Image, Linking, Modal, Pressable, Text, View, type TextStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { API_URL } from '@/config';
import { fileHeaders, toApiFileUrl } from '@/api/client';
import { openFile } from '@/lib/files';
import { VoiceNote } from './VoiceNote';
import { parseRich, type Block, type Span } from '@/lib/richText';
import { spacing, useColors } from '@/theme';

const resolveDisk = (id: string) => `${API_URL}/disk-file/${id}`;

const safeLink = (href: string) => (/^(https?:|mailto:|tel:)/i.test(href) ? href : null);

type Props = { text: string | null | undefined; color?: string; size?: number; numberOfLines?: number };

/** Draws message / comment / description text: styled words, links, photos (tap to enlarge) and file chips (tap to open). */
export const RichText = memo(function RichText({ text, color, size = 15, numberOfLines }: Props) {
  const c = useColors();
  const blocks = useMemo(() => parseRich(text, resolveDisk), [text]);
  const [viewing, setViewing] = useState<string | null>(null);
  const ink = color ?? c.text;

  if (blocks.length === 0) return null;
  return (
    <View style={{ gap: spacing.sm }}>
      {blocks.map((b, i) => (
        <BlockView key={i} block={b} ink={ink} size={size} numberOfLines={numberOfLines} onImage={setViewing} />
      ))}
      <ImageViewer url={viewing} onClose={() => setViewing(null)} />
    </View>
  );
});

function BlockView({ block, ink, size, numberOfLines, onImage }: { block: Block; ink: string; size: number; numberOfLines?: number; onImage: (url: string) => void }) {
  const c = useColors();

  if (block.type === 'text') {
    return (
      <Text style={{ color: ink, fontSize: size, lineHeight: size * 1.4 }} numberOfLines={numberOfLines} selectable>
        {block.spans.map((s, i) => (
          <SpanView key={i} span={s} c={c} />
        ))}
      </Text>
    );
  }

  if (block.type === 'image') {
    return (
      <Pressable onPress={() => onImage(block.url)} accessibilityRole="imagebutton" accessibilityLabel="Photo">
        <Image
          source={{ uri: toApiFileUrl(block.url), headers: fileHeaders() }}
          style={{ width: 240, height: 180, borderRadius: 10, backgroundColor: c.border }}
          resizeMode="cover"
        />
      </Pressable>
    );
  }

  if (block.type === 'voice') return <VoiceNote url={block.url} duration={block.duration} />;

  const label = block.name;
  const icon = '📎';
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      onPress={() => openFile(block.url, block.name).catch((e) => Alert.alert('Could not open the file', e instanceof Error ? e.message : 'Try again.'))}
      style={({ pressed }) => ({
        flexDirection: 'row',
        alignItems: 'center',
        gap: spacing.sm,
        backgroundColor: c.bg,
        borderRadius: 10,
        borderWidth: 1,
        borderColor: c.border,
        padding: 10,
        opacity: pressed ? 0.7 : 1,
      })}>
      <Text style={{ fontSize: 18 }}>{icon}</Text>
      <Text style={{ color: c.text, fontSize: 14, fontWeight: '600', flexShrink: 1 }} numberOfLines={1}>
        {label}
      </Text>
    </Pressable>
  );
}

function SpanView({ span, c }: { span: Span; c: ReturnType<typeof useColors> }) {
  const style: TextStyle = {};
  if (span.bold) style.fontWeight = '700';
  if (span.italic) style.fontStyle = 'italic';
  if (span.mention) {
    style.color = c.primary;
    style.fontWeight = '700';
  }
  const href = span.link ? safeLink(span.link) : null;
  if (href) {
    return (
      <Text style={[style, { color: c.primary, textDecorationLine: 'underline' }]} onPress={() => Linking.openURL(href)}>
        {span.text}
      </Text>
    );
  }
  return <Text style={style}>{span.text}</Text>;
}

function ImageViewer({ url, onClose }: { url: string | null; onClose: () => void }) {
  const insets = useSafeAreaInsets();
  return (
    <Modal visible={!!url} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <Pressable style={{ flex: 1, backgroundColor: 'rgba(0,0,0,0.92)', justifyContent: 'center' }} onPress={onClose} accessibilityLabel="Close photo">
        {url ? <Image source={{ uri: toApiFileUrl(url), headers: fileHeaders() }} style={{ width: '100%', height: '80%' }} resizeMode="contain" /> : null}
        <Text style={{ position: 'absolute', top: insets.top + 12, right: 20, color: '#fff', fontSize: 28 }}>✕</Text>
      </Pressable>
    </Modal>
  );
}
