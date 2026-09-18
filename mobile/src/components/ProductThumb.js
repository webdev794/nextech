import React, { useState } from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';
import { mediaUrl } from '../mediaUrl';
import { colors } from '../theme';

// Renders the product's real image when it has one and it loads; falls back
// to a two-letter initials tile (the app's original placeholder) otherwise —
// no image_url, or the request fails (e.g. offline, broken path).
export default function ProductThumb({ name, imageUrl, style, textStyle }) {
  const [failed, setFailed] = useState(false);
  const resolved = mediaUrl(imageUrl);

  if (resolved && !failed) {
    return (
      <Image
        source={{ uri: resolved }}
        style={[styles.image, style]}
        resizeMode="contain"
        onError={() => setFailed(true)}
      />
    );
  }

  const initials = (name || '').split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();

  return (
    <View style={[styles.fallback, style]}>
      <Text style={[styles.fallbackText, textStyle]}>{initials}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  image: { backgroundColor: '#eef1ec' },
  fallback: { alignItems: 'center', justifyContent: 'center', backgroundColor: '#eef1ec' },
  fallbackText: { fontSize: 20, fontWeight: '800', color: colors.accent },
});
