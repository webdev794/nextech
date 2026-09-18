import React, { useCallback, useState } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, TextInput, View } from 'react-native';
import { api } from '../api';
import { colors, money, STATUS_LABELS, DELIVERY_STAGES } from '../theme';

function RiderRating({ order, onSaved }) {
  const existing = order.rider_review;
  const [rating, setRating] = useState(existing?.rating ?? 0);
  const [comment, setComment] = useState(existing?.comment ?? '');
  const [editing, setEditing] = useState(!existing);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState('');

  const submit = async () => {
    if (!rating) { setMsg('Tap a star to rate.'); return; }
    setBusy(true); setMsg('');
    try {
      const { data } = await api.rateRider(order.id, { rating, comment: comment.trim() || null, source: 'delivery' });
      onSaved(data);
      setEditing(false);
      setMsg('Thanks for the feedback!');
    } catch (e) {
      setMsg(e.message);
    } finally {
      setBusy(false);
    }
  };

  const stars = (
    <View style={styles.starRow}>
      {[1, 2, 3, 4, 5].map((n) => (
        <Pressable key={n} disabled={!editing} onPress={() => setRating(n)} hitSlop={4}>
          <Text style={[styles.star, n <= rating && styles.starOn]}>★</Text>
        </Pressable>
      ))}
    </View>
  );

  if (!editing) {
    return (
      <View style={styles.ratingBox}>
        <Text style={styles.ratingDone}>You rated your rider</Text>
        {stars}
        <Pressable onPress={() => setEditing(true)}><Text style={styles.help}>Edit</Text></Pressable>
      </View>
    );
  }

  return (
    <View style={styles.ratingBox}>
      <Text style={styles.ratingH}>Rate your delivery rider</Text>
      {stars}
      <TextInput
        style={styles.ratingInput}
        placeholder="Private note for the NexTech team (optional)"
        placeholderTextColor={colors.muted}
        value={comment}
        onChangeText={setComment}
        multiline
        maxLength={1000}
      />
      <Pressable style={styles.ratingBtn} disabled={busy} onPress={submit}>
        <Text style={styles.ratingBtnText}>{existing ? 'Update rating' : 'Submit rating'}</Text>
      </Pressable>
      {!!msg && <Text style={styles.ratingMsg}>{msg}</Text>}
    </View>
  );
}

function Tracker({ status }) {
  if (status === 'cancelled') return <Text style={styles.cancelled}>Cancelled</Text>;
  const index = DELIVERY_STAGES.indexOf(status);
  if (index < 0) return null;
  return (
    <View style={styles.tracker}>
      {DELIVERY_STAGES.map((stage, i) => (
        <View key={stage} style={[styles.segment, i <= index && styles.segmentDone]} />
      ))}
      <Text style={styles.trackerLabel}>{STATUS_LABELS[status]}</Text>
    </View>
  );
}

