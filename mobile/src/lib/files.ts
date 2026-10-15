import { Linking, Platform } from 'react-native';
import { File, Paths } from 'expo-file-system';
import * as Sharing from 'expo-sharing';

import { fileHeaders, toApiFileUrl } from '@/api/client';

const safeName = (name: string) => name.replace(/[^\w.\- ]+/g, '_').slice(0, 100) || 'file';

/**
 * Files are private: the server only gives them to a signed-in request. So the app downloads the file with its token
 * into the cache and hands it to the phone's own "open with / share" sheet.
 */
export async function openFile(url: string, name: string): Promise<void> {
  const full = toApiFileUrl(url);

  if (Platform.OS === 'web') {
    await Linking.openURL(full);
    return;
  }

  const dest = new File(Paths.cache, `${Date.now()}-${safeName(name)}`);
  const task = File.createDownloadTask(full, dest, { headers: fileHeaders() });
  const saved = await task.downloadAsync();
  if (!saved) throw new Error('The download was interrupted.');

  if (!(await Sharing.isAvailableAsync())) throw new Error('This phone cannot open files from here.');
  await Sharing.shareAsync(saved.uri, { dialogTitle: name });
}
