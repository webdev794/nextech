import React, { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { api } from '../api';
import { useApp } from '../state';
import { colors } from '../theme';

export default function OtpScreen({ route, navigation }) {
  const { email, purpose } = route.params;
  const { signIn } = useApp();
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');

  async function verify() {
    setMessage('');
    setBusy(true);
    try {
      const data = await api.verifyOtp({ email, purpose, code: code.trim() });
      await signIn(data.token);
    } catch (e) {
      setMessage(e.message);
    } finally {
      setBusy(false);
    }
  }

  async function resend() {
    setMessage('');
    try {
      const data = await api.resendOtp({ email, purpose });
      setMessage(data.message || 'A new code is on its way.');
    } catch (e) {
      setMessage(e.message);
    }
  }

  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>Enter your code</Text>
      <Text style={styles.sub}>We emailed a 6-digit code to {email}. It expires in 10 minutes.</Text>

      <TextInput
        style={styles.input}
        placeholder="6-digit code"
        keyboardType="number-pad"
        maxLength={8}
        value={code}
        onChangeText={(value) => setCode(value.replace(/[^0-9]/g, ''))}
      />

      {!!message && <Text style={styles.message}>{message}</Text>}

      <Pressable style={styles.button} onPress={verify} disabled={busy || code.length < 4}>
        {busy ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Verify</Text>}
      </Pressable>

      <Pressable onPress={resend}>
        <Text style={styles.link}>Resend code</Text>
      </Pressable>
      <Pressable onPress={() => navigation.goBack()}>
        <Text style={styles.link}>Use a different email</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, padding: 24, paddingTop: 40, backgroundColor: colors.bg },
  title: { fontSize: 24, fontWeight: '800', color: colors.ink, letterSpacing: -0.5 },
  sub: { fontSize: 14, color: colors.muted, marginTop: 8, marginBottom: 22 },
  input: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 14,
    fontSize: 20,
    letterSpacing: 6,
    color: colors.ink,
  },
  message: { color: colors.danger, fontSize: 13, marginTop: 12 },
  button: {
    backgroundColor: colors.brand,
    borderRadius: 10,
    paddingVertical: 15,
    alignItems: 'center',
    marginTop: 18,
  },
  buttonText: { color: '#fff', fontSize: 15, fontWeight: '700' },
  link: { color: colors.muted, fontSize: 13, textAlign: 'center', marginTop: 16 },
});