export default function OrdersScreen({ navigation }) {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setError('');
    try {
      const { data } = await api.orders();
      setOrders(data ?? []);
    } catch (e) {
      setError(e.message);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      let active = true;
      (async () => {
        await load();
        if (active) setLoading(false);
      })();
      return () => {
        active = false;
      };
    }, [load])
  );

  if (loading) {
    return (
      <View style={styles.centre}>
        <ActivityIndicator size="large" color={colors.brand} />
      </View>
    );
  }

  return (
    <FlatList
      style={styles.wrap}
      data={orders}
      keyExtractor={(item) => String(item.id)}
      contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 40 }}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={async () => {
            setRefreshing(true);
            await load();
            setRefreshing(false);
          }}
        />
      }
      ListEmptyComponent={
        <Text style={styles.empty}>{error || 'No orders yet. Your checkouts will appear here.'}</Text>
      }
      renderItem={({ item }) => {
        const paymentPaid = item.payment_status === 'paid';
        return (
          <View style={styles.card}>
            <View style={styles.cardHead}>
              <Text style={styles.orderId}>Order #{item.id}</Text>
              <Text style={[styles.badge, paymentPaid ? styles.badgePaid : styles.badgePending]}>
                {paymentPaid ? 'Paid' : STATUS_LABELS[item.payment_status] || item.payment_status}
              </Text>
            </View>
            <View style={styles.cardMeta}>
              <Text style={styles.metaText}>{new Date(item.created_at).toLocaleDateString()}</Text>
              <Text style={styles.metaText}>{item.items?.length ?? 0} items</Text>
              <Text style={styles.total}>{money(item.total_cents)}</Text>
            </View>
            {paymentPaid && <Tracker status={item.status} />}
            {item.status === 'out_for_delivery' && item.delivery_code && new Date(item.delivery_code_expires_at) > new Date() && (
              <Text style={styles.handover}>Delivery code {item.delivery_code} — read this to your rider.</Text>
            )}
            {!!item.courier_name && paymentPaid && (
              <Text style={styles.courier}>Courier: {item.courier_name}</Text>
            )}
            <Pressable onPress={() => navigation.navigate('Support', { orderId: item.id })}>
              <Text style={styles.help}>Get help with this order</Text>
            </Pressable>
            {item.status === 'completed' && item.delivery_partner_id && (
              <RiderRating
                order={item}
                onSaved={(rv) => setOrders((current) => current.map((row) => (row.id === item.id ? { ...row, rider_review: rv } : row)))}
              />
            )}
          </View>
        );
      }}
    />
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg },
  centre: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
  empty: { color: colors.muted, textAlign: 'center', marginTop: 50, paddingHorizontal: 24 },
  card: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 14,
    padding: 14,
  },
  cardHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  orderId: { fontSize: 14, fontWeight: '800', color: colors.ink },
  badge: {
    fontSize: 10,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    paddingHorizontal: 9,
    paddingVertical: 3,
    borderRadius: 999,
    overflow: 'hidden',
  },
  badgePaid: { backgroundColor: colors.accentSoft, color: colors.accent },
  badgePending: { backgroundColor: '#eef1ec', color: colors.muted },
  cardMeta: { flexDirection: 'row', alignItems: 'center', gap: 14, marginTop: 8 },
  metaText: { fontSize: 12, color: colors.muted },
  total: { fontSize: 14, fontWeight: '700', color: colors.ink, marginLeft: 'auto' },
  tracker: { flexDirection: 'row', alignItems: 'center', gap: 5, marginTop: 12 },
  segment: { width: 34, height: 4, borderRadius: 2, backgroundColor: '#e3e7e0' },
  segmentDone: { backgroundColor: colors.accent },
  trackerLabel: { marginLeft: 6, fontSize: 10, color: colors.muted, textTransform: 'uppercase', letterSpacing: 0.4 },
  cancelled: { marginTop: 12, fontSize: 11, color: colors.danger, textTransform: 'uppercase', letterSpacing: 0.4 },
  courier: { marginTop: 8, fontSize: 12, color: colors.muted },
  handover: { marginTop: 8, fontSize: 13, fontWeight: '700', color: '#1f4e8a' },
  help: { marginTop: 10, fontSize: 12, color: colors.accent, fontWeight: '700' },
  ratingBox: { marginTop: 12, paddingTop: 12, borderTopWidth: 1, borderTopColor: colors.line, gap: 8 },
  ratingH: { fontSize: 12, fontWeight: '700', color: colors.ink },
  ratingDone: { fontSize: 12, color: colors.muted },
  starRow: { flexDirection: 'row', gap: 4 },
  star: { fontSize: 22, color: '#d3d8cd' },
  starOn: { color: '#f5a623' },
  ratingInput: { borderWidth: 1, borderColor: colors.line, borderRadius: 8, padding: 10, fontSize: 13, color: colors.ink, minHeight: 40 },
  ratingBtn: { alignSelf: 'flex-start', backgroundColor: colors.accent, borderRadius: 8, paddingVertical: 8, paddingHorizontal: 14 },
  ratingBtnText: { color: '#fff', fontWeight: '800', fontSize: 12 },
  ratingMsg: { fontSize: 11, color: colors.muted },
});
