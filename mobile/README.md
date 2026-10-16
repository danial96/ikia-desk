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
| `src/app/` | Screens (Expo Router). `sign-in`, `update-required`, and `(app)/`: the tab bar `(tabs)/` (tasks, messenger, notifications, profile) plus `task/[id]` (detail), `task/[id]/edit`, `task/new`, `trash` |
| `src/api/tasks.ts` | React Query hooks for tasks, board, comments, checklist, trash, pick-lists |
| `src/components/tasks/` | `TaskRow`, `BoardView`, `Comment`, `TaskForm` |
| `src/lib/` | `time` (profile-zone clock, board columns), `richText` (message/description tags), `permissions`, `activity`, `files` (private download) |
| `src/api/client.ts` | The one `fetch` wrapper: bearer token, `X-App-Version`, errors (`ApiError`, `NetworkError`), 401 → sign out, 426 → update screen |
| `src/auth/` | `SessionProvider` (startup, sign in/out) and `tokenStore` (token + profile in Keychain/Keystore) |
| `src/lib/queryClient.ts` | React Query wired to NetInfo (offline) and AppState (refresh on return) |
| `src/components/ui.tsx` | `Screen`, `Button`, `TextField`, `Loading`, `Message`, offline banner |

## What works (Phase 2: tasks)

List (Active / Paused / Done, search, "Mine"), deadline board (hold a card to move it), task page (status, priority, deadline, responsible person,
description with photos/links, checklist, files, comments with reply / reactions / edit / delete / @mention, history), create and edit, Trash.
Not yet: attaching files or photos to a task or comment (comes with the chat media work), realtime updates (task pages refresh every 15 s).

Quick browser check without a phone: `EXPO_PUBLIC_API_URL=http://127.0.0.1:8000 npx expo start --web` (login works through a localStorage
stand-in for the secure store; the deadline field is a plain text box there).

## What works (Phase 3: messenger)

Chat list (unread badges, online dot, search), new chat / new group, the chat itself (day headings, replies, reactions, edit, delete for me / for
everyone, @mentions in groups, read/delivered ticks, failed-send retry, older messages on scroll-up, search inside a chat), photos / camera / files,
voice messages (record and play), and photos or files attached to a task or a comment. Updates arrive live over Pusher when the server has it on
(`/auth/me` returns `realtime_channel`); otherwise the screens poll (every 6 s in a chat, 20 s for the list).

## What works (Phase 4: push)

On a real phone (not Expo Go on Android, which has no remote push since SDK 53; use a development build) the app asks for notification permission,
registers its Expo push token, shows alerts for chat messages and task activity, and opens the right chat/task on tap. The icon badge is unread
chats + unread alerts. A message for the chat you are reading makes no banner. **One-off setup**: `eas login`, `eas init` (writes
`extra.eas.projectId` into app.json), then `eas credentials` / the first `eas build` creates the Apple push key and Google FCM key for you.

## Releasing (one-time setup, then per release)

You need an Expo account (free), and for stores: Google Play ($25 once) and/or Apple Developer ($99/year). Employees-only distribution works without stores:
an Android `.apk` from the website, and iPhone through TestFlight.

```bash
npm i -g eas-cli          # or: npx eas-cli@latest <command>
eas login
eas init                  # links the app to your Expo account, writes extra.eas.projectId into app.json
```

**Android app file (no Google Play needed)**

```bash
eas build --platform android --profile preview     # prints a download link for the .apk
```

Download it, put it on the server as `public/downloads/ikia-desk.apk` (the folder is git-ignored, so deploys never touch it) and set in the server `.env`:
`DESK_MOBILE_APK_URL=https://desk.ikiatech.com/downloads/ikia-desk.apk`. The sidebar's **Download app** button appears as soon as any link is set.

**iPhone / stores**

```bash
eas build --platform ios --profile production      # asks for your Apple login the first time and creates the certificates
eas submit --platform ios                          # uploads to App Store Connect / TestFlight
eas build --platform android --profile production  # an .aab for Google Play
eas submit --platform android
```

Then set `DESK_MOBILE_IOS_URL` (TestFlight or App Store link) and/or `DESK_MOBILE_ANDROID_URL` (Play Store link) in the server `.env`.

**Retire old builds**: raise `DESK_MOBILE_MIN_VERSION` in the server `.env`; older apps then show "Please update" with the store link.
Bump `version` in `app.json` for each release (build numbers are automatic).

## Notes

- **Do not add packages with `npm install`**, use `npx expo install <pkg>` so versions match the SDK. `.npmrc` sets `legacy-peer-deps`
  because Expo's optional peers (`react-native-worklets`) otherwise make npm refuse; `react-native-worklets` and `-reanimated` are pinned to
  Expo's blessed versions on purpose.
- Bundle ids are `com.ikiatech.desk` (iOS and Android). `eas init` links the project to an Expo account (not done yet).
- Release builds, store listing, app icon (the template icons are still the Expo defaults) and the website's "Download app" button are the release phase.
- Older builds are retired by the server: raise `DESK_MOBILE_MIN_VERSION` in the server `.env`.
