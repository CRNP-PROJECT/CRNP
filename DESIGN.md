---
name: Crates N' Plates
description: A warm, espresso-dark diner management system — flat ledger surfaces that lift only on interaction, one accent, tabular money.
colors:
  accent: "#f3c47d"
  accent-strong: "#ffd79b"
  accent-soft: "rgb(243 196 125 / 14%)"
  accent-ink: "#38251a"
  accent-light: "#8a5a1b"
  accent-strong-light: "#744a13"
  accent-soft-light: "rgb(138 90 27 / 14%)"
  accent-ink-light: "#fffdfb"
  danger: "#c8102e"
  danger-ink: "#ffffff"
  espresso: "#121010"
  board: "#1f1a18"
  board-overlay: "#262019"
  board-hover: "#2a221e"
  glass: "rgb(23 18 15 / 82%)"
  glass-border: "rgb(243 196 125 / 40%)"
  ink: "#f5eedc"
  ink-muted: "#a39e9b"
  ink-faint: "#8a817c"
  line: "#282220"
  line-strong: "#3a322e"
  open: "#2f9e44"
  warning: "#e8a33d"
  espresso-light: "#f6f2ee"
  board-light: "#fffdfb"
  board-hover-light: "#efe8e2"
  glass-light: "rgb(255 253 251 / 88%)"
  glass-border-light: "rgb(138 90 27 / 45%)"
  ink-light: "#1c1714"
  ink-muted-light: "#5c5450"
  ink-faint-light: "#6f6761"
  line-light: "#e4dcd5"
  line-strong-light: "#cdc2ba"
typography:
  display:
    fontFamily: "Playfair Display, Georgia, Times New Roman, serif"
    fontSize: "2.5rem"
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: "0.12em"
  headline:
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.25
  title:
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: 1.3
  body:
    fontFamily: "Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontSize: "0.8125rem"
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: "0.04em"
rounded:
  sm: "6px"
  md: "12px"
  lg: "20px"
  xl: "26px"
  pill: "999px"
spacing:
  "1": "4px"
  "2": "8px"
  "3": "16px"
  "4": "24px"
  "5": "40px"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.accent-ink}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "44px"
  google-button:
    backgroundColor: "{colors.board}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "44px"
  field-input:
    backgroundColor: "{colors.espresso}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
    height: "44px"
  category-chip:
    backgroundColor: "{colors.board}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
    padding: "4px 16px"
    height: "44px"
  category-chip-active:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.accent-ink}"
    rounded: "{rounded.pill}"
    padding: "4px 16px"
    height: "44px"
  product-card:
    backgroundColor: "{colors.board}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "16px"
  tag-promo:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.accent-ink}"
    rounded: "{rounded.sm}"
    padding: "0 8px"
  tag-top:
    backgroundColor: "{colors.espresso}"
    textColor: "{colors.accent}"
    rounded: "{rounded.sm}"
    padding: "0 8px"
---

# Design System: Crates N' Plates

## Overview

**Creative North Star: "The Counter Ledger"**

Crates N' Plates looks like a diner's order pad and receipt made rigorous. The
surface is warm and plainspoken — a neighbourhood diner's voice carried straight
into the interface — but it is disciplined by a bookkeeper's columns: money is
tabular, labels are small and set in uppercase, and every figure the system can
compute it computes, so the screen never asks a person to do arithmetic. The
system spans one web app with four very different jobs (a customer ordering on a
phone, a cashier at a shared register, a cook glancing at a wall screen, and an
owner in the back office), so the visual system stays deliberately quiet and
consistent rather than expressive: it earns trust by being legible under service
light and identical from screen to screen.

Depth is tonal first. The page sits on a warm espresso-black surface, cards and
panels lift onto a deep espresso-brown board surface, and a 1px warm line does
the rest of the separating. Surfaces are flat at rest: hierarchy comes from
tonal layering, borders, and spacing. A shallow shadow vocabulary exists but is
reserved for the moment of interaction — a hover lift on a card, tile, or
button, and floating chrome such as the sticky header — so depth reads as
responsiveness, never as decoration. The feel is **refined and restrained**:
quiet surfaces, precise alignment, no flourish. Controls are honest and
unfussy rather than decorative.

