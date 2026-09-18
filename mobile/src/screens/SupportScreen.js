import React, { useCallback, useState } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import {
  ActivityIndicator, FlatList, Pressable, ScrollView, StyleSheet, Text, TextInput, View,
} from 'react-native';
import { api, ISSUE_TYPES } from '../api';
import { colors } from '../theme';

const ISSUE_LABEL_EXTRA = { delivery: 'Delivery message' };
const issueLabel = (t) => ISSUE_LABEL_EXTRA[t] || (ISSUE_TYPES.find(([x]) => x === t) || [null, t])[1];

export default function SupportScreen({ navigation, route }) {
  const presetOrderId = route.params?.orderId ?? null;
  const [threads, setThreads] = useState([]);
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [mode, setMode] = useState(presetOrderId ? 'new' : 'list');
  const [orderId, setOrderId] = useState(presetOrderId ? String(presetOrderId) : '');
  const [issue, setIssue] = useState('item_missing');
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    try {
      const [t, o] = await Promise.all([api.supportThreads(), api.orders()]);
      setThreads(t.data ?? []);
      setOrders(o.data ?? []);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  const submit = async () => {
    if (!message.trim()) { setError('Describe what happened.'); return; }
    setBusy(true);
    setError('');
    try {
      const body = { issue_type: issue, message: message.trim() };
      if (orderId) body.order_id = Number(orderId);
      const { data } = await api.createSupportThread(body);
      setMessage('');
      navigation.navigate('SupportThread', { id: data.id });
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };

  if (loading) {
    return <View style={styles.center}><ActivityIndicator color={colors.brand} /></View>;
  }

  if (mode === 'new') {
    return (
      <ScrollView style={styles.screen} contentContainerStyle={{ padding: 16 }}>
        <Text style={styles.label}>Which order?</Text>
        <View style={styles.chips}>
          <Chip active={!orderId} onPress={() => setOrderId('')} text="General" />
          {orders.map((o) => (
            <Chip key={o.id} active={orderId === String(o.id)} onPress={() => setOrderId(String(o.id))} text={`#${o.id}`} />
          ))}
        </View>
        <Text style={styles.label}>What's wrong?</Text>
        <View style={styles.chips}>
          {ISSUE_TYPES.map(([t, l]) => (
            <Chip key={t} active={issue === t} onPress={() => setIssue(t)} text={l} />
          ))}
        </View>
        <TextInput
          style={styles.input}
          placeholder="Tell us what happened"
          placeholderTextColor={colors.muted}
          multiline
          value={message}
          onChangeText={setMessage}
        />
        {!!error && <Text style={styles.error}>{error}</Text>}
        <Pressable style={styles.primary} onPress={submit} disabled={busy}>
          <Text style={styles.primaryText}>{busy ? 'Sending…' : 'Send'}</Text>
        </Pressable>
        <Pressable onPress={() => setMode('list')}><Text style={styles.link}>Back to conversations</Text></Pressable>
      </ScrollView>
    );
  }

  return (
    <View style={styles.screen}>
      <Pressable style={styles.primary} onPress={() => setMode('new')}>
        <Text style={styles.primaryText}>New request</Text>
      </Pressable>
      <FlatList
        data={threads}
        keyExtractor={(t) => String(t.id)}
        contentContainerStyle={{ padding: 16 }}
        ListEmptyComponent={<Text style={styles.muted}>No conversations yet.</Text>}
        renderItem={({ item }) => (
          <Pressable style={styles.row} onPress={() => navigation.navigate('SupportThread', { id: item.id })}>
            <Text style={styles.rowTitle}>
              {issueLabel(item.issue_type)}{item.order_id ? ` · Order #${item.order_id}` : ''}
            </Text>
            <Text style={styles.muted}>{item.status === 'resolved' ? 'Resolved' : 'Open'}</Text>
          </Pressable>
        )}
      />
    </View>
  );
}

function Chip({ active, onPress, text }) {
  return (
    <Pressable style={[styles.chip, active && styles.chipActive]} onPress={onPress}>
      <Text style={[styles.chipText, active && styles.chipTextActive]}>{text}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
  label: { color: colors.muted, fontSize: 12, marginBottom: 8, marginTop: 12, textTransform: 'uppercase' },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { paddingVertical: 7, paddingHorizontal: 12, borderRadius: 999, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface },
  chipActive: { borderColor: colors.accent, backgroundColor: colors.accentSoft },
  chipText: { fontSize: 13, color: colors.ink },
  chipTextActive: { color: colors.accent, fontWeight: '700' },
  input: { marginTop: 14, minHeight: 90, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface, padding: 12, color: colors.ink, textAlignVertical: 'top' },
  primary: { margin: 16, backgroundColor: colors.accent, padding: 14, alignItems: 'center' },
  primaryText: { color: '#fff', fontWeight: '800' },
  link: { color: colors.accent, textAlign: 'center', marginTop: 14, fontWeight: '700' },
  error: { color: colors.danger, marginTop: 10 },
  row: { padding: 14, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface, marginBottom: 8 },
  rowTitle: { fontSize: 14, fontWeight: '700', color: colors.ink },
  muted: { color: colors.muted, fontSize: 12, marginTop: 3 },
});
