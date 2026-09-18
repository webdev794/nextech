import React, { useRef, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { api } from '../api';
import { useApp } from '../state';
import { colors, money } from '../theme';
import { stripeHtml } from '../payment/stripeHtml';

export default function PaymentScreen({ route, navigation }) {
  const { orderId, clientSecret, totalCents } = route.params;
  const { config } = useApp();
  const [status, setStatus] = useState('loading'); // loading | ready | confirming | done | error
  const [message, setMessage] = useState('');
  const handled = useRef(false);

  const publishableKey = config?.stripe_publishable_key;

  if (!publishableKey) {
    return (
      <View style={styles.centre}>
        <Text style={styles.error}>
          Stripe is not configured on the server. Add STRIPE_PUBLISHABLE_KEY to the backend .env.
        </Text>
      </View>
    );
  }

  async function onMessage(event) {
    let payload;
    try {
      payload = JSON.parse(event.nativeEvent.data);
    } catch {
      return;
    }

    if (payload.type === 'ready') {
      setStatus('ready');
      return;
    }
    if (payload.type === 'error') {
      setStatus('error');
      setMessage(payload.message || 'Payment failed.');
      return;
    }
    if (payload.type === 'pending') {
      setStatus('error');
      setMessage('This card needs an extra verification step. Try another card.');
      return;
    }
    if (payload.type === 'success' && !handled.current) {
      handled.current = true;
      setStatus('confirming');
      // Reconcile with the server (works even if the Stripe webhook listener is off).
      try {
        await api.paymentIntent(orderId);
      } catch {
        // The webhook will still confirm it; the order screen shows the live status.
      }
      setStatus('done');
    }
  }

  if (status === 'done') {
    return (
      <View style={styles.centre}>
        <Text style={styles.doneTitle}>Payment submitted</Text>
        <Text style={styles.doneText}>Order #{orderId} is being confirmed.</Text>
        <Pressable
          style={styles.button}
          onPress={() => navigation.reset({ index: 1, routes: [{ name: 'Catalog' }, { name: 'Orders' }] })}
        >
          <Text style={styles.buttonText}>View your orders</Text>
        </Pressable>
      </View>
    );
  }

  return (
    <View style={styles.wrap}>
      <View style={styles.summary}>
        <Text style={styles.summaryText}>Order #{orderId}</Text>
        <Text style={styles.summaryTotal}>{money(totalCents)}</Text>
      </View>

      {(status === 'loading' || status === 'confirming') && (
        <View style={styles.overlay}>
          <ActivityIndicator size="large" color={colors.brand} />
          <Text style={styles.overlayText}>
            {status === 'confirming' ? 'Confirming your order…' : 'Loading secure payment…'}
          </Text>
        </View>
      )}

      <WebView
        originWhitelist={['*']}
        source={{ html: stripeHtml(clientSecret, publishableKey), baseUrl: 'https://nextech.local' }}
        onMessage={onMessage}
        style={{ flex: 1, opacity: status === 'ready' || status === 'error' ? 1 : 0 }}
        javaScriptEnabled
        domStorageEnabled
      />

      {!!message && <Text style={styles.error}>{message}</Text>}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg },
  centre: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg, padding: 24 },
  summary: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
    backgroundColor: colors.surface,
  },
  summaryText: { fontSize: 13, color: colors.muted },
  summaryTotal: { fontSize: 18, fontWeight: '800', color: colors.ink },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    top: 53,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.bg,
    zIndex: 2,
    gap: 12,
  },
  overlayText: { color: colors.muted, fontSize: 13 },
  error: { color: colors.danger, fontSize: 13, padding: 16 },
  doneTitle: { fontSize: 20, fontWeight: '800', color: colors.ink },
  doneText: { fontSize: 14, color: colors.muted, marginTop: 8, textAlign: 'center' },
  button: {
    backgroundColor: colors.brand,
    borderRadius: 10,
    paddingVertical: 15,
    paddingHorizontal: 28,
    alignItems: 'center',
    marginTop: 22,
  },
  buttonText: { color: '#fff', fontSize: 15, fontWeight: '700' },
});
