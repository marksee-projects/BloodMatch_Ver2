# BloodMatch CSS Architecture & Refactoring Status

**Document Version:** 1.0  
**Status:** In Progress / Iterative Modularization Active  
**Author:** Expert React Refactoring Engineer  
**Date:** September 17, 2026  

---

## 1. Executive Summary & File Metrics

| Metric | Before Refactor | Current Status | Net Delta |
| :--- | :--- | :--- | :--- |
| **`global.css` Total Lines** | 1,400 lines | **1,041 lines** | **-359 lines (-25.6%)** |
| **Hardcoded Hex Colors in `global.css`** | 3 instances (`#fff`, `#10b981`) | **0 instances** | **100% Tokenized** |
| **Design Tokens in `tokens.css`** | 96 lines | **117 lines** | **+21 lines (Light & Dark tokens)** |
| **Modular CSS Files (`*.module.css`)** | 0 files | **3 files** | `PrivacyConsentModal`, `PortalLayout`, `AnalyticsPage`, `Hero` |
| **Production Build Status** | Passing | **Passing (`vite build` in 4.99s, 0 errors)** | Clean compilation |

---

## 2. Changes Implemented

### Phase 1: Token Extraction & Centralization (`tokens.css`)
Hardcoded values and layout magic numbers were identified, extracted into semantic design tokens, and mapped across both light and dark modes:

1. **Backdrop & Modal Tint Tokens**:
   - `light`: `--color-backdrop: rgba(8, 10, 12, 0.62);`, `--color-backdrop-heavy: rgba(0, 0, 0, 0.65);`
   - `dark`: `--color-backdrop: rgba(0, 0, 0, 0.75);`, `--color-backdrop-heavy: rgba(0, 0, 0, 0.85);`
2. **Operational Status Tokens**:
   - `light`: `--color-status-active: #10b981;`, `--color-status-active-glow: rgba(16, 185, 129, 0.6);`
   - `dark`: `--color-status-active: #34d399;`, `--color-status-active-glow: rgba(52, 211, 153, 0.5);`
3. **Responsive Dimension Tokens**:
   - `--topbar-height: 3.5rem;` (56px)
   - `--sidebar-width: 16.25rem;` (260px)
   - `--sidebar-width-collapsed: 4.75rem;` (76px)
   - `--modal-max-width: 37.5rem;` (600px)
   - `--touch-target-min: 2.25rem;` (36px)
4. **Replacements in `global.css`**:
   - `.btn-danger:hover`: Replaced `#fff` with `var(--color-accent-fg)`.
   - `.badge-critical`: Replaced `#fff` with `var(--color-accent-fg)`.
   - `.modal-backdrop`: Replaced hardcoded `rgba(8, 10, 12, 0.62)` with `var(--color-backdrop)`.

---

### Phase 2: True Globals Isolation
The remaining ~1,040 lines in `global.css` have been audited to ensure they represent **true globals** that belong at the application level:

1. **Base Reset & Normalization (Lines 1–63)**: Universal box-sizing, smooth scrolling, `prefers-reduced-motion` overrides, body defaults, and accessibility `.skip-link`.
2. **Typography Hierarchy (Lines 64–168)**: Semantic styling for `h1`–`h6` using `Source Serif 4`, body copy typography using `Geist`, inline `code`, `kbd`, `pre`, and utility text classes.
3. **Shared Design System Primitives (Lines 487–1070)**:
   - Reusable buttons: `.btn`, `.btn-secondary`, `.btn-sm`, `.btn-lg`, `.btn-danger`
   - Surfaces & Cards: `.card`, `.card--halftone`, `.card--interactive`
   - Form fields: `.field`, `.field-hint`, `.field-error`, `label`, `input`, `select`, `textarea`
   - Badges & Chips: `.badge`, `.badge-verified`, `.badge-self-reported`, `.badge-urgent`, `.badge-critical`
   - Alert notifications: `.alert`, `.alert-error`, `.alert-success`, `.alert-info`
   - Denser Data Tables: `.table-wrap`, `.table`
   - Loading skeletons & animations: `.skeleton`, `@keyframes skeleton-pulse`, `.spinning`
