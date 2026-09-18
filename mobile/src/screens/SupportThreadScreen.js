import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator, KeyboardAvoidingView, Platform, Pressable, ScrollView,
  StyleSheet, Text, TextInput, View,
} from 'react-native';
import { api } from '../api';
import { colors } from '../theme';

function ChatRating({ thread, onSaved }) {
  const rated = thread.rating != null;
  const [rating, setRating] = useState(thread.rating ?? 0);
  const [comment, setComment] = useState(thread.rating_comment ?? '');
  const [editing, setEditing] = useState(!rated);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState('');

  const submit = async () => {
    if (!rating) { setMsg('Tap a star to rate.'); return; }
    setBusy(true); setMsg('');
    try {
      const { data } = await api.rateSupportThread(thread.id, { rating, comment: comment.trim() || null });
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
      <View style={styles.rating}>
        <Text style={styles.ratingDone}>You rated this chat</Text>
        {stars}
        <Pressable onPress={() => setEditing(true)}><Text style={styles.ratingLink}>Edit</Text></Pressable>
      </View>
    );
  }

  return (
    <View style={styles.rating}>
      <Text style={styles.ratingH}>How was this conversation?</Text>
      {stars}
      <TextInput
        style={styles.ratingInput}
        placeholder="Anything we could do better? (optional)"
        placeholderTextColor={colors.muted}
        value={comment}
        onChangeText={setComment}
        multiline
        maxLength={1000}
      />
      <Pressable style={styles.ratingBtn} onPress={submit} disabled={busy}>
        <Text style={styles.ratingBtnText}>{rated ? 'Update rating' : 'Submit rating'}</Text>
      </Pressable>
      {!!msg && <Text style={styles.ratingMsg}>{msg}</Text>}
    </View>
  );
}

export default function SupportThreadScreen({ route }) {
  const { id } = route.params;
  const [thread, setThread] = useState(null);
  const [reply, setReply] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const scrollRef = useRef(null);

  const refresh = useCallback(async () => {
    try {
      const { data } = await api.supportThread(id);
      setThread(data);
    } catch (e) {
      setError(e.message);
    }
  }, [id]);

  useEffect(() => {
    refresh();
    const timer = setInterval(refresh, 4000);
    return () => clearInterval(timer);
  }, [refresh]);

  const send = async () => {
    const body = reply.trim();
    if (!body) return;
    setBusy(true);
    setError('');
    try {
      const { data } = await api.supportReply(id, body);
      setReply('');
      setThread(data);
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };

  if (!thread) {
    return <View style={styles.center}><ActivityIndicator color={colors.brand} /></View>;
  }

  return (
    <KeyboardAvoidingView
      style={styles.screen}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={90}
    >
      {thread.status === 'resolved' && (
        <Text style={styles.resolved}>This conversation is resolved. Send a message to re-open it.</Text>
      )}
      <ScrollView
        ref={scrollRef}
        style={styles.log}
        contentContainerStyle={{ padding: 16, gap: 9 }}
        onContentSizeChange={() => scrollRef.current?.scrollToEnd({ animated: true })}
      >
        {(thread.messages ?? []).map((m) => {
          const kind = m.is_staff ? 'staff' : m.user_id ? 'me' : 'system';
          return (
            <View key={m.id} style={[styles.msg, styles[kind]]}>
              <Text style={kind === 'me' ? styles.msgTextMe : styles.msgText}>{m.body}</Text>
              <Text style={kind === 'me' ? styles.timeMe : styles.time}>
                {new Date(m.created_at).toLocaleString()}
              </Text>
            </View>
          );
        })}
      </ScrollView>
      {thread.issue_type !== 'delivery' && (thread.rating != null || (thread.messages ?? []).some((m) => m.is_staff)) && (
        <ChatRating thread={thread} onSaved={setThread} />
      )}
      {!!error && <Text style={styles.error}>{error}</Text>}
      <View style={styles.sendRow}>
        <TextInput
          style={styles.input}
          placeholder="Type a message"
          placeholderTextColor={colors.muted}
          value={reply}
          onChangeText={setReply}
        />
        <Pressable style={styles.sendBtn} onPress={send} disabled={busy || !reply.trim()}>
          <Text style={styles.sendText}>Send</Text>
        </Pressable>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
  resolved: { backgroundColor: '#fdeede', color: '#8a5a1f', padding: 10, fontSize: 12 },
  log: { flex: 1 },
  msg: { maxWidth: '82%', padding: 9, borderRadius: 12 },
  me: { alignSelf: 'flex-end', backgroundColor: colors.accent },
  staff: { alignSelf: 'flex-start', backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.line },
  system: { alignSelf: 'center', backgroundColor: '#e7eef6' },
  msgText: { color: colors.ink, fontSize: 13 },
  msgTextMe: { color: '#fff', fontSize: 13 },
  time: { color: colors.muted, fontSize: 9, marginTop: 3 },
  timeMe: { color: '#e4f3e4', fontSize: 9, marginTop: 3 },
  error: { color: colors.danger, paddingHorizontal: 16 },
  sendRow: { flexDirection: 'row', gap: 8, padding: 12, borderTopWidth: 1, borderColor: colors.line, backgroundColor: colors.surface },
  input: { flex: 1, borderWidth: 1, borderColor: colors.line, padding: 10, color: colors.ink, backgroundColor: colors.bg },
  sendBtn: { paddingHorizontal: 16, justifyContent: 'center', backgroundColor: colors.brand },
  sendText: { color: '#fff', fontWeight: '800' },
  rating: { padding: 12, borderTopWidth: 1, borderColor: colors.line, backgroundColor: colors.surface, gap: 8, flexDirection: 'column', alignItems: 'flex-start' },
  ratingH: { fontSize: 12, fontWeight: '700', color: colors.ink },
  ratingDone: { fontSize: 12, color: colors.muted },
  starRow: { flexDirection: 'row', gap: 4 },
  star: { fontSize: 22, color: '#d3d8cd' },
  starOn: { color: '#f5a623' },
  ratingInput: { alignSelf: 'stretch', borderWidth: 1, borderColor: colors.line, borderRadius: 8, padding: 10, fontSize: 13, color: colors.ink, minHeight: 40, backgroundColor: colors.bg, textAlignVertical: 'top' },
  ratingBtn: { backgroundColor: colors.accent, borderRadius: 8, paddingVertical: 8, paddingHorizontal: 14 },
  ratingBtnText: { color: '#fff', fontWeight: '800', fontSize: 12 },
  ratingLink: { color: colors.accent, fontWeight: '700', fontSize: 12 },
  ratingMsg: { fontSize: 11, color: colors.muted },
});
