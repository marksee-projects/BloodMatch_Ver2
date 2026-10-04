# BloodMatch Design System

> **Source of truth for the BloodMatch visual system.**  
> Follow this document for all UI decisions. For redesign phases and roadmap, see [`UI_REDESIGN_PLAN.md`](./UI_REDESIGN_PLAN.md).

---

## 1. Principles & Emotion
- **Tone & Emotion:** Calm, capable, community.
- **Design Philosophy:** Apple-like precision, restraint, and high contrast. Medical emergencies require clarity, calmness, and trustworthiness rather than flashy decoration.
- **Non-Negotiables:**
  - No gradients, glows, colored card top-borders, or decorative shapes.
  - No emoji as UI icons. Use Lucide icons (`lucide-react`, stroke width 1.75).
  - Red is strictly reserved for critical/emergency states, destructive actions, and errors.
  - Navy is the primary brand and action color.
  - Support light and dark mode through unified design tokens.

---

## 2. Typography
- **Font Family:** `Inter` only (`'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif`).
- **Tracking:**
  - Headings: `-0.02em`
  - Body: `0`
- **Line Heights:**
  - Headings: `1.1` – `1.2`
  - Body: `1.5`
- **Type Scale:**

| Token | Size | Line Height | Tracking | Usage |
|---|---|---|---|---|
| `hero` | 34px | 1.15 | -0.02em | Page titles, key hero headers |
| `h1` | 24px | 1.2 | -0.02em | Section titles, modal headers |
| `h2` | 20px | 1.2 | -0.02em | Card titles, group headers |
| `h3` | 18px | 1.25 | -0.02em | Subsections, item names |
| `body` | 15px | 1.5 | 0 | Standard body copy, inputs, buttons |
| `sm` | 13px | 1.4 | 0 | Secondary metadata, hints, timestamps |

- **Conventions:**
  - Sentence-case labels across all UI elements (no all-caps tracked labels).
  - No monospace font for decorative text or breadcrumbs.
  - Tabular numerals (`font-variant-numeric: tabular-nums`) for counts, dates, and metrics.

---

## 3. Color Palette & Contrast
All text meets or exceeds WCAG AA contrast ratio (>= 4.5:1).

| Token | Light Mode | Dark Mode | Usage |
|---|---|---|---|
| `color-page` | `#E9ECF2` | `#0B0F19` | Application background behind cards |
| `color-surface` | `#FFFFFF` | `#151E2E` | Cards, modals, elevated surfaces |
| `color-surface-sunken` | `#F1F5F9` | `#0F172A` | Inset areas, input backgrounds |
| `color-text` | `#0F172A` | `#F8FAFC` | Primary headings and body text (slate-900 / slate-50) |
| `color-text-secondary` | `#475569` | `#94A3B8` | Supporting metadata, field labels (slate-600 / slate-400) |
| `color-text-tertiary` | `#64748B` | `#64748B` | Subtle hints, placeholder text (slate-500) |
| `color-border-hairline` | `rgba(15,23,42,.12)` | `rgba(255,255,255,.12)` | 1px clean hairlines on cards/dividers |
| `color-brand-navy` | `#020066` | `#4338CA` | Primary actions, brand accents, active state |
| `color-brand-navy-hover` | `#010052` | `#3730A3` | Primary button hover |
| `color-critical-red` | `#C8102E` | `#EF4444` | Emergency requests, destructive actions, errors |
| `color-critical-bg` | `#FFF5F5` | `#450A0A` | Critical card background & tints |
| `color-urgent-amber` | `#9A3412` | `#F97316` | Urgent requests, warning badges |
| `color-urgent-bg` | `#FFEDD5` | `#431407` | Urgent badges and banners |
| `color-success-green` | `#1F8A4C` | `#22C55E` | Verified donor status, "You can help" badges |
| `color-focus-ring` | `#020066` | `#6366F1` | 2px solid accessible focus outline |

---

## 4. Spacing, Shapes & Grid
- **Base Grid:** 8px rhythm.
  - `4px` (`xs`): tight padding inside chips or badges.
  - `8px` (`sm`): gap between icon and label, tight item lists.
  - `16px` (`md`): padding inside groups and cards, spacing between form fields.
  - `24px` (`lg`): spacing between cards and grid columns.
  - `32px` (`gutter`): main page padding and section margins.
- **Corner Radii:**
  - Controls (`--radius-control`): `12px` (buttons, inputs, select menus).
  - Cards (`--radius-card`): `16px` (content cards, dialogs, dropdown panels).
  - Avatars (`--radius-avatar`): `9999px` (full circle).
- **Card Elevation:**
  - Background: White (`#FFFFFF`).
  - Border: 1px hairline (`rgba(15,23,42,.12)`).
  - Box Shadow: `0 1px 2px rgba(15,23,42,.06), 0 6px 16px rgba(15,23,42,.06)`.

---

## 5. Components & Interactive Controls
- **Buttons:**
  - Minimum height: `44px` (touch target).
  - Typography: `15px` / `600` weight.
  - Primary: Solid brand navy (`#020066`) with white text. Exactly one loud action per card or screen.
  - Secondary: White surface with 1px hairline border and `#0F172A` text.
  - Destructive: `#C8102E` solid or outline, used only for critical cancellations or emergency actions.
- **Iconography:**
  - Lucide icons (`lucide-react`), stroke width `1.75px`.
  - Icon-only buttons must include `aria-label` and a descriptive tooltip.
- **States Required:**
  - Default, Hover, Focus, Pressed (`scale(0.97)` at 100ms), Disabled (`opacity: 0.45`, `pointer-events: none`), Error, Loading (`is-loading` with spinner or skeleton).

---

## 6. Signature BloodMatch Elements
- **`BloodTypeBlock`:**
  - 44px rounded square (`12px` radius).
  - Blood type letter prominently in bold.
  - Tints:
    - Normal: Navy tint background with navy text.
    - Urgent: Amber background (`#FFEDD5`) with amber text (`#9A3412`).
    - Critical: Solid red (`#C8102E`) background with white text.
- **`DropletProgress`:**
  - Visual indicator showing units needed vs. collected (one droplet icon per unit).
  - Filled navy (`#020066`) in normal states; filled critical red (`#C8102E`) on emergency requests.
  - Animated fill transition on mount/update.

---

## 7. Motion & Physics
- **Spring Physics:** Physical springs with damping ratio `1.0`, duration `~0.3s` (bounce only after a flick/drag).
- **Interactions:**
  - Button press: `transform: scale(0.97)` over 100ms.
  - Menus/Dropdowns: Scale and fade originating from the trigger element.
  - Navigation header: Translucent blur (`backdrop-filter: blur(12px)`).
- **Reduced Motion:** Fully respect `@media (prefers-reduced-motion: reduce)` and `@media (prefers-reduced-transparency: reduce)`. Disable scaling/spring transforms and fallback to instant state changes or subtle opacity fades.

---

## 8. Accessibility Standards
- **Touch & Click Targets:** Minimum 44px × 44px for all interactive elements.
- **Keyboard Navigation:**
  - Clear, visible `2px` solid navy focus ring with `2px` offset (`:focus-visible`).
  - Logical tab hierarchy across forms and modal dialogues.
- **Information Conveyance:** Never use color alone to communicate status, eligibility, or urgency. Always pair colors with clear textual labels and descriptive icons.
- **Scalability:** Layouts and typography must survive 130% text scaling without layout breakage or clipping.