4. **App Macro Shell Layouts (Lines 169–217)**:
   - `.container`, `.container.narrow`, `.grid-2`, `.grid-3`, `.main--portal`

---

### Phase 3: Component Modularization (`*.module.css`)

Four component clusters have been successfully extracted into CSS Modules:

#### 1. `PrivacyConsentModal`
- **New Module**: `frontend/src/components/PrivacyConsentModal.module.css`
- **Updated Component**: `frontend/src/components/PrivacyConsentModal.jsx`
- **Removed from `global.css`**: Lines 1294–1376 (`.privacy-shield-overlay`, `.privacy-consent-modal`, `@keyframes modalPop`, `.privacy-modal-header`, `.privacy-items-list`, `.privacy-item`, `.privacy-modal-actions`).
- **Integration**: Wired into `RegisterPage.jsx` so unconsented users encounter this modal before registering.

#### 2. `PortalLayout` (Admin & Officer Dashboard Shell)
- **New Module**: `frontend/src/components/PortalLayout.module.css`
- **Updated Component**: `frontend/src/components/PortalLayout.jsx`
- **Removed from `global.css`**: Lines 1109–1292 (`.portal-container`, `.portal--collapsed`, `.portal-sidebar`, `.portal-nav`, `.portal-collapse-btn`, `.portal-status-dot`, `.portal-main-content`, etc.).
- **Benefits**: Fixed full-width docking on ultra-wide monitors, eliminated layout bleed, and isolated collapsible state transitions.

#### 3. `AnalyticsPage` Presets Toolbar
- **New Module**: `frontend/src/pages/AnalyticsPage.module.css`
- **Updated Component**: `frontend/src/pages/AnalyticsPage.jsx`
- **Removed from `global.css`**: Lines 1378–1392 (`.analytics-presets-bar`, `.tab-group`).
- **Benefits**: Self-contained quick-switch interval tabs for Daily, Weekly, Monthly, and Yearly operational reports.

#### 4. `Hero` (Landing Page Editorial Hero Section)
- **New Module**: `frontend/src/components/Hero.module.css`
- **New Component**: `frontend/src/components/Hero.jsx`
- **Updated View**: `frontend/src/App.jsx` (`HomePage`)
- **Removed from `global.css`**: Lines 671–743 (`.hero`, `.hero-kicker`, `.hero-title`, `.hero-lede`, `.hero-actions`) and related responsive rules.

---

## 3. Next Candidate Clusters for Future Iterations

For teammates continuing the refactoring:

| Cluster Candidate | Estimated Lines in `global.css` | Target Destination | Notes |
| :--- | :--- | :--- | :--- |
| **Topbar & Nav Header** | ~268 lines (Lines 218–486) | `src/components/Topbar.module.css` | Extract `<header className="topbar">` from `App.jsx` into a dedicated `<Topbar />` component. |
| **National ID Upload Dropzone** | ~35 lines | `src/pages/RegisterPage.module.css` | Image preview thumbnail, PDF badge, upload dash-border. |
| **Date-of-Birth Calendar Widget** | ~40 lines | `src/components/CalendarPicker.module.css` | Date input styling with live age pill. |

---

## 4. Engineering Rules for Teammates

1. **Never dump page-specific styles into `global.css`**: If a style is used by only one page or component, create `[ComponentName].module.css` next to the `.jsx` file.
2. **Never hardcode hex colors**: Always check `tokens.css`. If a new semantic role is needed, add the variable to both `:root, [data-theme='light']` and `[data-theme='dark']`.
3. **Importing CSS Modules**: Use `import styles from './[ComponentName].module.css'` and access classes via `className={styles.className}`.
4. **Verification**: Always run `npm run build` after editing CSS to ensure zero bundler or PostCSS syntax errors.
