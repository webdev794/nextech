import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api } from '../api';
import { useApp } from '../state';
import { colors, money, saleInfo } from '../theme';
import ProductThumb from '../components/ProductThumb';

export default function ProductScreen({ route, navigation }) {
  const { slug } = route.params;
  const { addToCart, deliveryLocation } = useApp();
  const [product, setProduct] = useState(null);
  const [error, setError] = useState('');
  const [added, setAdded] = useState(false);
  const [selectedId, setSelectedId] = useState(null); // null = the base product itself
  const addingRef = useRef(false); // blocks a rapid double-tap/touch-bounce from queuing two adds

  useEffect(() => {
    (async () => {
      try {
        const { data } = await api.product(
          slug,
          deliveryLocation ? { lat: deliveryLocation.lat, lng: deliveryLocation.lng } : {},
        );
        setProduct(data);
        setSelectedId(null);
        navigation.setOptions({ title: data.name });
      } catch (e) {
        setError(e.message);
      }
    })();
  }, [slug]);

  if (error) return <Text style={styles.error}>{error}</Text>;
  if (!product) {
    return (
      <View style={styles.centre}>
        <ActivityIndicator size="large" color={colors.brand} />
      </View>
    );
  }

  // Variant picker: the base product is itself an option (id: null), so
  // switching between it and its variants is a single selection.
  const variants = product.variants ?? [];
  const hasVariants = variants.length > 0;
  const options = hasVariants
    ? [
        {
          id: null,
          label: 'Standard',
          price_cents: product.price_cents,
          compare_at_price_cents: product.compare_at_price_cents,
          inventory_quantity: product.inventory_quantity,
          image_url: product.image_url,
        },
        ...variants,
      ]
    : [];
  const chosen = hasVariants ? (options.find((o) => o.id === selectedId) ?? options[0]) : null;
  const unitPrice = chosen ? chosen.price_cents : product.price_cents;
  const compareAt = chosen ? chosen.compare_at_price_cents : product.compare_at_price_cents;
  const stock = chosen ? chosen.inventory_quantity : product.inventory_quantity;
  const heroImage = chosen?.image_url || product.image_url;
  const { onSale, pctOff } = saleInfo(unitPrice, compareAt);

  return (
    <ScrollView style={styles.wrap} contentContainerStyle={{ padding: 20 }}>
      <View>
        <ProductThumb name={product.name} imageUrl={heroImage} style={styles.hero} textStyle={styles.heroText} />
        {onSale && stock !== 0 && (
          <View style={styles.saleBadge}>
            <Text style={styles.saleBadgeText}>{pctOff}% off</Text>
          </View>
        )}
      </View>
      <Text style={styles.cat}>{product.category?.name ?? 'Uncategorized'}</Text>
      <Text style={styles.name}>{product.name}</Text>
      <View style={styles.priceRow}>
        <Text style={[styles.price, onSale && styles.priceOnSale]}>{money(unitPrice)}</Text>
        {onSale && <Text style={styles.priceStrike}>{money(compareAt)}</Text>}
      </View>
      {!!product.description && <Text style={styles.desc}>{product.description}</Text>}

      {hasVariants && (
        <View style={styles.variantRow}>
          {options.map((option) => {
            const active = option.id === (chosen?.id ?? null);
            return (
              <Pressable
                key={option.id ?? 'base'}
                style={[styles.variantChip, active && styles.variantChipActive]}
                onPress={() => setSelectedId(option.id)}
              >
                <Text style={[styles.variantChipText, active && styles.variantChipTextActive]}>
                  {option.label}
                </Text>
              </Pressable>
            );
          })}
        </View>
      )}

      <Text style={styles.stock}>{stock > 0 ? `${stock} in stock` : 'Out of stock'}</Text>

      <Pressable
        style={[styles.button, stock <= 0 && styles.buttonOff]}
        disabled={stock <= 0}
        onPress={() => {
          if (addingRef.current) return;
          addingRef.current = true;
          addToCart(product, chosen?.id != null ? chosen : null);
          setAdded(true);
          setTimeout(() => setAdded(false), 1500);
          setTimeout(() => { addingRef.current = false; }, 400);
        }}
      >
        <Text style={styles.buttonText}>
          {stock <= 0 ? 'Out of stock' : added ? 'Added to cart' : 'Add to cart'}
        </Text>
      </Pressable>
      <Pressable style={styles.secondary} onPress={() => navigation.navigate('Cart')}>
        <Text style={styles.secondaryText}>Go to cart</Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg },
  centre: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
  error: { color: colors.danger, padding: 20 },
  hero: {
    height: 180,
    borderRadius: 16,
    backgroundColor: '#eef1ec',
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroText: { fontSize: 46, fontWeight: '800', color: colors.accent },
  saleBadge: {
    position: 'absolute',
    top: 10,
    left: 10,
    backgroundColor: colors.danger,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  saleBadgeText: { color: '#fff', fontSize: 11, fontWeight: '700' },
  cat: { fontSize: 11, color: colors.muted, textTransform: 'uppercase', letterSpacing: 0.6, marginTop: 18 },
  name: { fontSize: 24, fontWeight: '800', color: colors.ink, marginTop: 4, letterSpacing: -0.5 },
  priceRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 10 },
  price: { fontSize: 20, fontWeight: '800', color: colors.ink },
  priceOnSale: { color: colors.danger },
  priceStrike: { fontSize: 14, color: colors.muted, textDecorationLine: 'line-through' },
  desc: { fontSize: 14, color: colors.muted, marginTop: 14, lineHeight: 21 },
  variantRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 16 },
  variantChip: {
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.surface,
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  variantChipActive: { backgroundColor: colors.brand, borderColor: colors.brand },
  variantChipText: { fontSize: 12, color: colors.muted, fontWeight: '600' },
  variantChipTextActive: { color: '#fff' },
  stock: { fontSize: 12, color: colors.muted, marginTop: 14 },
  button: {
    backgroundColor: colors.brand,
    borderRadius: 10,
    paddingVertical: 15,
    alignItems: 'center',
    marginTop: 24,
  },
  buttonOff: { backgroundColor: colors.muted, opacity: 0.6 },
  buttonText: { color: '#fff', fontSize: 15, fontWeight: '700' },
  secondary: { paddingVertical: 14, alignItems: 'center' },
  secondaryText: { color: colors.muted, fontSize: 13 },
});
