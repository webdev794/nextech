import React from 'react';
import { ActivityIndicator, View } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';

import { AppProvider, useApp } from './src/state';
import { colors } from './src/theme';
import AuthScreen from './src/screens/AuthScreen';
import OtpScreen from './src/screens/OtpScreen';
import CatalogScreen from './src/screens/CatalogScreen';
import ProductScreen from './src/screens/ProductScreen';
import CartScreen from './src/screens/CartScreen';
import CheckoutScreen from './src/screens/CheckoutScreen';
import PaymentScreen from './src/screens/PaymentScreen';
import OrdersScreen from './src/screens/OrdersScreen';
import SupportScreen from './src/screens/SupportScreen';
import SupportThreadScreen from './src/screens/SupportThreadScreen';
import RiderScreen from './src/screens/RiderScreen';
import DeliveryDetailScreen from './src/screens/DeliveryDetailScreen';

const Stack = createNativeStackNavigator();

const navTheme = {
  dark: false,
  colors: {
    primary: colors.brand,
    background: colors.bg,
    card: colors.brand,
    text: '#ffffff',
    border: colors.brand,
    notification: colors.accent,
  },
  fonts: {
    regular: { fontFamily: 'System', fontWeight: '400' },
    medium: { fontFamily: 'System', fontWeight: '500' },
    bold: { fontFamily: 'System', fontWeight: '700' },
    heavy: { fontFamily: 'System', fontWeight: '800' },
  },
};

function Root() {
  const { booting, user } = useApp();

  if (booting) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg }}>
        <ActivityIndicator size="large" color={colors.brand} />
      </View>
    );
  }

  return (
    <NavigationContainer theme={navTheme}>
      <Stack.Navigator screenOptions={{ headerTintColor: '#fff', headerStyle: { backgroundColor: colors.brand } }}>
        {user && user.is_rider ? (
          <>
            <Stack.Screen name="Deliveries" component={RiderScreen} options={{ title: 'Deliveries' }} />
            <Stack.Screen name="DeliveryDetail" component={DeliveryDetailScreen} options={{ title: 'Delivery' }} />
          </>
        ) : user ? (
          <>
            <Stack.Screen name="Catalog" component={CatalogScreen} options={{ title: 'NexTech' }} />
            <Stack.Screen name="Product" component={ProductScreen} options={{ title: 'Product' }} />
            <Stack.Screen name="Cart" component={CartScreen} options={{ title: 'Your cart' }} />
            <Stack.Screen name="Checkout" component={CheckoutScreen} options={{ title: 'Checkout' }} />
            <Stack.Screen name="Payment" component={PaymentScreen} options={{ title: 'Payment' }} />
            <Stack.Screen name="Orders" component={OrdersScreen} options={{ title: 'Your orders' }} />
            <Stack.Screen name="Support" component={SupportScreen} options={{ title: 'Support' }} />
            <Stack.Screen name="SupportThread" component={SupportThreadScreen} options={{ title: 'Conversation' }} />
          </>
        ) : (
          <>
            <Stack.Screen name="Auth" component={AuthScreen} options={{ headerShown: false }} />
            <Stack.Screen name="Otp" component={OtpScreen} options={{ title: 'Verify' }} />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}

export default function App() {
  return (
    <SafeAreaProvider>
      <StatusBar style="light" />
      <AppProvider>
        <Root />
      </AppProvider>
    </SafeAreaProvider>
  );
}
