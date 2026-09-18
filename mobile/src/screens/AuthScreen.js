import React, { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { api } from '../api';
import { useApp } from '../state';
import { colors } from '../theme';

export default function AuthScreen({ navigation }) {
  const { signIn } = useApp();
  const [mode, setMode] = useState('login');
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const set = (key) => (value) => setForm((current) => ({ ...current, [key]: value }));

  async function submit() {
    setError('');
    setBusy(true);
    try {
      const payload =
        mode === 'register'
          ? form
          : { email: form.email.trim(), password: form.password };
      const data = mode === 'register' ? await api.register(payload) : await api.login(payload);

      if (data.requires_otp) {
        navigation.navigate('Otp', { email: data.email, purpose: data.purpose });
      } else if (data.token) {
        await signIn(data.token);
      }
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.wrap} keyboardShouldPersistTaps="handled">
        <Text style={styles.brand}>nextech</Text>
        <Text style={styles.title}>{mode === 'register' ? 'Create your account' : 'Welcome back'}</Text>
        <Text style={styles.sub}>
          {mode === 'register' ? 'Save your details for faster checkout.' : 'Sign in to pick up where you left off.'}
        </Text>

        {mode === 'register' && (
          <TextInput style={styles.input} placeholder="Full name" value={form.name} onChangeText={set('name')} />
        )}
        <TextInput
          style={styles.input}
          placeholder="Email address"
          autoCapitalize="none"
          keyboardType="email-address"
          value={form.email}
          onChangeText={set('email')}
        />
        <TextInput
          style={styles.input}
          placeholder="Password"
          secureTextEntry
          value={form.password}
          onChangeText={set('password')}
        />
        {mode === 'register' && (
          <TextInput
            style={styles.input}
            placeholder="Confirm password"
            secureTextEntry
            value={form.password_confirmation}
            onChangeText={set('password_confirmation')}
          />
        )}

        {!!error && <Text style={styles.error}>{error}</Text>}

        <Pressable style={styles.button} onPress={submit} disabled={busy}>
          {busy ? <ActivityIndicator color="#fff" /> : (
            <Text style={styles.buttonText}>{mode === 'register' ? 'Create account' : 'Sign in'}</Text>
          )}
        </Pressable>

        <Pressable onPress={() => { setMode(mode === 'register' ? 'login' : 'register'); setError(''); }}>
          <Text style={styles.switch}>
            {mode === 'register' ? 'Already have an account? Sign in' : 'New here? Create an account'}
          </Text>
        </Pressable>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  wrap: { padding: 24, paddingTop: 90, backgroundColor: colors.bg, flexGrow: 1 },
  brand: { fontSize: 15, fontWeight: '800', color: colors.brand, letterSpacing: -0.3 },
  title: { fontSize: 26, fontWeight: '800', color: colors.ink, marginTop: 18, letterSpacing: -0.6 },
  sub: { fontSize: 14, color: colors.muted, marginTop: 6, marginBottom: 22 },
  input: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 13,
    fontSize: 15,
    marginBottom: 12,
    color: colors.ink,
  },
  error: { color: colors.danger, fontSize: 13, marginBottom: 10 },
  button: {
    backgroundColor: colors.brand,
    borderRadius: 10,
    paddingVertical: 15,
    alignItems: 'center',
    marginTop: 6,
  },
  buttonText: { color: '#fff', fontSize: 15, fontWeight: '700' },
  switch: { color: colors.muted, fontSize: 13, textAlign: 'center', marginTop: 18 },
});
