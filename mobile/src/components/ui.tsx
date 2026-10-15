import { forwardRef, type ReactNode } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
  type StyleProp,
  type TextInputProps,
  type ViewStyle,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNetInfo } from '@react-native-community/netinfo';

import { spacing, useColors } from '@/theme';

export function Screen({ children, style }: { children: ReactNode; style?: StyleProp<ViewStyle> }) {
  const c = useColors();
  return (
    <SafeAreaView edges={['top']} style={[{ flex: 1, backgroundColor: c.bg }, style]}>
      <OfflineBanner />
      {children}
    </SafeAreaView>
  );
}

export function OfflineBanner() {
  const c = useColors();
  const net = useNetInfo();
  if (net.isConnected !== false && net.isInternetReachable !== false) return null;
  return (
    <View style={[styles.banner, { backgroundColor: c.warning }]} accessibilityRole="alert">
      <Text style={styles.bannerText}>You are offline. Showing what was loaded last.</Text>
    </View>
  );
}

type ButtonProps = {
  title: string;
  onPress: () => void;
  loading?: boolean;
  disabled?: boolean;
  variant?: 'primary' | 'secondary' | 'danger';
  style?: StyleProp<ViewStyle>;
};

export function Button({ title, onPress, loading, disabled, variant = 'primary', style }: ButtonProps) {
  const c = useColors();
  const off = disabled || loading;
  const bg = variant === 'primary' ? c.primary : variant === 'danger' ? c.danger : 'transparent';
  const fg = variant === 'secondary' ? c.primary : variant === 'danger' ? '#ffffff' : c.onPrimary;
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: !!off, busy: !!loading }}
      disabled={off}
      onPress={onPress}
      style={({ pressed }) => [
        styles.button,
        { backgroundColor: bg, borderColor: variant === 'secondary' ? c.primary : bg, opacity: off ? 0.55 : pressed ? 0.85 : 1 },
        style,
      ]}>
      {loading ? <ActivityIndicator color={fg} /> : <Text style={[styles.buttonText, { color: fg }]}>{title}</Text>}
    </Pressable>
  );
}

export const TextField = forwardRef<TextInput, TextInputProps & { label: string; error?: string | null }>(function TextField(
  { label, error, style, ...rest },
  ref,
) {
  const c = useColors();
  return (
    <View style={{ gap: spacing.xs }}>
      <Text style={{ color: c.subtext, fontSize: 13, fontWeight: '600' }}>{label}</Text>
      <TextInput
        ref={ref}
        placeholderTextColor={c.subtext}
        accessibilityLabel={label}
        style={[
          styles.input,
          { backgroundColor: c.inputBg, color: c.text, borderColor: error ? c.danger : c.border },
          style,
        ]}
        {...rest}
      />
      {error ? <Text style={{ color: c.danger, fontSize: 13 }}>{error}</Text> : null}
    </View>
  );
});

export function Loading() {
  const c = useColors();
  return (
    <View style={styles.center}>
      <ActivityIndicator size="large" color={c.primary} />
    </View>
  );
}

export function Message({
  title,
  detail,
  actionTitle,
  onAction,
}: {
  title: string;
  detail?: string;
  actionTitle?: string;
  onAction?: () => void;
}) {
  const c = useColors();
  return (
    <View style={[styles.center, { gap: spacing.sm, paddingHorizontal: spacing.lg }]}>
      <Text style={{ color: c.text, fontSize: 17, fontWeight: '700', textAlign: 'center' }}>{title}</Text>
      {detail ? <Text style={{ color: c.subtext, fontSize: 14, textAlign: 'center' }}>{detail}</Text> : null}
      {actionTitle && onAction ? <Button title={actionTitle} onPress={onAction} variant="secondary" style={{ marginTop: spacing.sm }} /> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  banner: { paddingVertical: 6, paddingHorizontal: spacing.md },
  bannerText: { color: '#1b1b1b', fontSize: 13, fontWeight: '600', textAlign: 'center' },
  button: { minHeight: 48, borderRadius: 12, borderWidth: 1, alignItems: 'center', justifyContent: 'center', paddingHorizontal: spacing.md },
  buttonText: { fontSize: 16, fontWeight: '700' },
  input: { minHeight: 48, borderRadius: 12, borderWidth: 1, paddingHorizontal: 14, fontSize: 16 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.lg },
});
