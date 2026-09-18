import React, { useCallback, useRef, useState } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import {
  ActivityIndicator, Linking, Pressable, RefreshControl, ScrollView, StyleSheet, Text, Vibration, View,
} from 'react-native';
import * as Location from 'expo-location';
import { api } from '../api';
import { colors, money } from '../theme';

// Best-effort: tell the server where the rider is so auto-assignment can pick the
// closest one. Silent on failure (permission denied, GPS off, offline).
async function pingLocation() {
  try {
    const { status } = await Location.requestForegroundPermissionsAsync();
    if (status !== 'granted') return;
    const pos = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
    await api.riderLocation(pos.coords.latitude, pos.coords.longitude);
  } catch {
    // ignore
  }
}

function addressLine(a) {
  if (!a) return '';
  return [a.line1, a.city, a.state, a.postal_code].filter(Boolean).join(', ');
}

function DeliveryCard({ order, mine, onAction, navigation, busy }) {
  const a = order.delivery_address || {};
  return (
    <Pressable style={styles.card} onPress={() => navigation.navigate('DeliveryDetail', { id: order.id })}>
      <View style={styles.cardHead}>
        <Text style={styles.orderId}>Order #{order.id}</Text>
        <Text style={styles.meta}>{order.items?.length ?? 0} items · {money(order.total_cents)}</Text>
      </View>
      <Text style={styles.addr}>{addressLine(a)}</Text>
      {!!order.delivery_instructions && <Text style={styles.note}>“{order.delivery_instructions}”</Text>}
      {order.cod_due > 0 && <Text style={styles.cod}>Collect cash: {money(order.cod_due)}</Text>}
      <View style={styles.actions}>
        <Pressable style={styles.linkBtn} onPress={() => Linking.openURL(`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addressLine(a))}`)}>
          <Text style={styles.linkText}>Maps</Text>
        </Pressable>
        {!!order.customer_phone && (
          <Pressable style={styles.linkBtn} onPress={() => Linking.openURL(`tel:${order.customer_phone}`)}>
            <Text style={styles.linkText}>Call</Text>
          </Pressable>
        )}
        {mine ? (
          <>
            {order.cod_due > 0 && (
              <Pressable style={styles.actBtn} disabled={busy} onPress={() => onAction('cash', order)}>
                <Text style={styles.actText}>Cash collected</Text>
              </Pressable>
            )}
            <Pressable style={[styles.actBtn, styles.primaryBtn]} disabled={busy} onPress={() => navigation.navigate('DeliveryDetail', { id: order.id })}>
              <Text style={styles.actText}>Deliver</Text>
            </Pressable>
          </>
        ) : (
          <Pressable style={[styles.actBtn, styles.primaryBtn]} disabled={busy} onPress={() => onAction('claim', order)}>
            <Text style={styles.actText}>Pick up</Text>
          </Pressable>
        )}
      </View>
    </Pressable>
  );
}

function StatStrip({ stats }) {
  if (!stats) return null;
  const delta = (stats.deliveries_week ?? 0) - (stats.deliveries_week_prev ?? 0);
  const deltaText = delta === 0 ? 'no change vs last week' : `${delta > 0 ? '▲' : '▼'} ${Math.abs(delta)} vs last week`;
  return (
    <View style={styles.stats}>
      <View style={styles.stat}>
        <Text style={styles.statN}>{stats.deliveries_total ?? 0}</Text>
        <Text style={styles.statL}>All-time</Text>
      </View>
      <View style={styles.stat}>
        <Text style={styles.statN}>{stats.deliveries_week ?? 0}</Text>
        <Text style={styles.statL}>This week</Text>
        <Text style={[styles.statD, delta > 0 && styles.statUp, delta < 0 && styles.statDown]}>{deltaText}</Text>
      </View>
      <View style={styles.stat}>
        <Text style={styles.statN}>{stats.rating_avg != null ? `★ ${stats.rating_avg.toFixed(1)}` : '★ —'}</Text>
        <Text style={styles.statL}>{stats.rating_count ?? 0} ratings</Text>
      </View>
    </View>
  );
}

