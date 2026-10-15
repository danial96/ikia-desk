import { Linking, Platform, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useSession } from '@/auth/session';
import { Backdrop } from '@/components/Backdrop';
import { Button } from '@/components/ui';
import { APP_VERSION } from '@/config';
import { spacing } from '@/theme';

/** Shown instead of everything else when the server says this build is too old (HTTP 426). */
export default function UpdateRequired() {
  const { updateInfo } = useSession();
  const link = Platform.OS === 'ios' ? updateInfo?.store?.ios : updateInfo?.store?.android;

  return (
    <Backdrop>
      <SafeAreaView style={{ flex: 1, justifyContent: 'center', padding: spacing.lg }}>
        <View style={{ backgroundColor: '#ffffff', borderRadius: 18, padding: spacing.lg, gap: spacing.md }}>
          <Text style={{ fontSize: 22, fontWeight: '800', color: '#04252b' }}>Please update IKIA Desk</Text>
          <Text style={{ fontSize: 15, color: '#5b7077', lineHeight: 22 }}>
            This version of the app ({APP_VERSION}) is too old to keep working with the server
            {updateInfo?.minVersion ? `. Version ${updateInfo.minVersion} or newer is needed.` : '.'}
          </Text>
          {link ? (
            <Button title="Get the update" onPress={() => Linking.openURL(link)} />
          ) : (
            <Text style={{ fontSize: 14, color: '#5b7077' }}>Ask your administrator for the new version.</Text>
          )}
        </View>
      </SafeAreaView>
    </Backdrop>
  );
}
