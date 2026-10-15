import { useMemo, useState, type ReactNode } from 'react';
import { FlatList, Modal, Pressable, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Avatar } from './Avatar';
import { spacing, useColors } from '@/theme';

/** A panel that slides up from the bottom. Tapping outside closes it. */
export function Sheet({ visible, onClose, title, children }: { visible: boolean; onClose: () => void; title?: string; children: ReactNode }) {
  const c = useColors();
  const insets = useSafeAreaInsets();
  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose} statusBarTranslucent>
      <Pressable style={{ flex: 1, backgroundColor: 'rgba(0,0,0,0.45)', justifyContent: 'flex-end' }} onPress={onClose} accessibilityLabel="Close">
        {/* the inner Pressable swallows taps so touching the panel does not close it */}
        <Pressable
          onPress={() => {}}
          style={{
            backgroundColor: c.card,
            borderTopLeftRadius: 20,
            borderTopRightRadius: 20,
            maxHeight: '80%',
            paddingBottom: insets.bottom + spacing.sm,
          }}>
          <View style={{ alignSelf: 'center', width: 40, height: 4, borderRadius: 2, backgroundColor: c.border, marginVertical: spacing.sm }} />
          {title ? <Text style={{ color: c.text, fontSize: 17, fontWeight: '800', paddingHorizontal: spacing.md, paddingBottom: spacing.sm }}>{title}</Text> : null}
          {children}
        </Pressable>
      </Pressable>
    </Modal>
  );
}

export type SheetAction = { label: string; onPress: () => void; destructive?: boolean; selected?: boolean };

export function ActionSheet({ visible, onClose, title, actions }: { visible: boolean; onClose: () => void; title?: string; actions: SheetAction[] }) {
  const c = useColors();
  return (
    <Sheet visible={visible} onClose={onClose} title={title}>
      {actions.map((a) => (
        <Pressable
          key={a.label}
          accessibilityRole="button"
          onPress={() => {
            onClose();
            a.onPress();
          }}
          style={({ pressed }) => ({ paddingVertical: 14, paddingHorizontal: spacing.md, backgroundColor: pressed ? c.bg : 'transparent', flexDirection: 'row', justifyContent: 'space-between' })}>
          <Text style={{ color: a.destructive ? c.danger : c.text, fontSize: 16, fontWeight: a.selected ? '800' : '500' }}>{a.label}</Text>
          {a.selected ? <Text style={{ color: c.primary, fontSize: 16 }}>✓</Text> : null}
        </Pressable>
      ))}
      <Pressable accessibilityRole="button" onPress={onClose} style={{ paddingVertical: 14, paddingHorizontal: spacing.md }}>
        <Text style={{ color: c.subtext, fontSize: 16 }}>Cancel</Text>
      </Pressable>
    </Sheet>
  );
}

export type PickOption = { id: number; label: string; subtitle?: string | null; avatar?: string | null };

type PickerProps = {
  visible: boolean;
  onClose: () => void;
  title: string;
  options: PickOption[];
  /** Selected ids (one for single choice). */
  value: number[];
  onChange: (ids: number[]) => void;
  multi?: boolean;
  /** Single choice only: adds a first row that clears the choice. */
  clearLabel?: string;
};

/** Pick a person / project from a searchable list. Single choice closes on tap; multiple has a Done button. */
export function PickerSheet({ visible, onClose, title, options, value, onChange, multi, clearLabel }: PickerProps) {
  const c = useColors();
  const [q, setQ] = useState('');
  const shown = useMemo(() => {
    const t = q.trim().toLowerCase();
    return t ? options.filter((o) => o.label.toLowerCase().includes(t) || o.subtitle?.toLowerCase().includes(t)) : options;
  }, [options, q]);

  function pick(id: number | null) {
    if (multi) {
      if (id === null) return;
      onChange(value.includes(id) ? value.filter((v) => v !== id) : [...value, id]);
    } else {
      onChange(id === null ? [] : [id]);
      setQ('');
      onClose();
    }
  }

  const rows: (PickOption | null)[] = !multi && clearLabel ? [null, ...shown] : shown;

  return (
    <Sheet visible={visible} onClose={() => { setQ(''); onClose(); }} title={title}>
      <View style={{ paddingHorizontal: spacing.md, paddingBottom: spacing.sm }}>
        <TextInput
          value={q}
          onChangeText={setQ}
          placeholder="Search"
          placeholderTextColor={c.subtext}
          autoCapitalize="none"
          style={{ minHeight: 44, borderRadius: 12, borderWidth: 1, borderColor: c.border, paddingHorizontal: 12, color: c.text, backgroundColor: c.inputBg }}
        />
      </View>
      <FlatList
        data={rows}
        keyExtractor={(o) => (o ? String(o.id) : 'clear')}
        keyboardShouldPersistTaps="handled"
        style={{ flexGrow: 0 }}
        ListEmptyComponent={<Text style={{ color: c.subtext, padding: spacing.md }}>Nothing found.</Text>}
        renderItem={({ item }) => {
          const on = item ? value.includes(item.id) : value.length === 0;
          return (
            <Pressable
              accessibilityRole="button"
              accessibilityState={{ selected: on }}
              onPress={() => pick(item ? item.id : null)}
              style={({ pressed }) => ({ flexDirection: 'row', alignItems: 'center', gap: spacing.sm, paddingVertical: 10, paddingHorizontal: spacing.md, backgroundColor: pressed ? c.bg : 'transparent' })}>
              {item?.avatar !== undefined && item ? <Avatar uri={item.avatar} name={item.label} size={32} /> : null}
              <View style={{ flex: 1 }}>
                <Text style={{ color: item ? c.text : c.subtext, fontSize: 16, fontWeight: on ? '800' : '500' }}>{item ? item.label : clearLabel}</Text>
                {item?.subtitle ? <Text style={{ color: c.subtext, fontSize: 12 }}>{item.subtitle}</Text> : null}
              </View>
              {on ? <Text style={{ color: c.primary, fontSize: 18 }}>✓</Text> : null}
            </Pressable>
          );
        }}
      />
      {multi ? (
        <Pressable accessibilityRole="button" onPress={() => { setQ(''); onClose(); }} style={{ padding: spacing.md, alignItems: 'center' }}>
          <Text style={{ color: c.primary, fontSize: 16, fontWeight: '800' }}>Done ({value.length})</Text>
        </Pressable>
      ) : null}
    </Sheet>
  );
}
