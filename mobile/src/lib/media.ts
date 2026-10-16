import * as DocumentPicker from 'expo-document-picker';
import * as ImagePicker from 'expo-image-picker';

import type { LocalFile } from '@/api/client';

export class PermissionDenied extends Error {
  constructor(what: string) {
    super(`Allow access to your ${what} in the phone's Settings to do this.`);
    this.name = 'PermissionDenied';
  }
}

const extOf = (mime: string | undefined | null, fallback: string) => (mime?.split('/')[1] ?? fallback).replace('jpeg', 'jpg').replace(/[^a-z0-9]/gi, '') || fallback;

function fromAsset(a: ImagePicker.ImagePickerAsset, i: number): LocalFile {
  const mime = a.mimeType ?? 'image/jpeg';
  return { uri: a.uri, name: a.fileName || `photo-${Date.now()}-${i}.${extOf(mime, 'jpg')}`, mimeType: mime };
}

/** Photos from the gallery (several at once). Returns [] when cancelled. */
export async function pickPhotos(): Promise<LocalFile[]> {
  const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
  if (!perm.granted) throw new PermissionDenied('photos');
  const res = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], allowsMultipleSelection: true, selectionLimit: 10, quality: 0.8 });
  return res.canceled ? [] : res.assets.map(fromAsset);
}

export async function takePhoto(): Promise<LocalFile[]> {
  const perm = await ImagePicker.requestCameraPermissionsAsync();
  if (!perm.granted) throw new PermissionDenied('camera');
  const res = await ImagePicker.launchCameraAsync({ mediaTypes: ['images'], quality: 0.8 });
  return res.canceled ? [] : res.assets.map(fromAsset);
}

export async function pickFiles(): Promise<LocalFile[]> {
  const res = await DocumentPicker.getDocumentAsync({ multiple: true, copyToCacheDirectory: true });
  return res.canceled ? [] : res.assets.map((a) => ({ uri: a.uri, name: a.name, mimeType: a.mimeType ?? null }));
}

export const isImage = (f: LocalFile) => (f.mimeType ?? '').startsWith('image/') || /\.(jpe?g|png|gif|webp|heic)$/i.test(f.name);
