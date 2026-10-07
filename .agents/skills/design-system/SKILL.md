---
name: design-system
description: Use when creating, modifying, or reviewing BloodMatch UI while preserving the established visual system, tokens, components, and interaction patterns.
---

# BloodMatch Design System Skill

Use this skill for BloodMatch frontend UI work.

## Before Making UI Changes

Read:
* `AGENTS.md`
* `DESIGN.md`
* `UI_REDESIGN_PLAN.md` (when the task is part of the redesign)
* Relevant token files
* Relevant existing components and pages

Understand the existing implementation before creating or modifying UI.

## Design Tokens

Use the project's existing token system.

Primary sources:
* `src/tokens.ts`
* `src/index.css`

Never introduce a second visual-token system.
Avoid hardcoded:
* Colors
* Font sizes
* Spacing values
* Radii
* Shadows
* Motion values
*(when an established token exists)*

## Components

Prefer existing shared components over creating duplicates.
Before creating a component:
* Search the existing component library.
* Identify whether an existing component can be extended or reused.
* Follow existing naming and API conventions.

Only create a new component when the existing system does not cover the requirement.

## BloodMatch Visual Language

Follow the decisions documented in `DESIGN.md`.
Pay particular attention to:
* Inter typography
* Navy as the primary brand/action color
* Red restricted to emergency/error states
* Restrained neutral surfaces
* Consistent 8px spacing rhythm
* Established card and control radii
* Accessible contrast
* 44px interactive targets
* Visible keyboard focus states
* Dark-mode support
* Reduced-motion support

Do not introduce visual patterns that conflict with the documented system.

## Icons

* Do not use emoji as UI icons.
* Use the existing icon library. Prefer `lucide-react` when available.
* Use consistent icon sizing and stroke treatment with the existing component system.

## States

When implementing a reusable UI component, consider the states required by the project:
* Default
* Hover
* Focus
* Pressed
* Disabled
* Loading
* Error
* Selected/Active
* Empty (where relevant)

Do not add unnecessary states to simple static elements.

## Responsive Design

* Preserve the existing responsive strategy.
* Check relevant layouts at narrow widths.
* Do not solve desktop layout problems by introducing fixed dimensions that break smaller screens.

## Accessibility

Check:
* Contrast
* Keyboard access
* Visible focus
* Target size
* Semantic structure
* Readable labels
* Status communication
* Reduced motion

Never communicate an important state through color alone.

## Browser Verification

For significant frontend changes:
* Run the development server when necessary.
* Open the affected route in the browser.
* Inspect the rendered result.
* Confirm the requested interaction and visual behavior.
* Report anything that could not be verified.

Do not assume source code correctness means visual correctness.

## Scope

* This skill does not authorize changes outside the requested task or phase.
* Do not begin later redesign phases automatically.
* Do not perform unrelated refactors during UI work.
