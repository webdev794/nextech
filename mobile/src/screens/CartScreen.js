import React from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useApp } from '../state';
import { colors, money, saleInfo } from '../theme';

export default function CartScreen({ navigation }) {
  const { cart, cartTotal, changeQuantity } = useApp();

  if (cart.length === 0) {
    return (
      <View style={styles.centre}>
        <Text style={styles.emptyTitle}>Your cart is empty</Text>
        <Pressable style={styles.button} onPress={() => navigation.navigate('Catalog')}>
          <Text style={styles.buttonText}>Keep shopping</Text>
        </Pressable>
      </View>
    );
  }

  return (
    <View style={styles.wrap}>
      <FlatList
        data={cart}
        keyExtractor={(item) => item.key ?? `${item.id}:${item.variantId ?? ''}`}
        contentContainerStyle={{ padding: 16, gap: 10 }}
        renderItem={({ item }) => {
          const key = item.key ?? `${item.id}:${item.variantId ?? ''}`;
          const { onSale } = saleInfo(item.price_cents, item.compare_at_price_cents);
          return (
            <View style={styles.row}>
              <View style={{ flex: 1 }}>
                <Text style={styles.name}>{item.name}</Text>
                {!!item.variantLabel && <Text style={styles.unit}>{item.variantLabel}</Text>}
                <View style={styles.unitRow}>
                  <Text style={[styles.unit, onSale && styles.unitOnSale]}>{money(item.price_cents)} each</Text>
                  {onSale && <Text style={styles.unitStrike}>{money(item.compare_at_price_cents)}</Text>}
                </View>
              </View>
              <View style={styles.stepper}>
                <Pressable style={styles.stepBtn} onPress={() => changeQuantity(key, -1)}>
                  <Text style={styles.stepText}>-</Text>
                </Pressable>
                <Text style={styles.qty}>{item.quantity}</Text>
                <Pressable style={styles.stepBtn} onPress={() => changeQuantity(key, 1)}>
                  <Text style={styles.stepText}>+</Text>
                </Pressable>
              </View>
              <Text style={styles.lineTotal}>{money(item.price_cents * item.quantity)}</Text>
            </View>
          );
        }}
      />

      <View style={styles.footer}>
        <View style={styles.totalRow}>
          <Text style={styles.totalLabel}>Subtotal</Text>
          <Text style={styles.totalValue}>{money(cartTotal)}</Text>
        </View>
        <Text style={styles.note}>Tax and delivery are calculated by the server at checkout.</Text>
        <Pressable style={styles.button} onPress={() => navigation.navigate('Checkout')}>
          <Text style={styles.buttonText}>Continue to checkout</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg },
  centre: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg, padding: 24 },
  emptyTitle: { fontSize: 18, fontWeight: '700', color: colors.ink, marginBottom: 16 },
  row: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 12,
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  name: { fontSize: 14, fontWeight: '600', color: colors.ink },
  unit: { fontSize: 12, color: colors.muted, marginTop: 2 },
  unitRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  unitOnSale: { color: colors.danger, fontWeight: '700' },
  unitStrike: { fontSize: 11, color: colors.muted, textDecorationLine: 'line-through' },
  stepper: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  stepBtn: {
    width: 28,
    height: 28,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.line,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepText: { fontSize: 16, color: colors.ink },
  qty: { fontSize: 14, fontWeight: '600', minWidth: 18, textAlign: 'center' },
  lineTotal: { fontSize: 14, fontWeight: '700', color: colors.ink, minWidth: 60, textAlign: 'right' },
  footer: {
    borderTopWidth: 1,
    borderTopColor: colors.line,
    backgroundColor: colors.surface,
    padding: 18,
  },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  totalLabel: { fontSize: 13, color: colors.muted },
  totalValue: { fontSize: 22, fontWeight: '800', color: colors.ink, letterSpacing: -0.5 },
  note: { fontSize: 11, color: colors.muted, marginTop: 6 },
  button: {
    backgroundColor: colors.brand,
    borderRadius: 10,
    paddingVertical: 15,
    alignItems: 'center',
    marginTop: 14,
  },
  buttonText: { color: '#fff', fontSize: 15, fontWeight: '700' },
});
