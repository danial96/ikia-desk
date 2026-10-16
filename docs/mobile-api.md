# Mobile app API (`/api/v1`)

For the React Native app. Same server, same database and same rules as the website — the app is only another way in.

## How it works

- **Auth:** `POST /api/v1/auth/login` returns a `token`. Send it on every call as `Authorization: Bearer <token>`.
  One token per phone; keep it in Keychain / Keystore. Only its sha256 is stored on the server.
- **No cookies, no CSRF, no session.** The API never sets a cookie.
- **JSON only.** Validation errors are `422 {"message", "errors"}`; permission errors `403`; a missing/bad/revoked token `401`.
- **Same handlers as the website.** `routes/api.php` mounts the website's own controllers, and `routes/shared_api.php` /
  `routes/shared_files.php` are loaded by *both* `web.php` (session) and `api.php` (token). A rule changed once applies to both.

## Headers

| Header | Why |
|---|---|
| `Authorization: Bearer <token>` | every call except `app/config`, `auth/login`, `auth/forgot-password` |
| `X-App-Version: 1.2.0` | lets the server retire old builds: older than `DESK_MOBILE_MIN_VERSION` gets **426** `{"error":"update_required","store":{...}}` |

Rate limit: 300 requests/minute per phone. Login: 5 wrong passwords per email+IP per minute (**429**, `retry_after`).

## Errors the app must handle

| Status | `error` | Do |
|---|---|---|
| 401 | `unauthenticated` | token revoked/unknown → sign out, show login |
| 401 | `deactivated` | account switched off → sign out, show the message |
| 426 | `update_required` | show the "update the app" screen with `store.android` / `store.ios` |
| 429 | `too_many_attempts` | wait `retry_after` seconds |

A new password (reset or profile change on the website) signs **every** phone out.

## Endpoints

### Session & device
| | |
|---|---|
| `GET  app/config` | open. `min_version`, `latest_version`, `store`, `realtime` (Pusher key+cluster or null), `server_time` |
| `POST auth/login` | `{email, password, device_name?, platform?: ios\|android, app_version?}` → `{token, user}` |
| `GET  auth/me` | `{user}` — includes `can.*` (what buttons to show) and `notifications.*` |
| `POST auth/logout` | revokes this phone's token and its push registration |
| `POST auth/forgot-password` | `{email}` — always the same answer; the emailed link opens the website's reset page |
| `POST devices` / `DELETE devices` | `{push_token, platform, app_version?}` / `{push_token}` — FCM registration |
| `POST me/notifications` | `{notify_messages, notify_messages_sound, notify_tasks, notify_tasks_sound}` — **send all four** (a missing one is saved as off) |
| `POST realtime/auth` | Pusher private-channel auth: `{socket_id, channel_name}`; only your own channel `private-[prefix]user.<id>` |

### People & projects
`GET users` (active people: id, name, position, avatar) · `GET projects`

### Tasks
| | |
|---|---|
| `GET tasks` | `?status&search&project_id&priority&assignee_id&per_page(≤50)&page` → `{data:[card], meta:{page,per_page,total,has_more}}`. `status=in_progress` also covers new/pending/reviewing, as on the website. Newest activity first. |
| `GET tasks/board` | kanban columns (`overdue, due_today, due_this_week, due_next_week, no_deadline, due_over_two_weeks, completed`). Same filters. Completed is capped at the newest 200 — use `GET tasks?status=completed` for the full paged list. |
| `GET tasks/board/version` | cheap change marker |
| `GET local-task/{id}` | one task in full: fields, people, **feed** (comments + activity), checklist, files, viewed-by, `canEdit` |
| `POST tasks` | create (`title, priority` required; needs the create-tasks permission) |
| `PUT tasks/{id}` | edit |
| `PATCH tasks/{id}/field` | `{field: assigned_to\|deadline\|status\|priority\|project_id, value}` |
| `PATCH tasks/{id}/move` | `{status?, deadline?}` |
| `POST tasks/{id}/participants/toggle`, `.../observers/toggle` | `{user_id}` |
| `DELETE tasks/{id}` | to Trash |
| `GET tasks/trash`, `POST tasks/{id}/restore`, `DELETE tasks/{id}/force` | Trash |
| `POST local-task/{id}/comment` | `{content, mentions?[ids], parent_id?}` — attachments travel inside `content` as `[img]`/`[file]` tags (upload first) |
| `PATCH/DELETE local-task/comments/{id}`, `POST local-task/comments/{id}/react` | edit / delete / `{emoji}` |
| `POST local-task/{id}/checklist`, `PATCH/DELETE local-task/{id}/checklist/{item}` | checklist |
| `POST local-task/{id}/attach` | multipart `file` |
| `GET local-task/{id}/files-zip`, `POST download-zip` | zips |

