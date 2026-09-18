import React, { useEffect, useState } from 'react';
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
import * as Location from 'expo-location';
import { api } from '../api';
import { useApp } from '../state';
import { colors } from '../theme';

const BLANK = { name: '', line1: '', city: '', state: '', postal_code: '' };

export default function CheckoutScreen({ navigation }) {
  const { cart, clearCart, user, refreshUser, deliveryLocation, setDeliveryLocation } = useApp();
  const [addresses, setAddresses] = useState([]);
  const [selectedId, setSelectedId] = useState(null);
  const [form, setForm] = useState(BLANK);
  const [phone, setPhone] = useState(user?.phone || '');
  const [busy, setBusy] = useState(false);
  const [locating, setLocating] = useState(false);
  const [error, setError] = useState('');

  async function detectLocation() {
    setError('');
    setLocating(true);
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        setError('Location permission denied.');
        return;
      }
      const pos = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
      setDeliveryLocation({ lat: pos.coords.latitude, lng: pos.coords.longitude });
    } catch {
      setError('Could not read your location.');
    } finally {
      setLocating(false);
    }
  }

  const set = (key) => (value) =>
    setForm((current) => ({ ...current, [key]: key === 'state' ? value.toUpperCase().slice(0, 2) : value }));

  useEffect(() => {
    (async () => {
      try {
        const { data } = await api.addresses();
        setAddresses(data ?? []);
        if (data?.[0]) setSelectedId(data[0].id);
      } catch {
        // an empty address book is fine
      }
    })();
  }, []);

  async function placeOrder() {
    setError('');
    if (!phone.trim()) {
      setError('Add a phone number so your delivery rider can reach you.');
      return;
    }
    setBusy(true);
    try {
      for (const item of cart) {
        await api.addCartItem(
          item.id,
          item.quantity,
          item.variantId,
          deliveryLocation ? { lat: deliveryLocation.lat, lng: deliveryLocation.lng } : {},
        );
      }

      let checkoutBody;
      if (selectedId) {
        checkoutBody = { address_id: selectedId };
      } else {
        const created = await api.addAddress({
          ...form,
          label: 'Home',
          is_default: addresses.length === 0,
          // Coordinates let the server pick the store that serves this address
          // and check every item is stocked there.
          ...(deliveryLocation
            ? { latitude: deliveryLocation.lat, longitude: deliveryLocation.lng }
            : {}),
        });
        checkoutBody = { address_id: created.data.id };
      }

      const order = await api.checkout({ ...checkoutBody, phone: phone.trim() });
      if (phone.trim() !== (user?.phone || '')) refreshUser();
      const intent = await api.paymentIntent(order.data.id);

      clearCart();

      if (intent.data.payment_status === 'paid' || !intent.data.client_secret) {
        navigation.reset({ index: 1, routes: [{ name: 'Catalog' }, { name: 'Orders' }] });
        return;
      }

      navigation.replace('Payment', {
        orderId: order.data.id,
        clientSecret: intent.data.client_secret,
        totalCents: order.data.total_cents,
      });
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView style={styles.wrap} contentContainerStyle={{ padding: 20 }} keyboardShouldPersistTaps="handled">
        <Text style={styles.title}>Where should we deliver?</Text>
        <Text style={styles.sub}>Your total is calculated and confirmed by the server.</Text>

        {addresses.length > 0 && (
          <View style={styles.savedList}>
            {addresses.map((address) => (
              <Pressable
                key={address.id}
                style={[styles.saved, selectedId === address.id && styles.savedActive]}
                onPress={() => setSelectedId(address.id)}
              >
                <Text style={styles.savedLabel}>{address.label || 'Address'}</Text>
                <Text style={styles.savedText}>
                  {address.line1}, {address.city} {address.state} {address.postal_code}
                </Text>
              </Pressable>
            ))}
            <Pressable
              style={[styles.saved, selectedId === null && styles.savedActive]}
              onPress={() => setSelectedId(null)}
            >
              <Text style={styles.savedLabel}>Use a new address</Text>
            </Pressable>
          </View>
        )}

        {selectedId === null && (
          <View style={{ marginTop: 8 }}>
            <Pressable style={styles.locBtn} onPress={detectLocation} disabled={locating}>
              <Text style={styles.locBtnText}>
                {locating
                  ? 'Finding you…'
                  : deliveryLocation
                    ? '📍 Location set — tap to update'
                    : '📍 Use my current location'}
              </Text>
            </Pressable>
            <TextInput style={styles.input} placeholder="Full name" value={form.name} onChangeText={set('name')} />
            <TextInput style={styles.input} placeholder="Street address" value={form.line1} onChangeText={set('line1')} />
            <TextInput style={styles.input} placeholder="City" value={form.city} onChangeText={set('city')} />
            <View style={styles.formRow}>
              <TextInput
                style={[styles.input, { flex: 1 }]}
                placeholder="State"
                autoCapitalize="characters"
                value={form.state}
                onChangeText={set('state')}
              />
              <TextInput
                style={[styles.input, { flex: 1 }]}
                placeholder="ZIP code"
                keyboardType="number-pad"
                value={form.postal_code}
                onChangeText={set('postal_code')}
              />
            </View>
          </View>
        )}

        <Text style={styles.fieldLabel}>
          {user?.phone ? 'Contact phone' : 'Contact phone — the delivery rider may call you'}
        </Text>
        <TextInput
          style={styles.input}
          placeholder="e.g. +1 555 987 6543"
          keyboardType="phone-pad"
          value={phone}
          onChangeText={setPhone}
        />

        {!!error && <Text style={styles.error}>{error}</Text>}

        <Pressable style={styles.button} onPress={placeOrder} disabled={busy}>
          {busy ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Review &amp; pay</Text>}
        </Pressable>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg },
  title: { fontSize: 22, fontWeight: '800', color: colors.ink, letterSpacing: -0.5 },
  sub: { fontSize: 13, color: colors.muted, marginTop: 6, marginBottom: 18 },
  savedList: { gap: 10, marginBottom: 12 },
  saved: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 12,
    padding: 14,
  },
  savedActive: { borderColor: colors.brand },
  savedLabel: { fontSize: 12, fontWeight: '700', color: colors.ink, textTransform: 'uppercase', letterSpacing: 0.4 },
  savedText: { fontSize: 13, color: colors.muted, marginTop: 4 },
  input: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 14,
    marginBottom: 10,
    color: colors.ink,
  },
  locBtn: {
    borderWidth: 1,
    borderColor: colors.brand,
    borderRadius: 10,
    paddingVertical: 11,
    alignItems: 'center',
    marginBottom: 10,
  },
  locBtnText: { color: colors.brand, fontSize: 13, fontWeight: '700' },
  formRow: { flexDirection: 'row', gap: 10 },
  fieldLabel: { fontSize: 12, fontWeight: '700', color: colors.muted, marginTop: 8, marginBottom: 6 },
  error: { color: colors.danger, fontSize: 13, marginTop: 6 },
  button: {
    backgroundColor: colors.brand,
    borderRadius: 10,
    paddingVertical: 15,
    alignItems: 'center',
    marginTop: 16,
  },
  buttonText: { color: '#fff', fontSize: 15, fontWeight: '700' },
});
