import { Alert } from 'react-native';

import type { LocalFile } from '@/api/client';
import { pickFiles, pickPhotos, takePhoto } from '@/lib/media';
import { ActionSheet } from './Sheet';

/** "Photo library / Camera / File" menu. Hands the chosen files to `onPicked`; the caller uploads them. */
export function AttachSheet({ visible, onClose, onPicked }: { visible: boolean; onClose: () => void; onPicked: (files: LocalFile[]) => void }) {
  const run = (pick: () => Promise<LocalFile[]>) => async () => {
    try {
      const files = await pick();
      if (files.length) onPicked(files);
    } catch (e) {
      Alert.alert('Could not attach', e instanceof Error ? e.message : 'Try again.');
    }
  };
  return (
    <ActionSheet
      visible={visible}
      onClose={onClose}
      title="Attach"
      actions={[
        { label: 'Photo library', onPress: run(pickPhotos) },
        { label: 'Camera', onPress: run(takePhoto) },
        { label: 'File', onPress: run(pickFiles) },
      ]}
    />
  );
}
