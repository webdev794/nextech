import React, { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import * as Location from 'expo-location';
import { api } from '../api';
import { useApp } from '../state';
import { colors, money, saleInfo } from '../theme';
import ProductThumb from '../components/ProductThumb';

export default function CatalogScreen({ navigation }) {
  const { addToCart, cartCount, signOut, deliveryLocation, setDeliveryLocation } = useApp();
  const addingRef = useRef(new Set()); // blocks a rapid double-tap/touch-bounce per product
  const [categories, setCategories] = useState([]);
  const [products, setProducts] = useState([]);
  const [activeCategory, setActiveCategory] = useState(null);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [locating, setLocating] = useState(false);
  const [error, setError] = useState('');

  const coords = deliveryLocation
    ? { lat: deliveryLocation.lat, lng: deliveryLocation.lng }
    : {};

  useLayoutEffect(() => {
    navigation.setOptions({
      headerRight: () => (
        <View style={styles.headerRow}>
          <Pressable onPress={() => navigation.navigate('Orders')} hitSlop={8}>
            <Text style={styles.headerLink}>Orders</Text>
          </Pressable>
          <Pressable onPress={() => navigation.navigate('Cart')} hitSlop={8}>
            <Text style={styles.headerLink}>Cart {cartCount > 0 ? `(${cartCount})` : ''}</Text>
          </Pressable>
        </View>
      ),
      headerLeft: () => (
        <Pressable onPress={signOut} hitSlop={8}>
          <Text style={styles.headerLink}>Sign out</Text>
        </Pressable>
      ),
    });
  }, [navigation, cartCount, signOut]);

  const loadProducts = useCallback(async (opts = {}) => {
    setError('');
    try {
      const { data } = await api.products({
        search: (opts.search ?? search).trim() || undefined,
        category: (opts.category ?? activeCategory) || undefined,
        ...coords,
      });
      setProducts(data ?? []);
    } catch (e) {
      setError(e.message);
    }
  }, [search, activeCategory, deliveryLocation]);

  const loadCategories = useCallback(async () => {
    try {
      const cats = await api.categories(coords);
      setCategories(cats.data ?? []);
    } catch (e) {
      setError(e.message);
    }
  }, [deliveryLocation]);

  useEffect(() => {
    (async () => {
      await loadCategories();
      await loadProducts();
      setLoading(false);
    })();
  }, []);

  // Re-query when the filter changes, or when the customer's location does — the
  // catalog is scoped to the store that serves that point.
  useEffect(() => {
    if (!loading) loadProducts();
  }, [activeCategory]);

  useEffect(() => {
    if (loading) return;
    loadCategories();
    loadProducts();
  }, [deliveryLocation]);

  async function detectLocation() {
    setError('');
    setLocating(true);
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        setError('Location permission denied — you can still browse everything.');
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

  const onRefresh = async () => {
    setRefreshing(true);
    await Promise.all([loadCategories(), loadProducts()]);
    setRefreshing(false);
  };

  if (loading) {
    return (
      <View style={styles.centre}>
        <ActivityIndicator size="large" color={colors.brand} />
      </View>
    );
  }

  return (
    <View style={styles.wrap}>
      <View style={styles.searchRow}>
        <TextInput
          style={styles.search}
          placeholder="Search products"
          value={search}
          returnKeyType="search"
          onChangeText={setSearch}
          onSubmitEditing={() => loadProducts()}
        />
      </View>

      <View style={styles.locRow}>
        <Pressable onPress={detectLocation} disabled={locating} hitSlop={6}>
          <Text style={styles.locText}>
            {locating
              ? 'Finding you…'
              : deliveryLocation
                ? '📍 Showing items from your nearest store · tap to update'
                : '📍 Use my location to see what your nearest store stocks'}
          </Text>
        </Pressable>
        {deliveryLocation && !locating && (
          <Pressable onPress={() => setDeliveryLocation(null)} hitSlop={6}>
            <Text style={styles.locClear}>  Clear</Text>
          </Pressable>
        )}
      </View>

      <FlatList
        horizontal
        showsHorizontalScrollIndicator={false}
        data={[{ id: 'all', name: 'All items', slug: null }, ...categories]}
        keyExtractor={(item) => String(item.id)}
        style={styles.chipRow}
        contentContainerStyle={{ paddingHorizontal: 16, gap: 8, alignItems: 'center' }}
        renderItem={({ item }) => {
          const active = activeCategory === item.slug;
          return (
            <Pressable
              style={[styles.chip, active && styles.chipActive]}
              onPress={() => setActiveCategory(item.slug)}
            >
              <Text style={[styles.chipText, active && styles.chipTextActive]} numberOfLines={1}>{item.name}</Text>
            </Pressable>
          );
        }}
      />

      {!!error && <Text style={styles.error}>{error}</Text>}

      <FlatList
        data={products}
        keyExtractor={(item) => String(item.id)}
        numColumns={2}
        columnWrapperStyle={{ gap: 12, paddingHorizontal: 16 }}
        contentContainerStyle={{ gap: 12, paddingVertical: 14, paddingBottom: 40 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        ListEmptyComponent={<Text style={styles.empty}>No products found.</Text>}
        renderItem={({ item }) => {
          const { onSale, pctOff } = saleInfo(item.price_cents, item.compare_at_price_cents);
          return (
            <Pressable style={styles.card} onPress={() => navigation.navigate('Product', { slug: item.slug })}>
              <View>
                <ProductThumb name={item.name} imageUrl={item.image_url} style={styles.thumb} textStyle={styles.thumbText} />
                {onSale && !item.out_of_stock && (
                  <View style={styles.saleBadge}>
                    <Text style={styles.saleBadgeText}>{pctOff}% off</Text>
                  </View>
                )}
              </View>
              <Text style={styles.cat}>{item.category?.name ?? 'Uncategorized'}</Text>
              <Text style={styles.name} numberOfLines={2}>{item.name}</Text>
              <View style={styles.cardBottom}>
                <View style={styles.priceRow}>
                  <Text style={[styles.price, onSale && styles.priceOnSale]}>{money(item.price_cents)}</Text>
                  {onSale && <Text style={styles.priceStrike}>{money(item.compare_at_price_cents)}</Text>}
                </View>
                {item.out_of_stock ? (
                  <View style={[styles.add, styles.addOff]}><Text style={styles.addOffText}>Out of stock</Text></View>
                ) : (
                  <Pressable
                    style={styles.add}
                    onPress={() => {
                      if (addingRef.current.has(item.id)) return;
                      addingRef.current.add(item.id);
                      addToCart(item);
                      setTimeout(() => addingRef.current.delete(item.id), 400);
                    }}
                  >
                    <Text style={styles.addText}>Add</Text>
                  </Pressable>
                )}
              </View>
            </Pressable>
          );
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg },
  centre: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
  headerRow: { flexDirection: 'row', gap: 14 },
  headerLink: { color: '#fff', fontSize: 13, fontWeight: '600' },
  searchRow: { paddingHorizontal: 16, paddingTop: 14 },
  search: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 11,
    fontSize: 14,
  },
  locRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 16,
    paddingTop: 10,
  },
  locText: { fontSize: 12, color: colors.muted },
  locClear: { fontSize: 12, color: colors.brand, fontWeight: '700' },
  chipRow: { marginTop: 12, flexGrow: 0, height: 44 },
  chip: {
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.surface,
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  chipActive: { backgroundColor: colors.brand, borderColor: colors.brand },
  chipText: { fontSize: 12, color: colors.muted },
  chipTextActive: { color: '#fff' },
  error: { color: colors.danger, fontSize: 13, paddingHorizontal: 16, paddingTop: 10 },
  empty: { color: colors.muted, textAlign: 'center', marginTop: 40 },
  card: {
    flex: 1,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 14,
    padding: 12,
  },
  thumb: {
    height: 78,
    borderRadius: 10,
    backgroundColor: '#eef1ec',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 10,
  },
  thumbText: { fontSize: 20, fontWeight: '800', color: colors.accent },
  saleBadge: {
    position: 'absolute',
    top: 6,
    left: 6,
    backgroundColor: colors.danger,
    borderRadius: 999,
    paddingHorizontal: 7,
    paddingVertical: 3,
  },
  saleBadgeText: { color: '#fff', fontSize: 9, fontWeight: '700' },
  cat: { fontSize: 10, color: colors.muted, textTransform: 'uppercase', letterSpacing: 0.5 },
  name: { fontSize: 14, fontWeight: '600', color: colors.ink, marginTop: 3, minHeight: 36 },
  cardBottom: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 8 },
  priceRow: { flexShrink: 1 },
  price: { fontSize: 15, fontWeight: '800', color: colors.ink },
  priceOnSale: { color: colors.danger },
  priceStrike: { fontSize: 11, color: colors.muted, textDecorationLine: 'line-through' },
  add: { backgroundColor: colors.brand, borderRadius: 8, paddingHorizontal: 12, paddingVertical: 7 },
  addText: { color: '#fff', fontSize: 12, fontWeight: '700' },
  addOff: { backgroundColor: 'transparent', borderWidth: 1, borderColor: colors.line },
  addOffText: { color: colors.muted, fontSize: 11, fontWeight: '700' },
});
