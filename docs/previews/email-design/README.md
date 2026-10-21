# Shared email design previews

Implemented 2026-10-21 following user approval. These are synthetic local previews, not delivered emails.

- [Verification code](otp.html) and [replacement code](otp-resend.html)
- [Membership verified](verified.html)
- [Verification correction](correction.html)
- [Single emergency opportunity](emergency-single.html)
- [Grouped emergency opportunities](emergency-group.html)
- [Escaping/long-content check](escaping.html)

Each preview has a corresponding `.txt` fallback. Regenerate with `C:/xampp/php/php.exe tests/email_templates_offline.php --previews`.

The shared presentation uses fluid table layouts, inline styling, escaped dynamic content and explicit PHT (UTC+08:00) emergency deadlines. It contains no website links or website buttons. OTP expiry comes from the existing service TTL. Verification reasons and next steps remain visible. Queue grouping, retry, accounting and eligibility rules are unchanged.

Validation: 39 offline presentation assertions, existing 21 worker policy and 20 experiment-scope checks pass. PHP syntax checks pass. No browser surface is connected; email-client visual review and fresh inbox samples for these new templates have not been performed. No emails were sent by these checks.

Requester names now appear first in single and grouped emergency cards, with missing-name fallback and HTML escaping. Approval emails use ?Your account is now verified? and explain administrator document review, verified-user features and donor enrollment. No website links were added.
