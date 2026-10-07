# BloodMatch Bataan: Full UI/UX Redesign Plan

CONTEXT
BloodMatch Bataan is a React + Vite + Tailwind frontend with a PHP/MySQL backend (capstone project for the DeMolay community). Goal: the UI currently looks AI-generated and inconsistent. Redesign it into one calm, precise, Apple-like system: purposeful, high-contrast, trustworthy for medical emergencies. Emotion to convey: calm, capable, community.

NON-NEGOTIABLES
- Do NOT change backend calls, routes, auth logic, data shapes or database. Presentation only. If a design needs data we don't have (distance, "You can help"), add a clearly marked TODO and a typed placeholder; never fake data.
- One source of truth: src/tokens.ts + src/index.css. No hardcoded colors, sizes, radii or shadows in pages.
- No emoji as icons (use lucide-react, stroke 1.75). No gradients, glows, colored card top-borders or decorative shapes.
- Red is only for emergency state and errors. Navy is the brand and primary action.
- Support dark mode (the app already has a toggle) through the same tokens.
- Work ONE phase per session. Touch only the files the phase needs. End every phase with the verification table below.

DESIGN SYSTEM (already decided)
- Font: Inter only. Headings -0.02em tracking, 1.1-1.2 line-height; body 0 tracking, 1.5 line-height; tabular numerals for counts. Scale: 34/24/20/18/15/13. Sentence-case labels, no all-caps tracked labels, no monospace decoration.
- Color: page #E9ECF2, surface #FFFFFF, text #0F172A, secondary #475569, tertiary #64748B (hints only), hairline rgba(15,23,42,.12), brand navy #020066, emergency red #C8102E, urgent amber #9A3412 on #FFEDD5, success green #1F8A4C. All text >= 4.5:1.
- Shape: radius 12 controls, 16 cards, avatars full. 8px grid: 16 inside groups, 24 between cards, 32 gutters. Card = white, 1px hairline, shadow 0 1px 2px rgba(15,23,42,.06), 0 6px 16px rgba(15,23,42,.06).
- Buttons: 44px height, 15px/600. Primary navy solid (one loud action per card/screen), secondary white with border.
- Motion: springs, damping 1.0, ~0.3s (bounce only after a flick/drag). Press = scale .97 at 100ms. Menus scale from their trigger. Respect prefers-reduced-motion and prefers-reduced-transparency. Header uses translucent blur.
- Signature elements: BloodTypeBlock (44px rounded square, blood type in bold: navy tint normal, amber urgent, solid red emergency) and DropletProgress (one droplet per unit needed; filled navy, red on emergency; animated fill).
- Copy: direct, human, specific; no exclamation marks or hype. Errors say what happened and how to fix it. Nav labels are specific ("Requests", not "Home").
- Accessibility: 44px targets, visible 2px navy focus ring, never color alone for urgency, layout survives 130% text.

PHASE 0: AUDIT (skill: design-system)
Scan the frontend. List every hardcoded color/size/radius and every duplicated Button/Card/Input/Badge. Create/update src/tokens.ts and write a 1-page DESIGN.md that records the decisions above. Add one line to AGENTS.md: "Follow DESIGN.md for all UI." Output a table of findings by file. No page redesigns yet.

PHASE 1: SHARED COMPONENTS (skills: apple-design, frontend-design)
Build src/components/ui/: Button, Card, Input, Select, Segmented, Toggle, Badge, StatusPill, BloodTypeBlock, DropletProgress, Avatar, IconButton (with tooltip + aria-label), Toast, Skeleton, EmptyState, Header. Every state: default, hover, focus, pressed, disabled, error, loading.

PHASE 2: APP SHELL + LOGGED-IN HOME
- Header: wordmark left; icon nav (Requests, Emergency, Alerts) with the active item as icon + label pill; avatar menu right (holds Profile, My Requests, Admin Portal for admins, dark mode, Log out). Translucent, 64px.
- Home: left sticky column (profile card, availability toggle, quick links: Donation drives, Donor registry, Chapter directory, Donate supplies); center feed; right column (suggested donors, network stats, Red Cross helpline).
- Center top: composer with two tabs, "Request blood" and "Find donors" (blood type + municipality selectors, results below). Then a context line ("12 requests near Orani, 4 match your blood type"), a segmented filter (All, Match N, Emergency, Near me), then request cards.
- Request card: BloodTypeBlock, patient, hospital, meta (municipality, time, distance), DropletProgress + "1 of 2 units". Matching cards get a green "You can help" label and a primary button; others get a secondary button. Emergency = #FFF5F5 surface + border and a pulsing red dot. New requests slide in with a "1 new request" pill.

PHASE 3: LANDING PAGE (skills: frontend-design, ux-copy)
Route "/" shows Landing when logged out and redirects to Home when logged in. One screen: headline, one sentence, Register and Log in buttons, map as the visual. Remove emoji chips and admin buttons. Same Inter type system as the app.

PHASE 4: AUTH (skills: apple-design, ux-copy, accessibility-review)
- Login: centered card, inline validation, show/hide password.
- Register: 3 steps with a slim stepper: (1) Account (2) About you (blood type as an 8-option grid, nothing preselected, chapter as a select fed by the chapters list) (3) Verify ID (dropzone with thumbnail preview, OCR result state, consent checkbox UNCHECKED by default). Steps slide along one axis; Back never clears data; only the last step submits. Validate on blur.

PHASE 5: REMAINING PAGES
My Requests, Profile, Emergency, Alerts, Demand Map, Donor registry, Chapter directory, Donation drives, Donate supplies. Reuse Phase 1 components only; no new one-off styles.

PHASE 6: ADMIN PORTAL
Overview, Regional Demand, Analytics, Audit Logs. Dense but calm: data tables with sticky headers, 44px rows, neutral colors, clear filters, chart colors limited to navy/slate + one red for emergency. Audit logs are read-only with timestamp, actor and action.

PHASE 7: FINAL REVIEW (skills: design-critique, accessibility-review)
Review every page against this plan. List each text/background pair under 4.5:1 and fix it. Check keyboard navigation and focus order, empty/loading/error states, dark mode, 375px mobile width, and prefers-reduced-motion.

RULES FOR EVERY PHASE
- Name the skill(s) in use and confirm the SKILL.md path you loaded.
- Do not read or edit files outside the phase's scope.
- End with this table, so I can confirm each file saved correctly:
| File | What changed | Distinctive string to search for | Found? |
