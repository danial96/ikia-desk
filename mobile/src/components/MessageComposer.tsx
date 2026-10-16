import { useState } from 'react';
import { ActivityIndicator, Alert, Platform, Pressable, Text, TextInput, View } from 'react-native';
import { AudioModule, RecordingPresets, setAudioModeAsync, useAudioRecorder, useAudioRecorderState } from 'expo-audio';
import { Ionicons } from '@expo/vector-icons';

import type { LocalFile } from '@/api/client';
import { AttachSheet } from '@/components/AttachSheet';
import { spacing, useColors } from '@/theme';

type Props = {
  draft: string;
  onChange: (t: string) => void;
  onSend: () => void;
  /** Sending text, or uploading attachments. */
  busy: boolean;
  placeholder: string;
  /** "Replying to Sara" / "Editing your message" */
  banner?: string | null;
  onCancelBanner?: () => void;
  onMention?: () => void;
  /** Photos/files chosen to attach. Omit to hide the paperclip. */
  onAttach?: (files: LocalFile[]) => void;
  /** A recorded voice message. Omit to hide the microphone. */
  onVoice?: (file: LocalFile, duration: string) => void;
  editing?: boolean;
};

const clock = (ms: number) => `${Math.floor(ms / 60000)}:${String(Math.floor((ms % 60000) / 1000)).padStart(2, '0')}`;

/** The box at the bottom of a chat or a task: text, paperclip, microphone, send. */
export function MessageComposer({ draft, onChange, onSend, busy, placeholder, banner, onCancelBanner, onMention, onAttach, onVoice, editing }: Props) {
  const c = useColors();
  const [attachOpen, setAttachOpen] = useState(false);
  const [recording, setRecording] = useState(false);
  const recorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const rec = useAudioRecorderState(recorder);
  const hasText = draft.trim().length > 0;
  const showMic = !!onVoice && !hasText && !editing && Platform.OS !== 'web';

  async function startRecording() {
    try {
      const perm = await AudioModule.requestRecordingPermissionsAsync();
      if (!perm.granted) {
        Alert.alert('Microphone', "Allow the microphone in the phone's Settings to send voice messages.");
        return;
      }
      await setAudioModeAsync({ allowsRecording: true, playsInSilentMode: true });
      await recorder.prepareToRecordAsync();
      recorder.record();
      setRecording(true);
    } catch (e) {
      Alert.alert('Could not record', e instanceof Error ? e.message : 'Try again.');
    }
  }

  async function finishRecording(send: boolean) {
    const ms = rec.durationMillis;
    setRecording(false);
    try {
      await recorder.stop();
      await setAudioModeAsync({ allowsRecording: false });
    } catch {
      /* nothing to stop */
    }
    const uri = recorder.uri;
    if (!send || !uri || ms < 700) return; // a tap, not a message
    onVoice?.({ uri, name: `voice-${Date.now()}.m4a`, mimeType: 'audio/mp4' }, clock(ms));
  }

  if (recording) {
    return (
      <View style={{ backgroundColor: c.card, borderTopWidth: 1, borderTopColor: c.border, padding: spacing.sm, flexDirection: 'row', alignItems: 'center', gap: spacing.md }}>
        <Pressable accessibilityRole="button" accessibilityLabel="Cancel recording" onPress={() => finishRecording(false)} hitSlop={8}>
          <Ionicons name="trash-outline" size={26} color={c.danger} />
        </Pressable>
        <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: c.danger }} />
        <Text style={{ flex: 1, color: c.text, fontSize: 16, fontWeight: '700' }}>Recording… {clock(rec.durationMillis)}</Text>
        <Pressable accessibilityRole="button" accessibilityLabel="Send voice message" onPress={() => finishRecording(true)} style={{ width: 42, height: 42, borderRadius: 21, backgroundColor: c.primary, alignItems: 'center', justifyContent: 'center' }}>
          <Ionicons name="send" size={20} color={c.onPrimary} />
        </Pressable>
      </View>
    );
  }

  return (
    <View style={{ backgroundColor: c.card, borderTopWidth: 1, borderTopColor: c.border, padding: spacing.sm, gap: 6 }}>
      {banner ? (
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', paddingHorizontal: 4 }}>
          <Text style={{ color: c.primary, fontSize: 12, fontWeight: '700', flexShrink: 1 }} numberOfLines={1}>
            {banner}
          </Text>
          <Pressable accessibilityRole="button" accessibilityLabel="Cancel" onPress={onCancelBanner} hitSlop={8}>
            <Text style={{ color: c.subtext, fontSize: 12 }}>✕ Cancel</Text>
          </Pressable>
        </View>
      ) : null}
      <View style={{ flexDirection: 'row', alignItems: 'flex-end', gap: spacing.sm }}>
        {onAttach && !editing ? (
          <Pressable accessibilityRole="button" accessibilityLabel="Attach" onPress={() => setAttachOpen(true)} hitSlop={6} style={{ paddingBottom: 10 }}>
            <Ionicons name="attach" size={26} color={c.primary} />
          </Pressable>
        ) : null}
        {onMention && !editing ? (
          <Pressable accessibilityRole="button" accessibilityLabel="Mention someone" onPress={onMention} hitSlop={6} style={{ paddingBottom: 8 }}>
            <Text style={{ color: c.primary, fontSize: 22, fontWeight: '800' }}>@</Text>
          </Pressable>
        ) : null}
        <TextInput
          value={draft}
          onChangeText={onChange}
          placeholder={placeholder}
          placeholderTextColor={c.subtext}
          multiline
          accessibilityLabel={placeholder}
          style={{ flex: 1, minHeight: 42, maxHeight: 120, borderRadius: 20, borderWidth: 1, borderColor: c.border, paddingHorizontal: 14, paddingTop: 10, paddingBottom: 10, color: c.text, backgroundColor: c.inputBg, fontSize: 15 }}
        />
        {showMic ? (
          <Pressable accessibilityRole="button" accessibilityLabel="Record a voice message" onPress={startRecording} disabled={busy} style={{ width: 42, height: 42, borderRadius: 21, backgroundColor: c.primary, alignItems: 'center', justifyContent: 'center' }}>
            {busy ? <ActivityIndicator color={c.onPrimary} /> : <Ionicons name="mic" size={22} color={c.onPrimary} />}
          </Pressable>
        ) : (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={editing ? 'Save' : 'Send'}
            accessibilityState={{ disabled: !hasText || busy }}
            onPress={onSend}
            disabled={!hasText || busy}
            style={{ width: 42, height: 42, borderRadius: 21, backgroundColor: hasText && !busy ? c.primary : c.border, alignItems: 'center', justifyContent: 'center' }}>
            {busy ? <ActivityIndicator color={c.onPrimary} /> : <Ionicons name={editing ? 'checkmark' : 'send'} size={20} color={c.onPrimary} />}
          </Pressable>
        )}
      </View>
      {onAttach ? <AttachSheet visible={attachOpen} onClose={() => setAttachOpen(false)} onPicked={onAttach} /> : null}
    </View>
  );
}
