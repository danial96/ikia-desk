import { useSyncExternalStore } from 'react';

import { api } from '@/api/client';
import { getSessionUserId } from '@/auth/sessionRef';
import { createCallEngine, type AudioRoute, type RtcLib } from './engine';

/**
 * react-native-webrtc and react-native-incall-manager are native modules: they exist in the installed app (a development
 * build or the .apk) but NOT in Expo Go. Loading them lazily and defensively keeps everything else working in Expo Go;
 * there the call button just says calls need the installed app.
 */
function loadRtc(): RtcLib | null {
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const m = require('react-native-webrtc');
    return m?.RTCPeerConnection && m?.mediaDevices ? (m as RtcLib) : null;
  } catch {
    return null;
  }
}

function loadAudio(): AudioRoute | null {
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const mod = require('react-native-incall-manager');
    const M = mod?.default ?? mod;
    if (!M?.start) return null;
    const safe = (fn: () => void) => {
      try {
        fn();
      } catch {
        /* audio routing is a nicety; the call itself must not die from it */
      }
    };
    return {
      start: (ringback) => safe(() => M.start({ media: 'audio', ringback: ringback ? '_DEFAULT_' : '' })),
      stop: () => safe(() => M.stop()),
      setSpeaker: (on) => safe(() => M.setForceSpeakerphoneOn(on)),
      startRingtone: () => safe(() => M.startRingtone('_DEFAULT_')),
      stopRingtone: () => safe(() => M.stopRingtone()),
    };
  } catch {
    return null;
  }
}

async function micPermission(): Promise<boolean> {
  try {
    const { AudioModule } = await import('expo-audio');
    return (await AudioModule.requestRecordingPermissionsAsync()).granted;
  } catch {
    return false;
  }
}

export const callEngine = createCallEngine({
  api: (path, opts) => api(path, opts as Parameters<typeof api>[1]),
  rtc: loadRtc(),
  audio: loadAudio(),
  micPermission,
  meId: getSessionUserId,
});

export function useCall() {
  return useSyncExternalStore(callEngine.subscribe, callEngine.getState, callEngine.getState);
}
