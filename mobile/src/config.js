import Constants from 'expo-constants';

// In Expo Go, Expo tells us which machine is serving the app (Metro's host).
// The Laravel API runs on that same machine, on port 8000 — so we can point at it
// automatically, whether you're on a physical phone or an emulator.
function devHostApiUrl() {
  const hostUri =
    Constants.expoConfig?.hostUri ||
    Constants.expoGoConfig?.debuggerHost ||
    Constants.manifest2?.extra?.expoClient?.hostUri ||
    '';
  const host = hostUri.split(':')[0];
  return host ? `http://${host}:8000/api` : null;
}

// Priority: explicit env override → auto-detected dev host → app.json → emulator default.
export const API_URL =
  process.env.EXPO_PUBLIC_API_URL ||
  devHostApiUrl() ||
  Constants.expoConfig?.extra?.apiUrl ||
  'http://10.0.2.2:8000/api';