### Chat
`GET chat/convs` · `GET chat/convs/{id}/msgs` (`?after=<id>` new ones, `?before_ts=&before_id=` older, 50 per page) ·
`POST chat/convs/{id}/send` `{content, mentions?[], parent_id?}` · `PATCH/DELETE chat/msgs/{id}` · `POST chat/msgs/{id}/react` `{emoji}` ·
`POST chat/convs/{id}/leave` · `GET chat/convs/{id}/about` · `GET chat/convs/{id}/search?q=` ·
`POST chat/direct` `{user_id}` · `POST chat/group` · `GET employees-list` · `GET online-status`

While the app is open, call `GET chat/convs` about every 20 s: that heartbeat is what drives the other person's "online" dot and "delivered" tick.

### Notifications
`GET notifications` · `POST notifications/{id}/read` · `POST notifications/read-all` · `POST notifications/task/{taskId}/read`

### Files
- `POST upload` (multipart `file`, ≤ 50 MB) → `{url, name, mime, ext}`.
- **File URLs inside messages/comments are website-form** (`https://desk.ikiatech.com/uploads/up_x.png/Name.png`). The website route needs a
  browser session, so the app must fetch them as `https://desk.ikiatech.com/api/v1/uploads/up_x.png/Name.png` **with the bearer header**
  (rewrite the path: `/uploads/` → `/api/v1/uploads/`). Avatars (`/storage/...`, `/avatar-initials`) are public and need no header.

## Push notifications (phones)

The app registers its **Expo push token** (`ExponentPushToken[...]`) with `POST devices`. The server (`App\Support\MobilePush`) POSTs to Expo's push
service, which delivers through Apple/Google, so the server holds no Firebase or Apple secrets. It is triggered from the same places as browser
push (`App\Support\Push` fans out to both): new chat messages, mentions/replies, task alerts. People's "messages" / "tasks" notification switches
apply. Voice-call rings are not sent to the app. Each push carries `data`:

| key | meaning |
|---|---|
| `conversation_id` | open `/chat/{id}` |
| `task_id` | open `/task/{id}` |
| `url`, `type` | the website-style address and `message` / `task` |

Android channels: `messages` (high) and `tasks`. A push for a chat or task replaces the previous one (`collapseId`). A phone Expo reports as
`DeviceNotRegistered` is removed (only visible when the server sends synchronously; in production sending is a detached `curl`, so dead phones are
dropped by the 90-day cleanup and on logout).

## Message text format (chat and task comments)

`content` is plain text with tags the app must render:
`[img]URL[/img]` photo · `[file name="x.pdf"]URL[/file]` file · `[voice ...]URL[/voice]` voice note · `@mentions` come separately as ids in `mentions`.
`WebPush::snippet()` shows the text rules for previews.

## Times

Chat messages carry `createdTs` (unix seconds) — format it on the phone in `user.time_zone`. Task `deadline` is ISO-8601 in the viewer's own zone.
Board cards carry pre-formatted deadline strings (`deadline`, `dl`) in the viewer's zone as well.

## Not in the app (v1)

Calls (`/api/calls/*` stay website-only), employees/permissions/projects management, the payment terminal.
