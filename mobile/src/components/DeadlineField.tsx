import { useState } from 'react';
import { Modal, Platform, Pressable, Text, TextInput, View } from 'react-native';
import DateTimePicker, { DateTimePickerAndroid } from '@react-native-community/datetimepicker';

import { carrierDate, nowWall, parseNaive, toNaive, wallFromCarrier, formatWall } from '@/lib/time';
import { spacing, useColors } from '@/theme';

type Props = {
  /** Wall-clock in the person's profile time zone ("2026-10-15T18:30"), or null for no deadline. */
  value: string | null;
  onChange: (naive: string | null) => void;
  timeZone: string;
  label?: string;
  disabled?: boolean;
};

/** Deadline picker using the phone's own date and time dialogs. Works on wall-clock text so the profile zone is never mixed up with the phone's. */
export function DeadlineField({ value, onChange, timeZone, label = 'Deadline', disabled }: Props) {
  const c = useColors();
  const [iosOpen, setIosOpen] = useState(false);
  const [iosValue, setIosValue] = useState<Date>(new Date());

  const wall = value ? parseNaive(value) : null;
  const shown = wall ? formatWall(wall) : 'No deadline';

  const commit = (d: Date) => onChange(toNaive(wallFromCarrier(d)));

  function open() {
    if (disabled) return;
    const start = carrierDate(wall ?? { ...nowWall(timeZone), h: 18, min: 0 });
    if (Platform.OS === 'android') {
      DateTimePickerAndroid.open({
        value: start,
        mode: 'date',
        onChange: (e, date) => {
          if (e.type !== 'set' || !date) return;
          DateTimePickerAndroid.open({
            value: date,
            mode: 'time',
            is24Hour: false,
            onChange: (e2, time) => {
              if (e2.type !== 'set' || !time) return;
              commit(new Date(date.getFullYear(), date.getMonth(), date.getDate(), time.getHours(), time.getMinutes()));
            },
          });
        },
      });
    } else {
      setIosValue(start);
      setIosOpen(true);
    }
  }

  return (
    <View style={{ gap: 4 }}>
      <Text style={{ color: c.subtext, fontSize: 13, fontWeight: '600' }}>{label}</Text>
      {Platform.OS === 'web' ? (
        // no native picker in a browser (development only): type "2026-10-15 18:30"
        <TextInput
          value={value ?? ''}
          onChangeText={(t) => {
            const w = parseNaive(t);
            onChange(w ? toNaive(w) : t || null);
          }}
          placeholder="YYYY-MM-DD HH:mm"
          placeholderTextColor={c.subtext}
          style={{ minHeight: 48, borderRadius: 12, borderWidth: 1, borderColor: c.border, paddingHorizontal: 14, color: c.text, backgroundColor: c.inputBg }}
        />
      ) : (
        <View style={{ flexDirection: 'row', gap: spacing.sm, alignItems: 'center' }}>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={`${label}: ${shown}`}
            onPress={open}
            disabled={disabled}
            style={{ flex: 1, minHeight: 48, borderRadius: 12, borderWidth: 1, borderColor: c.border, backgroundColor: c.inputBg, justifyContent: 'center', paddingHorizontal: 14, opacity: disabled ? 0.6 : 1 }}>
            <Text style={{ color: wall ? c.text : c.subtext, fontSize: 16 }}>{shown}</Text>
          </Pressable>
          {wall && !disabled ? (
            <Pressable accessibilityRole="button" accessibilityLabel="Clear deadline" onPress={() => onChange(null)} hitSlop={8}>
              <Text style={{ color: c.danger, fontSize: 14, fontWeight: '700' }}>Clear</Text>
            </Pressable>
          ) : null}
        </View>
      )}

      {Platform.OS === 'ios' ? (
        <Modal visible={iosOpen} transparent animationType="slide" onRequestClose={() => setIosOpen(false)}>
          <Pressable style={{ flex: 1, backgroundColor: 'rgba(0,0,0,0.45)', justifyContent: 'flex-end' }} onPress={() => setIosOpen(false)}>
            <Pressable onPress={() => {}} style={{ backgroundColor: c.card, padding: spacing.md, borderTopLeftRadius: 20, borderTopRightRadius: 20 }}>
              <DateTimePicker value={iosValue} mode="datetime" display="spinner" onChange={(_e, d) => d && setIosValue(d)} />
              <Pressable
                accessibilityRole="button"
                onPress={() => {
                  commit(iosValue);
                  setIosOpen(false);
                }}
                style={{ alignItems: 'center', padding: spacing.md }}>
                <Text style={{ color: c.primary, fontSize: 17, fontWeight: '800' }}>Done</Text>
              </Pressable>
            </Pressable>
          </Pressable>
        </Modal>
      ) : null}
    </View>
  );
}
