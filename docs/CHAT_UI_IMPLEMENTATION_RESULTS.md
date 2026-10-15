# Approved UI implementation - 15 October 2026

Branch: `feat/chat-privacy-account-lifecycle`. Items 1-9 of the current UI pass are implemented in source. Browser acceptance remains open; the computer-use inventory has no enabled browsers and opening the in-app browser returns `Browser is not available: iab`.

## Implemented

- Chat Reports: centered heading, styled status toolbar, separate loading/error/empty results, padded populated table, status badges, conditional pagination and grouped review/evidence controls. Existing moderation access and conflict rules remain enforced.
- Shared navigation: bell, plus, chat and central links use the same neutral hover surface and restrained icon animation. Active-page indicators and keyboard focus remain separate; motion respects reduced-motion settings.
- Chat: one shared input hover/focus treatment (single inset outline); full-width sidebar/thread layout filling the viewport below navigation with 8 px gutters and no visible page heading, independent scrolling, separated message requests, bottom composer and mobile list/thread navigation. Older history preserves the reading position. Initial full-chat history fetches the latest 100 messages.
- Profile: Expired posts and My requests render in the main content column using FeedCard. Expired cards label the previous local deadline; renewal fields and compact follow-up actions are spaced consistently. Renewal API semantics are unchanged.
- Account deletion: removed the large profile settings block. Desktop/mobile account menus open the centered confirmation dialog. Current password, DELETE, busy/error states, backend checks and the sole-admin restriction remain in place.
- Request wizard: increased field-group and stepper spacing across three steps and reduced footer/bottom padding.
- Unread counts: refresh on dropdown open and window focus, plus a five-second visible-page total poll. Only the signed-in account is monitored; simultaneous two-account testing requires isolated browser sessions. Listing conversations does not mark messages read. Hidden/minimized conversations retain their existing read protections.
- Message ownership: numeric sender comparisons in floating/full chat; own messages navy/right, received messages neutral/left. Dropdown previews identify outgoing messages with "You:". Both send surfaces include the displayed account ID as a precondition; the server rejects mismatches with HTTP 409 and still derives sender identity from the session. Historical messages were not relabelled.

## Checks completed

- Frontend Vite production build: passed. Existing bundle-size warning remains nonfatal.
- `node tests/request_ui_static.mjs`: 27 checks passed, including expired-tab placement, renewal/empty states, controlled deletion and sole-admin explanation, and report loading/empty presentation.
- `node tests/management_ui_offline.mjs`: passed (offline rendering/policy checks).
- `php -l backend/src/Controllers/ChatController.php`: passed.
- `powershell -ExecutionPolicy Bypass -File tests/run_isolated.ps1 -Suite chat_api.ps1`: 67 API assertions passed. Includes independent authenticated accounts, incoming unread counts, read cursors, consent, moderation, deletion enforcement and stale displayed-sender rejection. Fixtures removed and test server/environment restored.

No application account cleanup, password changes, migrations, historical sender edits, load tests or ERD work were performed in this UI pass. API tests used only `bloodmatch_test`.

## Browser acceptance still needed

Check `/admin/chat-reports`, `/chat`, `/profile?section=expired`, profile My requests, the account menus and all request-wizard steps. Verify desktop/mobile spacing, populated and empty states, long facility/message text, light/dark themes, keyboard focus, native date/time picker, reduced motion and hover while focused.

Use separate browsers or isolated browser profiles for Regular/Fire. Ordinary tabs share login cookies. Confirm recipient-only unread counts, opening the dropdown without clearing them, reading/minimizing/hiding a thread, replies on opposite sides, history loading and switching accounts in another tab. The API checks do not replace these browser interactions.

From the repository root, optional repeat checks:

```powershell
node tests/request_ui_static.mjs
node tests/management_ui_offline.mjs
Set-Location frontend
npm run build
```

The broader lifecycle plan still has separate scheduling, physical erasure/hold and historical-data acceptance work described in the rollout documents. This UI pass does not close those operational checkpoints.

## Compact report/card follow-up - 15 October

Approved follow-up implemented: report list heading removed and toolbar reduced; opening Review replaces the list with a two-column evidence/decision view (stacked on mobile), using current member names from the authorized evidence endpoint and a secondary case reference. Evidence access auditing and conflict restrictions remain unchanged. Resolve records status/findings only and does not alter account access.

Request-card controls are collapsed behind an equal-width action row. Renewal opens separate date/time inputs; closure opens a Reason for closing dropdown and retains confirmation. The status badge is compact. Removed manual donation follow-up from the card after verifying the separate My Responses completed-donation reporting path. The backend follow-up endpoint remains unchanged.

Latest checks: build passed, 29 static rendering checks passed, 68 isolated chat API checks passed (including reporter/sender names and protected evidence), PHP syntax passed, git diff whitespace check passed. No browser surface is available; visual/keyboard/picker acceptance remains pending. No application-account changes or migrations were made.