Color is almost entirely neutral; a single warm golden-amber accent does the
signalling. It is used **wherever the eye must go next** — the primary action, the
active nav item, a promo tag, the price on a product. The exceptions the diner
must notice (a late ticket, a refunds-owed badge, a flagged payment hold, a failed
proof) carry a reserved functional **Danger Red** instead, so an exception never
looks like a call to action. Two motifs carry the brand more than any single
color: **uppercase, letter-spaced micro-labels** (order codes, column headers,
step counters) and **tabular numerals on every monetary figure**, which keep the
ledger columns aligned. Display titles trade the system stack for **Playfair
Display**, a warm serif that gives the café its nameplate; everything else runs on
**Inter**.

**Key Characteristics:**
- Flat tonal layering: espresso base → board raised → 1px border; shadows appear only on hover lift and floating chrome, never at rest.
- One warm golden-amber accent, used wherever the eye must go next; functional hues stay separate — a green "open" dot, a single amber warning, and a reserved danger red for exceptions.
- Tabular numerals on every money figure; uppercase letter-spaced labels on every micro-label.
- Playfair Display for display titles, Inter for the UI; dark-first with a full `prefers-color-scheme: light` mirror.
- Pills and soft radii (6 / 12 / 20 / 26px) over hard corners; touch targets floored at 44px.
- Motion is short and smooth (transform/opacity only, plus a one-off fade-up entrance), always disabled under `prefers-reduced-motion`.

## Colors

A near-monochrome warm ledger of espresso, board, and cream ink, broken by
exactly one warm accent. Every value below is a `--color-*` custom property on
`:root`, and each has a light-mode counterpart swapped in by
`@media (prefers-color-scheme: light)`.

