import { useRef, useState } from 'react';
import { Image, KeyboardAvoidingView, Platform, ScrollView, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Backdrop } from '@/components/Backdrop';

import { forgotPassword } from '@/api/auth';
import { ApiError } from '@/api/client';
import { useSession } from '@/auth/session';
import { Button, OfflineBanner, TextField } from '@/components/ui';
import { errorMessage } from '@/lib/errors';
import { brandColors, spacing } from '@/theme';

export default function SignIn() {
  const { signIn } = useSession();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);
  const passwordRef = useRef<TextInput>(null);

  async function submit() {
    if (busy) return;
    setError(null);
    setInfo(null);
    if (!email.trim() || !password) {
      setError('Enter your email and password.');
      return;
    }
    setBusy(true);
    try {
      await signIn(email, password);
      // The navigator swaps to the app by itself once the session is signed in.
    } catch (e) {
      setError(e instanceof ApiError && e.status === 422 ? 'Wrong email or password.' : errorMessage(e));
      setBusy(false);
    }
  }

  async function reset() {
    setError(null);
    setInfo(null);
    if (!email.trim()) {
      setError('Type your email above first, then tap "Forgot password".');
      return;
    }
    try {
      const res = await forgotPassword(email);
      setInfo(res.message);
    } catch (e) {
      setError(errorMessage(e));
    }
  }

  return (
    <Backdrop>
      <SafeAreaView style={{ flex: 1 }}>
        <OfflineBanner />
        <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
          <ScrollView contentContainerStyle={{ flexGrow: 1, justifyContent: 'center', padding: spacing.lg }} keyboardShouldPersistTaps="handled">
            <Image
              source={require('../../assets/brand/logo-white.png')}
              style={{ width: 180, height: 106, alignSelf: 'center', marginBottom: spacing.lg }}
              resizeMode="contain"
              accessibilityLabel="IKIA Tech"
            />
            <View style={{ backgroundColor: '#ffffff', borderRadius: 18, padding: spacing.lg, gap: spacing.md }}>
              <Text style={{ fontSize: 22, fontWeight: '800', color: brandColors.deep }}>Sign in to Desk</Text>
              <TextField
                label="Email"
                value={email}
                onChangeText={setEmail}
                autoCapitalize="none"
                autoCorrect={false}
                autoComplete="email"
                keyboardType="email-address"
                textContentType="username"
                returnKeyType="next"
                onSubmitEditing={() => passwordRef.current?.focus()}
                style={{ backgroundColor: '#fff', color: '#10242a' }}
              />
              <TextField
                ref={passwordRef}
                label="Password"
                value={password}
                onChangeText={setPassword}
                secureTextEntry
                autoCapitalize="none"
                autoComplete="current-password"
                textContentType="password"
                returnKeyType="go"
                onSubmitEditing={submit}
                style={{ backgroundColor: '#fff', color: '#10242a' }}
              />
              {error ? <Text style={{ color: '#c0392b', fontSize: 14 }}>{error}</Text> : null}
              {info ? <Text style={{ color: '#2f855a', fontSize: 14 }}>{info}</Text> : null}
              <Button title="Sign in" onPress={submit} loading={busy} />
              <Button title="Forgot password" onPress={reset} variant="secondary" disabled={busy} />
            </View>
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </Backdrop>
  );
}
