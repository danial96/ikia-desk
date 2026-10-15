import { Image, Text, View } from 'react-native';

import { useColors } from '@/theme';

const initials = (name: string) => {
  const words = name.trim().split(/\s+/).filter(Boolean);
  return ((words[0]?.[0] ?? '?') + (words.length > 1 ? words[words.length - 1][0] : '')).toUpperCase();
};

/** Round photo; the server already sends an initials picture for people without one, so this only covers a failed load. */
export function Avatar({ uri, name, size = 32 }: { uri?: string | null; name: string; size?: number }) {
  const c = useColors();
  if (uri) {
    return <Image source={{ uri }} accessibilityLabel={name} style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: c.border }} />;
  }
  return (
    <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: c.primary, alignItems: 'center', justifyContent: 'center' }}>
      <Text style={{ color: c.onPrimary, fontSize: size * 0.4, fontWeight: '700' }}>{initials(name)}</Text>
    </View>
  );
}