### Primary
- **Accent Gold** (#f3c47d / `--color-brand`): the single accent. Carries the
  primary button, the active nav underline and active chip, promo and Top-3 tags,
  the price on a product, the 2px payment frame and flagged-hold border, the
  kitchen NEW badge, the store-badge "closed" dot, and focus outlines. It appears
  wherever emphasis is needed — its meaning is always "look here". In light mode
  it deepens to **Accent Bronze** (#8a5a1b) so accent-as-text keeps its contrast
  against cream.
- **Accent Ink** (#38251a / `--color-brand-ink`): the text and icon color placed
  on Accent Gold fills — buttons, tags, banners, badges. It is a deep espresso
  brown, because the accent is a light hue that carries dark ink rather than
  white. In light mode it flips to cream (#fffdfb) on the bronze fill.
- **Accent Strong** (#ffd79b / `--color-brand-strong`): the brighter hover/press
  twin of Accent Gold, used on the primary button's hover and its focus-visible
  state so the fill visibly reacts without a second hue.
- **Accent Soft** (`--color-brand-soft`): a 14% Accent Gold tint for active chips,
  hover glows, and the focus ring. It carries emphasis without a filled surface.
- **Danger Red** (#c8102e / `--color-danger`, with **Danger Ink** #ffffff): the
  reserved functional red for exceptions only — a failed payment proof, a flagged
  hold, a late kitchen ticket, a red age timer. It is never a call to action and
  never decoration, so an exception cannot be mistaken for a primary path.

### Neutral
A warm espresso ramp: an espresso-black page, deep espresso-brown boards, and
warm cream ink, all separated by warm hairlines. Light mode mirrors it with warm
off-whites and warm greys rather than cool greys.
- **Espresso** (#121010 / `--color-surface`): the base page surface — the warm
  near-black the ledger sits on. Light mode: **Espresso Light** (#f6f2ee).
- **Board** (#1f1a18 / `--color-surface-raised`): the raised panel surface —
  cards, panels, tables, the register header, tiles at rest. Light mode:
  **Board Light** (#fffdfb).
- **Board Overlay** (#262019 / `--color-surface-overlay`): one more tonal step
  for chrome that floats above a panel — popovers, the sign-in card. Light mode:
  **Board Overlay Light** (#fffdfb).
- **Board Hover** (#2a221e / `--color-surface-hover`): the row/tile/link fill on
  hover — a state tint, never a resting surface. Light mode: **Board Hover
  Light** (#efe8e2).
- **Chrome** (`--color-chrome`): the 88%-transparent espresso surface behind the
  sticky headers, paired with `backdrop-filter: saturate(140%) blur(12px)` so
  content scrolls beneath a frosted bar. Light mode flips to a warm white wash.
- **Glass** (`--color-glass`, `--color-glass-border`): the translucent espresso
  panel (82% opacity, blur 12px) that the product detail card floats on above the
  café photo, edged with a 40% Accent Gold hairline. Light mode: an 88% cream
  wash edged with 45% Accent Bronze.
- **Ink** (#f5eedc / `--color-text`): primary cream text. Light mode: **Ink
  Light** (#1c1714).
- **Ink Muted** (#a39e9b / `--color-text-muted`): secondary warm-grey text,
  hints, placeholders, strike-through prices, table headers. Light mode: **Ink
  Muted Light** (#5c5450).
- **Ink Faint** (#8a817c / `--color-text-faint`): the quietest tier — disabled
  text, footer meta, decorative glyphs. Light mode: **Ink Faint
  Light** (#6f6761).
- **Line** (#282220 / `--color-border`): every 1px warm border, divider, table
  rule, and input stroke — also the header's bottom seam. Light mode: **Line
  Light** (#e4dcd5).
- **Line Strong** (#3a322e / `--color-border-strong`): the emphasized hairline —
  hover borders, active input strokes, table header rules. Light mode: **Line
  Strong Light** (#cdc2ba).
- **Open Green** (#2f9e44 / `--color-open`): the "open now" status hue, with a
  translucent **Open Soft** (`--color-open-soft`) tint for its pill background.
  It never fills anything outside the status vocabulary.
- **Warning Amber** (#e8a33d / `--color-warning`): the one non-accent,
  non-green, non-danger hue — reserved for advisory states that need attention
  but are not errors (e.g. a stale pending item, a closed store badge). Ships
  with a translucent **Warning Soft** (`--color-warning-soft`) tint.

### Named Rules
**The One Accent Rule.** There is exactly one accent hue in the system (Accent
Gold), supplemented by three functional hues — a green "open" dot, an amber
warning for advisory (non-error) states, and a reserved danger red for exceptions.
No second brand color is introduced; the exception red is functional and is never
used to signal a call to action. Emphasis reuses Accent Gold, exceptions reuse
Danger Red.

**The Every-Exception Rule.** Danger Red is reserved for exceptions only: a failed
payment proof, a flagged hold, a late kitchen ticket, or a red age timer. Accent
Gold marks the primary action and discoverability (prices, promo, selected state,
and guidance). Never use Danger Red for the primary CTA.

## Typography

**Display Font:** Playfair Display — `Playfair Display, Georgia, "Times New Roman", serif` — used for display titles (e.g. the product name on the product detail page). 
**Body Font:** Inter — `Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif` — used for the UI and body text, with system fallbacks.

**Label/Mono Font:** none — micro-labels reuse the body stack with uppercase and
letter-spacing; money uses tabular figures rather than a mono face.

### Hierarchy
- **Display** (700, 2.5rem, line-height 1.1, letter-spacing 0.12em): the order
  code on the customer tracker — the single biggest element in the app, read
  from across a counter.
- **Headline** (600, 1.5rem): screen titles (cart, queue, register actions,
  kitchen board, sign-in title).
- **Title** (600, 1.25rem): section titles, menu group titles, product names in
  the detail view, KPI values.
- **Body** (400, 1rem, line-height 1.5): all running text; long copy caps at
  ~60ch (`.site-main__copy`, descriptions, settings copy). The product detail page
  carries a wider glass card (`.site-main.site-main--product`, max-width 1200px).
  Content grids are `auto-fit`/`auto-fill` with `minmax` so they reflow without
  breakpoints: product grid `minmax(220px, 1fr)`, tiles `minmax(9rem, 1fr)`,
  rentals/occupancy `minmax(16rem, 1fr)`.

### Named Rules
**The Tabular Money Rule.** Every monetary figure — prices, totals, change,
receipt lines, KPI values, kitchen quantities — sets `font-variant-numeric:
tabular-nums`. Digits align in columns; a computed number never jitters.

**The Ledger-Label Rule.** Any label under ~1rem that names a column, field, or
metric is uppercase with 0.04em letter-spacing. Order codes and large
identifiers invert this: large, bold, 0.08–0.12em tracking, never uppercase.

## Layout

A mobile-first, single-column flow that widens into at most two columns for
task surfaces. The main public container (`.site-main`) is capped at **960px**
and centered with 40px vertical / 24px horizontal padding; interior panels cap
themselves narrower still (forms 32–40rem, the receipt 24rem, the tracker 34rem,
long copy 60ch). Content grids are `auto-fit`/`auto-fill` with `minmax` so they
reflow without breakpoints: product grid `minmax(220px, 1fr)`, tiles
`minmax(9rem, 1fr)`, rentals/occupancy `minmax(16rem, 1fr)`.

The spacing rhythm is a five-step scale — **4 / 8 / 16 / 24 / 40px** — used for
every gap, padding, and margin; there are no arbitrary spacing values. Density is
**comfortable** on the public site and cashier POS, and **compact** only on
read-only glanceable surfaces (kitchen board, admin tables), and never below the
tap floor.

Named breakpoints, in order of appearance: **800px** (walk-in POS splits into a
3fr / 2fr two-column register), **720px** (receipt/product-detail print-ish
widening), **719px** (the primary nav becomes a horizontally scrollable strip and
the header wraps to two rows), **640px** (wide tables scroll horizontally rather
than truncate), **60rem** (kitchen lanes split 2fr / 1fr), plus
`prefers-color-scheme: light`, `prefers-reduced-motion: reduce`, and a `print`
block for receipts. Reflow is verified down to **320px** (WCAG 1.4.10); overflow
uses "Show more", never silent truncation.

The public and POS headers are **sticky**: they pin to the top of the viewport
on the translucent **Chrome** surface with a backdrop blur, so the labeled nav is
always reachable while a long menu, queue, or board scrolls beneath it.

## Elevation & Depth

Flat at rest, tonal, and border-led. A resting surface is either the base
**Espresso**, the raised **Board**, or the floating **Board Overlay**, and a 1px
**Line** border does the separating; hierarchy at rest comes from moving a panel
up one tonal step and from spacing, not from shadow. Three shadow steps exist
(`--shadow-sm` / `md` / `lg`) but are a **transition** vocabulary, not a resting
one: they appear on hover lift (cards, tiles, buttons) and on floating chrome
(the sticky translucent header, overlays), so depth reads as "this responds"
rather than "this is higher". Focus is carried by the Accent Soft ring
(`--ring: 0 0 0 3px var(--color-brand-soft)`) and by border/outline changes; the
late kitchen ticket keeps its *inset* Danger Red ring as a state cue. Dashed
borders mark two "empty/provisional" cases (`.empty-state`, `.promo-preview`),
and a 2px solid border marks two "attention" containers (`.payment-frame`,
`.tracker__hold`) — Accent Gold for the branded GCash frame and Danger Red for
the flagged hold.

### Named Rules
**The Flat-At-Rest Rule.** A surface carries no shadow until it is interacted
with or floats above the page. Raise the tone (espresso → board → overlay) and let
the 1px line hold the edge; reserve `--shadow-sm` / `md` / `lg` for hover lift
and floating chrome.

**The Shadow-Is-Feedback Rule.** Depth appears only in response to state —
hover, focus, or a genuinely floating layer. A resting surface never carries a
shadow.

## Shapes

A soft, rounded form language: a four-step radius scale — **6px (sm)**, **12px
(md)**, **20px (lg)**, **26px (xl)** — plus a full **pill (999px)**. Small
elements (inputs, tags, chips-of-text, thumbs) take `sm`; the default control
and panel radius is `md`; large containers (cards, sections, the receipt, the
payment frame) take `lg`; the floating café card (product detail) takes `xl`.
Pills are reserved for inherently round affordances: category chips, nav links,
favorite hearts, steppers, and the store badge. Nothing in the system uses a
square corner, and there is no border-radius: 0 anywhere.

Borders are hairline (1px, `Line`) by default; **2px** borders are reserved for
the two attention containers (payment frame, flagged hold) — Accent Gold for the
branded GCash frame and Danger Red for the flagged hold; the kitchen ticket card
uses 2px borders. **Dashed** 1px borders mark empty or provisional surfaces.
Images use `aspect-ratio: 4 / 3` with `object-fit: cover` and inherit the
container radius; the one hard-edged surface is the white GCash QR, which is
served untouched inside its red frame.

## Components

The feel throughout is **refined and restrained** — quiet, precise, unfussy.
Every control is a real element with a 44px minimum height on touch surfaces;
interactive states are carried by a border/color change and a shallow shadow
lift, never by a resting shadow.

### Buttons
- **Shape:** medium radius (12px) for primary and secondary; the pill (999px) is
  reserved for chips, steppers, and the favorite toggle.
- **Primary:** Accent Gold fill, dark Accent Ink text, 600 weight, `8px 16px` padding,
  44px min-height. One per view where a primary action exists. On hover and
  focus-visible it brightens to **Accent Strong** and casts a soft Accent Soft
  glow; `:active` presses it down 1px. The product page's primary actions are
  pill-shaped for a polished, touch-friendly call to action.
- **Secondary / Google:** Board fill, Ink text, 1px Line border, 12px radius,
  600 weight. On hover/focus-visible the fill warms to **Board Hover** and the
  border shifts to Accent Gold.
- **Link button:** no fill or border, Accent Gold text, 24px min-height — used for
  in-flow actions like "Sign out". On hover it brightens to Accent Strong and
  underlines.
- **Disabled:** `opacity: 0.55` with `cursor: not-allowed` (the place-order gate).
- **Toggle button:** Espresso fill at rest; on hover the border strengthens and the
  fill warms; when `aria-pressed="true"` the border and text shift to Accent Gold
  over an Accent Soft fill (a state, not a filled button).

### Chips
- **Style:** category chips are pills — Board fill, Ink text, 1px Line border,
  44px min-height, `4px 16px` padding.
- **Selected:** the active chip flips to an Accent Gold fill with dark Ink text and
  600 weight (`.category-chip--active`).
- **Choice chips** (`.chip`): hidden radio + styled span; the checked span takes
  the Accent Gold fill and the keyboard focus shows the Accent Soft ring.

### Cards / Containers
- **Corner Style:** large radius (20px) for cards, sections, receipts, and the
  payment frame; medium (12px) for smaller panels, tiles, and rows.
- **Background:** **Board** (#1f1a18) for every raised card/panel, stepping to
  **Board Overlay** for surfaces that float above a panel; the base **Espresso**
  shows through the page and inside inputs.
- **Shadow Strategy:** flat at rest. `--shadow-sm` lifts standalone floating
  surfaces (the sign-in panel, the receipt, the kitchen ticket, the banner);
  product and rental cards take `--shadow-md` plus a `-3px` translateY only on
  hover. See Elevation & Depth.
- **Border:** 1px Line (strengthening to Line Strong on hover); 2px Accent Gold
  only for the branded GCash frame, and 2px Danger Red for the flagged hold; the
  kitchen ticket card uses 2px borders.
- **Internal Padding:** from the spacing scale — 16px typical, 24px for larger
  panels, 40px for hero/empty states.

### Inputs / Fields
- **Style:** Espresso fill, Ink text, 1px Line stroke, small radius (6px), `8px 16px`
  padding, 44px min-height.
- **Hover:** the stroke strengthens to Line Strong.
- **Focus:** the field border shifts to Accent Gold and takes the 3px Accent Soft
  ring (`--ring`).
- **Error:** `.field__error` and `.notice--error` render in Danger Red (the error
  border also shifts to Danger Red).

### Navigation
- **Style:** a plain flex row of labeled text links (never an unlabeled icon),
  always visible, each a pill-shaped (`999px`) hit target. Links are Ink Muted,
  600 weight; on hover they fill **Board Hover** and warm to Ink. The current
  page takes `aria-current="page"` — Accent Gold text with a gold underline
  (no filled pill). The public `.site-nav`, the cashier `.register-header`, and
  the admin nav share this one rule (the admin nav reuses `.site-nav`), with the
  account control pinned right.
- **Report tabs:** the admin report switcher is a segmented control — a bordered
  Board track holding `.report-tabs__link` items; the active item is a filled
  Accent Gold segment.
- **Mobile:** at **719px** the labeled nav and account fold into a single full-width
  drawer revealed by a JS-free checkbox and a hamburger toggle; links remain fully
  labeled and the divider separates the shop name from the links. The logo always
  sits on its Board chip so the white-on-transparent artwork stays legible.

### Signature Components
- **Order-code display** (`.tracker__code`, `.walkin-head__code`,
  `.kitchen-card__code`): the brand's most distinctive motif — a large, bold,
  widely-tracked code (2–2.5rem, 0.08–0.12em) sitting under a small uppercase
  label. It appears identically on the customer tracker, the walk-in register,
  the receipt, and the kitchen ticket, tying every surface together.
- **Branded GCash frame** (`.payment-frame`): a 2px Accent Gold bordered Board
  panel holding the untouched white QR (max 20rem) and the copyable GCash number
  in tabular figures — the artwork is never re-rendered.
- **Kitchen ticket** (`.kitchen-card`): a 2px-bordered Board card with the code,
  per-line quantities in bold tabular figures, an age timer that turns Danger Red
  at 12 minutes, a NEW badge (gold) that flashes opacity-only five times then holds
  steady, and a dimmed (55% opacity) LATER variant for not-yet-cookable tickets.

## Do's and Don'ts

### Do:
- **Do** keep the surface stack to Espresso → Board → Board Overlay and separate
  with a 1px Line.
- **Do** set `font-variant-numeric: tabular-nums` on every monetary figure.
- **Do** set micro-labels (columns, fields, metrics) uppercase with 0.04em
  tracking, and order codes large, bold, and 0.08–0.12em tracked.
- **Do** use Accent Gold for the primary action and for discoverability (prices,
  promo, selected state). Use **Danger Red** for exceptions only (failed proof,
  flagged hold, late ticket, red age timer).
- **Do** reserve `--shadow-sm` / `md` / `lg` for hover lift, focus, and standalone
  floating surfaces; keep in-flow cards and rows flat at rest.
- **Do** floor touch targets at 44px on cashier/kitchen surfaces and never below
  24px anywhere (WCAG 2.5.8).
- **Do** confine motion to `transform`/`opacity` and disable it under
  `prefers-reduced-motion: reduce`.
- **Do** carry the radius scale 6 / 12 / 20 / 26px plus pills — no square corners.

### Don't:
- **Don't** give an in-flow card or row a resting shadow; reserve depth for
  hover/focus and genuinely floating surfaces.
- **Don't** introduce a second brand hue — the only other hues are the green
  "open" dot (#2f9e44), the amber warning (#e8a33d), and the reserved danger red
  (#c8102e) for exceptions only.
- **Don't** use Danger Red as a call to action; Accent Gold is for actions and
  emphasis, Danger Red is only for exceptions.
- **Don't** use Accent Gold as a large background fill for decoration; it is for
  emphasis.
- **Don't** load a webfont or add a mono face — Inter and Playfair Display (Google Fonts, loaded once) provide the type system, while tabular figures are used for money.
- **Don't** re-render, recolor, or re-export the logo (`public/images/logo.png`,
  white-on-transparent, always on its Board chip) or the GCash QR (always the
  official white image inside the red frame).
- **Don't** put two same-weight, same-style buttons in one visual group — the
  hierarchy comes from size, contrast, and grouping.
- **Don't** hide navigation behind an unlabeled icon; chrome stays visible and
  labeled — on narrow screens, a labeled hamburger reveals a drawer of labeled
  links.
