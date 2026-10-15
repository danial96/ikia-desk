# IKIA Desk — mobile app

React Native + Expo (SDK 57, TypeScript, Expo Router). Talks to the same Laravel server as the website through the token API
at `/api/v1` — see [`../docs/mobile-api.md`](../docs/mobile-api.md). Tasks, chat and notifications are the same data as on the desktop.

## Run it

```bash
cd mobile
npm install
npx expo start
```

Open it in **Expo Go** (scan the QR) for quick looks. Anything with native code beyond Expo Go's bundle (push notifications, later)
needs a development build: `npx eas-cli@latest build --profile development --platform android`.

The server address comes from `app.json → expo.extra.apiBaseUrl` (production). To point at a local server, create `mobile/.env.local`:

```
EXPO_PUBLIC_API_URL=http://192.168.1.20:8000
```

(Use your computer's LAN address, a phone cannot reach `localhost`; start Laravel with `php artisan serve --host=0.0.0.0`.
Plain `http://` is fine in Expo Go and development builds, release builds need `https`.)

## Checks

```bash
npm run typecheck   # tsc
npm test            # jest (client, session, token store, formatting)
npx expo-doctor     # dependency / config health
```

## Layout

| Path | What |
|---|---|
| `src/app/` | Screens (Expo Router). `sign-in`, `update-required`, and `(app)/` tabs: tasks, messenger, notifications, profile |
| `src/api/client.ts` | The one `fetch` wrapper: bearer token, `X-App-Version`, errors (`ApiError`, `NetworkError`), 401 → sign out, 426 → update screen |
| `src/auth/` | `SessionProvider` (startup, sign in/out) and `tokenStore` (token + profile in Keychain/Keystore) |
| `src/lib/queryClient.ts` | React Query wired to NetInfo (offline) and AppState (refresh on return) |
| `src/components/ui.tsx` | `Screen`, `Button`, `TextField`, `Loading`, `Message`, offline banner |

## Notes

- **Do not add packages with `npm install`**, use `npx expo install <pkg>` so versions match the SDK. `.npmrc` sets `legacy-peer-deps`
  because Expo's optional peers (`react-native-worklets`) otherwise make npm refuse; `react-native-worklets` and `-reanimated` are pinned to
  Expo's blessed versions on purpose.
- Bundle ids are `com.ikiatech.desk` (iOS and Android). `eas init` links the project to an Expo account (not done yet).
- Release builds, store listing, app icon (the template icons are still the Expo defaults) and the website's "Download app" button are the release phase.
- Older builds are retired by the server: raise `DESK_MOBILE_MIN_VERSION` in the server `.env`.
