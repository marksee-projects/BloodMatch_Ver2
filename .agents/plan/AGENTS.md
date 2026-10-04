# BloodMatch Agent Rules & Developer Guidelines

This file is the instruction guide for AI coding agents working on the BloodMatch repository.

---

## 1. Project Scope & Sources of Truth
- **Application Overview:** BloodMatch is a peer-to-peer blood donor matching platform for the DeMolay community (Bataan) built with a React + Vite + Tailwind frontend and a PHP/MySQL backend (MySQL on port **3307**, running in XAMPP; do not use Laravel unless instructed).
- **Functional Requirements Source of Truth:** `docs/ai_context/CONTEXT.md` defines what BloodMatch is required to do. Where any section conflicts with §9 (Finalized BloodMatch Business Rules), §9 prevails.
- **Visual System Source of Truth:** `DESIGN.md` is the source of truth for all UI/UX decisions, typography, color palettes, spacing, and interaction patterns. Follow `DESIGN.md` for all UI work.
- **Redesign Roadmap Source of Truth:** `UI_REDESIGN_PLAN.md` defines the phases (0–7) and exact scope of each frontend redesign step.

---

## 2. Phase Discipline
- Work **only** on the currently requested phase or task.
- Do not continue into later phases unless explicitly requested.
- Do not modify unrelated files outside the active phase scope.
- Preserve work already completed in previous phases.
- Do not redesign or refactor existing features unless the current task requires it.
- **Do not change backend calls, API endpoints, routes, authentication logic, data shapes, or database schema during presentation/UI work** unless explicitly requested.
- Do not invent data that the application does not provide. When required data is unavailable, use a clearly marked `TODO` or typed placeholder instead of fake data.

---

## 3. Before Editing
Before editing any existing file:
1. Check that the file exists and inspect its current implementation.
2. Read the relevant contents and understand how it connects to surrounding components and services.
3. Check whether an existing reusable component or utility already solves the problem.
4. Check `DESIGN.md` and relevant design tokens before introducing visual decisions.
5. **Never** silently overwrite, replace, or discard existing work.

---

## 4. Design System & UI Rules
- `src/tokens.ts` and `src/index.css` (and modular `src/styles/` sheets) are the primary visual token sources.
- Follow `DESIGN.md` for colors, typography, spacing, radii, shadows, motion, and interaction behavior.
- Do not hardcode visual values (colors, font sizes, radii, padding) when an established token exists.
- Do not create a competing token system.
- Reuse existing shared components (`src/components/ui/`) before creating new components.
- Do not create one-off visual patterns when an existing component or pattern can be reused.
- **Red (`#C8102E`)** is reserved strictly for critical, emergency, destructive, and error states.
- **Navy (`#020066`)** is the primary BloodMatch brand and action color.
- Do not use emoji as UI icons. Use the project's existing icon library (prefer `lucide-react`, stroke width 1.75).
- Preserve light and dark mode using the established token system.
- Do not introduce gradients, glows, decorative shapes, or visual patterns that conflict with `DESIGN.md`.

---

## 5. Backend Architecture & Security
- **Authentication & Security:** Use secure password hashing, prepared SQL statements, strict input validation/sanitization, and backend authorization (never rely solely on frontend UI restrictions).
- **Credentials & Secrets:** Never expose secrets, passwords, API keys, SMTP credentials, or database passwords in frontend code.
- **CSRF & Sessions:** Preserve CSRF protection and secure session management where applicable.
- **Domain Logic & Data:**
  - Centralize blood compatibility matching logic (see `BloodCompatibilityService.php`).
  - Proximity/location filtering must never override biological blood compatibility.
  - Preserve audit logging (`AuditLogger.php`) and transactional integrity across database mutations.
  - Avoid duplicate notifications.

---

## 6. Accessibility & Responsiveness
- Maintain text/background contrast ratios of at least 4.5:1 (WCAG AA).
- Never use color as the sole indicator of urgency, status, or errors; always pair with clear text and icons.
- Ensure visible keyboard focus states (2px solid navy focus ring with 2px offset).
- Interactive controls must provide adequate click/touch targets (minimum 44px × 44px).
- Maintain logical keyboard navigation, semantic HTML structure, and tab order.
- Respect `prefers-reduced-motion` and `prefers-reduced-transparency`.
- Verify layouts remain functional without truncation or clipping at narrow mobile widths (375px) and under 130% text scaling.

---

## 7. Codebase Safety & Quality
- Preserve the existing framework, routing structure, component architecture, naming conventions, and folder structure unless the task explicitly requests a change.
- Do not add dependencies unless they are strictly necessary for the requested task.
- Before creating a new component or utility, verify whether an existing one can be reused or extended.
- Prefer small, focused changes over broad refactors.

---

## 8. Implementation Truth Rule
- `docs/ai_context/CONTEXT.md` defines what BloodMatch is required to do.
- The repository defines what BloodMatch currently does.
- Never assume a feature is implemented because it is described in documentation, appears in old plans, or an endpoint/table exists.
- Verify actual end-to-end behavior in the codebase.
- Use standard verification labels:
  - ✅ IMPLEMENTED AND VERIFIED
  - ⚠️ PARTIALLY IMPLEMENTED
  - ❌ NOT IMPLEMENTED
  - ❓ CANNOT VERIFY

---

## 9. Verification & Completion Table
After making changes:
- Run the project's available lint command, build, or tests when relevant.
- For frontend work, verify the rendered result in the browser when browser tooling is available.
- Check the requested route or component rather than assuming the source code is correct.
- Report verification failures honestly.
- **Do not commit changes unless explicitly requested.**

For any task touching multiple files, finish with this verification table:

| File | What changed | Distinctive string to search for | Found? |
| :--- | :--- | :--- | :--- |
|      |      |      |      |

---

## 10. Ambiguous Decisions
When a requested design or behavior is not covered by `DESIGN.md`, the existing component system, or the current task specification:
1. First inspect the existing implementation for an established pattern.
2. If no established pattern exists and the decision would materially affect the system, ask before inventing a new direction.
3. Do not make arbitrary visual or architectural decisions simply to complete the task faster.

---

## 11. Development Priority
```
Correctness → Security → Requirements → Existing functionality
           → Data integrity → Performance → UX/UI → Maintainability
```
