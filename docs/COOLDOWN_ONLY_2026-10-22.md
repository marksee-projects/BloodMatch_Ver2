# Cooldown-only post-donation restriction

The user explicitly retired the 42-hour post-donation standby on 2026-10-22, retaining the 90-day cooldown. Eligibility now returns `cooldown` immediately after a confirmed donation, anchored to the recorded donation time. These 90 days do not include any additional 42-hour period.

Migration 038 sets the legacy standby setting to zero. It was applied only to local `bloodmatch:3306`, after recording settings and donor anchors in ignored `scratch/browser-check/before-retire-standby.json`. Existing cooldown duration and all donor anchors were preserved. Runtime also neutralizes the retired setting; the compatibility field remains zero. Historical phase9 tests and older standby documentation are superseded by this user amendment.

Completion no longer writes stored standby availability. Profile/auth responses display cooldown while restricted; an availability toggle cannot bypass it. Legacy standby values do not reintroduce the retired interval. Admin donor-status filters and the dashboard omit Standby. Previously retired non-response standby remains retired.

Mutual confirmation records the donation, releases the pending-report hold, and starts the donor's cooldown. Requester acknowledgment does not restrict the requester. Fulfillment remains quantity-aware and respects lifecycle rules: an open, unexpired one-unit request fulfills after one confirmed donation; a two-unit request remains open until two confirmed donations.

Two service-level scenarios tested these transitions inside main-database transactions and rolled everything back. They verified fulfillment, immediate cooldown, blocked availability changes, and unchanged requester anchors. No persistent fixture users, credits, or SMTP calls resulted. Twelve pure cooldown-boundary checks, eleven non-response retirement checks, and thirty-two donation-policy checks passed. PHP syntax and production frontend build passed, with the existing Vite bundle-size warning.