export default function RiderScreen({ navigation }) {
  const [data, setData] = useState({ assigned: [], pool: [] });
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [newOrders, setNewOrders] = useState([]);
  const seenRef = useRef(null); // Set<orderId>, null until the first poll seeds it

  const load = useCallback(async () => {
    try {
      const { data: d } = await api.riderOrders();
      const next = d ?? { assigned: [], pool: [] };
      setData(next);
      setError('');

      const ids = (next.assigned ?? []).map((o) => o.id);
      if (seenRef.current === null) {
        seenRef.current = new Set(ids);
      } else {
        const fresh = ids.filter((id) => !seenRef.current.has(id));
        if (fresh.length) {
          Vibration.vibrate([0, 400, 180, 400, 180, 700]);
          setNewOrders((cur) => [...new Set([...cur, ...fresh])]);
        }
        seenRef.current = new Set(ids);
      }
    } catch (e) {
      setError(e.message);
    }
    try {
      const { data: s } = await api.riderStats();
      setStats(s ?? null);
    } catch {
      // keep last
    }
  }, []);

  useFocusEffect(useCallback(() => {
    let active = true;
    (async () => { await load(); if (active) setLoading(false); })();
    pingLocation();
    const timer = setInterval(load, 15000);
    // Refresh the rider's position every couple of minutes while the screen is open.
    const locTimer = setInterval(pingLocation, 120000);
    return () => { active = false; clearInterval(timer); clearInterval(locTimer); };
  }, [load]));

  const onAction = async (kind, order) => {
    setBusy(true);
    setError('');
    try {
      if (kind === 'claim') await api.claimOrder(order.id);
      if (kind === 'cash') await api.riderCashCollected(order.id);
      if (kind === 'start') await api.riderStatus(order.id, 'out_for_delivery');
      await load();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };

  if (loading) return <View style={styles.center}><ActivityIndicator size="large" color={colors.brand} /></View>;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={async () => { setRefreshing(true); await load(); setRefreshing(false); }} />}
    >
      {!!error && <Text style={styles.error}>{error}</Text>}

      {newOrders.length > 0 && (
        <View style={styles.newBanner}>
          <Text style={styles.newBannerText}>🛵 New delivery assigned — {newOrders.map((id) => `#${id}`).join(', ')}</Text>
          <Pressable onPress={() => setNewOrders([])}><Text style={styles.newBannerBtn}>Got it</Text></Pressable>
        </View>
      )}

      <StatStrip stats={stats} />

      <Text style={styles.section}>My deliveries ({data.assigned.length})</Text>
      {data.assigned.length === 0 && <Text style={styles.muted}>Nothing on the go.</Text>}
      {data.assigned.map((o) => (
        <DeliveryCard key={o.id} order={o} mine onAction={onAction} navigation={navigation} busy={busy} />
      ))}

      <Text style={styles.section}>Available to pick up ({data.pool.length})</Text>
      {data.pool.length === 0 && <Text style={styles.muted}>No orders waiting.</Text>}
      {data.pool.map((o) => (
        <DeliveryCard key={o.id} order={o} mine={false} onAction={onAction} navigation={navigation} busy={busy} />
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
  section: { fontSize: 13, fontWeight: '800', color: colors.ink, marginTop: 18, marginBottom: 8, textTransform: 'uppercase' },
  muted: { color: colors.muted, fontSize: 13 },
  error: { color: colors.danger, marginBottom: 10 },
  newBanner: { flexDirection: 'row', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 10, padding: 11, borderRadius: 10, backgroundColor: '#fff3d6', borderWidth: 1, borderColor: '#e7c66b' },
  newBannerText: { flex: 1, minWidth: 160, color: '#7a5c14', fontWeight: '800', fontSize: 13 },
  newBannerBtn: { color: '#7a5c14', fontWeight: '800', fontSize: 12, borderWidth: 1, borderColor: '#d8b451', borderRadius: 8, paddingVertical: 5, paddingHorizontal: 10, overflow: 'hidden' },
  stats: { flexDirection: 'row', gap: 8, marginBottom: 4 },
  stat: { flex: 1, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.line, borderRadius: 12, padding: 10 },
  statN: { fontSize: 18, fontWeight: '800', color: colors.ink },
  statL: { fontSize: 10, color: colors.muted, textTransform: 'uppercase', marginTop: 2 },
  statD: { fontSize: 10, color: colors.muted, marginTop: 4, fontWeight: '700' },
  statUp: { color: colors.accent },
  statDown: { color: colors.danger },
  card: { backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.line, borderRadius: 12, padding: 14, marginBottom: 10 },
  cardHead: { flexDirection: 'row', justifyContent: 'space-between' },
  orderId: { fontWeight: '800', color: colors.ink },
  meta: { color: colors.muted, fontSize: 12 },
  addr: { marginTop: 6, color: colors.ink, fontSize: 13 },
  note: { marginTop: 4, color: '#8a6d2f', fontSize: 12, fontStyle: 'italic' },
  cod: { marginTop: 6, color: colors.accent, fontWeight: '700', fontSize: 13 },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 12 },
  linkBtn: { paddingVertical: 8, paddingHorizontal: 14, borderWidth: 1, borderColor: colors.line, borderRadius: 8 },
  linkText: { color: colors.ink, fontWeight: '700', fontSize: 12 },
  actBtn: { paddingVertical: 8, paddingHorizontal: 14, borderRadius: 8, backgroundColor: colors.brand },
  primaryBtn: { backgroundColor: colors.accent },
  actText: { color: '#fff', fontWeight: '800', fontSize: 12 },
});
