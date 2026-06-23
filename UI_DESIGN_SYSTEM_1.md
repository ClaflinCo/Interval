# UI Design System — Source of Truth

> **Read this before generating any UI.** This file defines the mandatory visual standards for every application we build. It is stack-agnostic: the rules apply whether you are writing React, React Native, Vue, or plain HTML/CSS. Map the tokens to whatever the project uses (CSS custom properties, Tailwind config, a JS theme object, a React Native `StyleSheet`) but **do not change the values**.

*Version 5 — Consolidated primary colors with official Claflin A.L.M. brand navy (#05294B) to establish dark surfaces and header consistency. Reconciled with implemented legacy style mappings, spin animations, global filter bars, login spinner loaders, warn/exceeded status colors, and updated modal sizing specifications.*

---

## 0. How to use this document

1. **Tokens are law, layouts are guidance.** Colors, fonts, radii, shadows, and spacing are fixed. Component *structures* (login, header, cards) are canonical patterns you reproduce; adapt internal content to the app's purpose.
2. **Always emit a token layer first.** Before writing components, declare the full token set in the project's idiom (see §2.7). Reference tokens by name — never hardcode a hex value inline.
3. **Light mode only.** No dark-mode variants or `prefers-color-scheme` blocks unless a prompt explicitly requests them.
4. **Conflict rule.** Follow this document by default. A prompt may override a specific rule *only when it says so explicitly* (e.g. "use the client's brand red for the primary button"). Absent an explicit override, this document wins — including over your own aesthetic preferences.
5. **Normal sizing, not oversized.** Use the type scale in §2.4 as written. Do not inflate text or inputs to fill empty space; handle sparse screens with layout and whitespace instead.

---

## 1. Brand foundation

The brand is **Claflin A.L.M.** (est. 1817). Official colors and logos are confirmed (2025 asset set). The brand mark is a navy-and-white wordmark; our apps pair it with a bright cyan accent (not part of the logo) for interactive emphasis.

- **Personality:** professional, trustworthy, established, clean. Deep brand navy as the authority color, a bright cyan as the single interactive accent. White surfaces, soft shadows, never harsh borders.
- **Tone:** credible and calm, not flashy. Generous whitespace. One accent color used sparingly for emphasis and focus.
- **Density:** comfortable. Real padding on inputs and buttons; tables and cards breathe.

---

## 2. Design tokens

### 2.1 Color

| Token | Value | Role |
|---|---|---|
| `--color-primary` | `#05294B` | Primary brand blue/navy (official Claflin A.L.M. logo-background navy). Unified as primary and container color for dark headers, login card backdrops, primary buttons, links, headings, key figures. |
| `--color-primary-hover` | `#031f3b` | Hover/active for primary surfaces. |
| `--color-navy` | `#05294B` | Identical to `--color-primary`. Deep brand navy backdrop, header background, footer, and main dark surfaces. |
| `--color-accent` | `#009CDE` | Single accent (cyan). Focus rings, active states, highlights, secondary emphasis. Not part of the logo — use sparingly. |
| `--color-accent-hover` | `#007bb0` | Hover for accent surfaces. |
| `--color-success` | `#00A99D` | Success / positive (teal). |
| `--color-warning` | `#FFC20E` | Caution / pending (yellow/gold/amber). |
| `--color-error` | `#E35205` | Errors, destructive actions. |
| `--text-main` | `#333333` | Primary text. |
| `--text-secondary` | `#5a6268` | Secondary text, labels. |
| `--text-muted` | `#868e96` | Placeholders, captions, disabled text. |
| `--text-on-primary` | `#ffffff` | Text on blue/navy/accent surfaces. |
| `--bg-app` | `#f8f9fa` | App background (behind cards). |
| `--bg-card` | `#ffffff` | Card / panel / input surface. |
| `--bg-input-focus` | `#f0f8ff` | Input background on focus (light alice blue). |
| `--bg-active` | `#f0f7ff` | Subtle blue active/expanded row or item state. |
| `--bg-input-disabled` | `#e9ecef` | Disabled input. |
| `--border-light` | `#dae0e5` | All borders and dividers. |
| `--border-focus` | `#009CDE` | Focused input border (= accent). |

*Brand note: `--color-primary` was consolidated from `#0A3B73` to the official logo background navy fill `#05294B` to unify visual identity across dark headers, login backdrops, and active elements. `--color-navy` shares this value.*

**Legacy Variable Mappings (for compatibility in retrofitted codebases):**
- `--navy` / `--navy-light` / `--navy2` → mapped to `--color-primary` (`#05294B`), `--bg-active` (`#f0f7ff`), `--color-primary-hover` (`#031f3b`).
- `--gold` / `--gold2` / `--gold-light` → mapped to `--color-accent` (`#009CDE`), `--color-accent-hover` (`#007bb0`), `--bg-active` (`#f0f7ff`).
- `--surface` → `--bg-card` (`#ffffff`).
- `--bg` → `--bg-app` (`#f8f9fa`).
- `--border` → `--border-light` (`#dae0e5`).
- `--ink` / `--ink2` → `--text-main` (`#333333`), `--text-secondary` (`#5a6268`).
- `--red` / `--green` / `--amber` → `--color-error` (`#E35205`), `--color-success` (`#00A99D`), `--color-warning` (`#FFC20E`).
- `--override` / `--override-bg` / `--override-border` → specific override red (`#d32f2f`), (`rgba(211, 47, 47, 0.1)`), (`rgba(211, 47, 47, 0.25)`).
- `--radius` → `--radius-xl` (`12px`).
- `--shadow` → `--shadow-md`.

**Tints for status backgrounds:** error → `#fef2f2` / `rgba(227,82,5,.1)`; success → teal at ~10% opacity; warning → amber at ~12%. Keep these for badge and alert backgrounds only.

**Contrast:** Target WCAG AA (4.5:1 body, 3:1 large/UI). `--text-main`/`--text-secondary` on white and white on `--color-primary` all pass. Note: cyan `--color-accent` on white does **not** meet 4.5:1 for small text — use it for borders, fills, large text, and focus rings, not for body copy. Verify any new color before using it for text.

### 2.2 Radius

| Token | Value | Use |
|---|---|---|
| `--radius-sm` | `4px` | Badges, small chips, inline tags. |
| `--radius-md` | `8px` | Buttons, inputs, search fields. |
| `--radius-lg` | `10px` | List items, secondary panels. |
| `--radius-xl` | `12px` | Cards, modals, the login card. |

Hierarchy rule: containers are rounder than the controls inside them — cards/modals at `12px`, the buttons and inputs within them at `8px`. Don't make buttons fully rounded (pill) unless a prompt asks; that reads consumer, not institutional. Status dots and avatars may be `border-radius:50%`.

### 2.3 Shadow

| Token | Value | Use |
|---|---|---|
| `--shadow-sm` | `0 1px 3px rgba(0,0,0,.05)` | Header, resting list items. |
| `--shadow-md` | `0 4px 6px rgba(0,0,0,.07)` | Cards, hovered items. |
| `--shadow-lg` | `0 10px 15px rgba(0,0,0,.1)` | Modals, login card, elevated/hovered cards. |

Borders are `1px solid var(--border-light)` by default; inputs use a `2px` border so the focus state reads clearly.

### 2.4 Typography

One family throughout — a clean humanist sans. No serif.

```
--font-family: 'Open Sans', 'Segoe UI', sans-serif;   /* weights 400, 500, 600 */
```
Web import:
```html
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
```
React Native: bundle Open Sans via `expo-font`; don't substitute the system font.

**Type scale** (moderate — do not inflate):

| Token | Size | Typical use |
|---|---|---|
| `--font-size-xs` | `0.75rem` | Badges, tiny labels. |
| `--font-size-sm` | `0.875rem` | Captions, hints, secondary text. |
| `--font-size-base` | `1rem` | Body, inputs, buttons. |
| `--font-size-lg` | `1.125rem` | Emphasized body, large labels. |
| `--font-size-xl` | `1.5rem` | Section / page titles, modal titles. |
| `--font-size-2xl` | `2rem` | Key figures / stat values. |
| `--font-size-3xl` | `2.5rem` | Hero numbers (rare). |

> Note: `--font-size-base` is **1rem** in this blended system (the NP Medical app used 1.1rem and oversized 1.5rem inputs to fill a sparse screen — that is not our standard).

**Type roles:**

| Role | Size | Weight | Notes |
|---|---|---|---|
| Page / section title | `1.5rem` (`xl`) | 600 | Color `--color-primary`. |
| Heading h1–h6 | scale down from `xl` | 600 | Color `--color-primary`, `line-height:1.2`. |
| Body | `1rem` (`base`) | 400 | Color `--text-main`, `line-height:1.5`. |
| Key figure / stat value | `2rem` (`2xl`) | 600 | Color `--text-main` (or success/error for status). |
| **Field / table / stat label** | `0.75rem` (`xs`) | 600 | **UPPERCASE**, `letter-spacing:.08em`, color `--text-secondary`. *(Signature look — use everywhere.)* |
| Caption / hint | `0.875rem` (`sm`) | 400 | Color `--text-secondary`/`--text-muted`. |
| Button | `1rem` (`base`) | 600 | — |

### 2.5 Spacing

8px-ish rhythm. Tokens: `--space-1:0.25rem`, `--space-2:0.5rem`, `--space-3:1rem`, `--space-4:1.5rem`, `--space-6:2rem`, `--space-8:3rem`, `--space-12:5rem`.
- Card body padding: `var(--space-6)` (`2rem`).
- Modal padding: `2.5rem`.
- Button padding: `var(--space-3) var(--space-6)` (`1rem 2rem`).
- Input padding: `0.75rem 1rem`.
- Login card padding: `var(--space-6)`.
- Section/content max-width: `1000px` for forms/wizards, up to `1800px` for data-dense dashboards.

### 2.6 Motion

Short and subtle. `--transition-fast: 150ms ease-in-out`, `--transition-normal: 250ms ease-in-out`. Buttons may use `transform: scale(0.98)` on `:active`. Entrance and utility animations:
```css
@keyframes fadeIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{opacity:0;transform:translateY(20px) scale(.95)} to{opacity:1;transform:translateY(0) scale(1)} }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
```
Apply `fadeIn .5s` to the login card/header and page content; `slideUp` (with a gentle overshoot cubic-bezier) to modals. Use `spin` (via the `.spin-animation` rule) for active progress, spinner, or data-refresh indicators. No decorative or looping animations should be used unless they serve an active functional indication (e.g. syncing or loading).

### 2.7 Per-stack token mapping

Declare tokens once, at the top, in the project's idiom:

- **HTML / CSS / web React:** `:root { --color-primary:#0A3B73; … }`, reference via `var(--color-primary)`.
- **Tailwind:** palette under `theme.extend.colors`, radii under `borderRadius`, shadows under `boxShadow`, `fontFamily` → Open Sans. Use named utilities (`bg-primary`, `text-secondary`, `rounded-xl`), never arbitrary `[#0A3B73]`.
- **React (CSS-in-JS / objects):** export one `theme` object (`colors`, `radius`, `shadow`, `space`, `font`) and consume it; no inline hexes.
- **React Native:** export `theme.js` constants. Shadow `--shadow-md` → `shadowColor:'#000', shadowOpacity:0.07, shadowRadius:6, shadowOffset:{width:0,height:4}` + `elevation`. Inputs `2px` border, accent focus border.
- **Vue:** CSS variables in a global stylesheet, or a theme composable.

---

## 3. Canonical components

### 3.1 Login portal — FIXED LAYOUT (reproduce exactly)

Every app's login screen is the same. Only the title text, subtitle, and logos change per app — never the structure.

**Structure (top to bottom):**
1. **Backdrop:** full viewport, centered content. Two approved options: **(default)** background `--bg-app` with a subtle blue wash toward `--bg-input-focus`, paired with the **blue** wordmark; **(on-brand dark)** a `--color-navy` (`#05294B`) fill paired with the **white** wordmark for a more branded entrance. Keep either understated. `min-height:100vh`, `padding: var(--space-4)`.
2. **Logo row:** logos centered in a flex row, `gap:1.5rem`, separated by a `1px` `--border-light` vertical divider, sitting **above** the card. Primary logo ~`50px` tall, secondary ~`40px`. `fadeIn .5s`. On screens ≤640px, scale logos to ~`40px`.
3. **Login card:** `--bg-card`, `border-radius: var(--radius-xl)` (12px), `box-shadow: var(--shadow-lg)`, `padding: var(--space-6)`, `max-width:400px`, `width:100%`, `fadeIn .5s .1s backwards`. Card header title (e.g. "Staff Login") in `--font-size-xl`, `--color-primary`.
4. **Fields:** stacked. Each has an UPPERCASE letter-spaced `--text-secondary` label above a full-width input. Input: `padding:0.75rem 1rem`, `2px solid --border-light`, `border-radius:var(--radius-md)`, white bg. **Focus:** border `--border-focus` (cyan), `box-shadow:0 0 0 4px rgba(0,156,222,.2)`, background `--bg-input-focus`. Field gap via `margin-bottom: var(--space-4)`.
5. **Error:** `--color-error` text on an error tint (`rgba(227,82,5,.1)`), `border-radius:var(--radius-md)`, centered, `role="alert"`, shown only when present.
6. **Submit button & Spinner:** full-width `btn-primary`, label "Sign In". When authenticating, the button element is disabled and hidden, replaced visually by the loading spinner (`.login-loading-container` displaying `.login-spinner` centered with spin animation) to indicate progress and block double submissions. Disabled until both fields are filled. Submits on Enter.

Reference JSX skeleton:
```jsx
<div className="login-container">
  <div className="login-header">
    <div className="login-logos">{/* primary logo · divider · secondary logo */}</div>
  </div>
  <Card className="login-card" title="[App Name] Login">
    <form onSubmit={handleSubmit} className="login-form">
      <Input id="username" label="Username" autoFocus />
      <Input id="password" label="Password" type="password" />
      {error && <div className="login-error" role="alert">{error}</div>}
      <Button type="submit" disabled={loading || !username || !password}>
        {loading ? 'Authenticating…' : 'Sign In'}
      </Button>
    </form>
  </Card>
</div>
```

### 3.2 App header / top bar

Sticky bar across the top. **Two approved treatments — pick per app and stay consistent within it:**
- **(A) Light header** (default for content/data apps): `background: --bg-card`, `padding: var(--space-3) var(--space-6)`, `box-shadow: --shadow-sm`, **`border-bottom: 3px solid var(--color-accent)`** (the cyan rule is the brand cue), space-between. Title in `--font-size-xl`, `--color-primary`. Right side shows user info in `--font-size-sm`, `--text-secondary`.
- **(B) Primary header** (for dashboard-style apps wanting more presence): `background: --color-primary`, white title, light-on-blue action buttons (`rgba(255,255,255,.12)` bg, light border), `position:sticky; top:0; z-index:50`.

Left side always carries the logo(s) (primary ~`50px`, optional secondary with a divider) and the app title. Right side carries actions + user identity.

### 3.3 Buttons

Base `.btn`: inline-flex centered, `padding: var(--space-3) var(--space-6)`, `font-size: base`, `font-weight:600`, `border-radius: var(--radius-md)`, `1px solid transparent`, `transition: var(--transition-fast)`. `:active:not(:disabled)` → `transform:scale(0.98)`. `:disabled` → `opacity:0.6`.

| Variant | Background | Text | Hover | Use |
|---|---|---|---|---|
| `btn-primary` | `--color-primary` | white | `--color-primary-hover` | Primary action. |
| `btn-accent` | `--color-accent` | white | `--color-accent-hover` | Highlighted secondary CTA (use sparingly). |
| `btn-outline` | transparent, `1px --color-primary` | `--color-primary` | bg `--bg-app` | Tertiary. |
| `btn-secondary` | transparent, `1px --border-light` | `--text-main` | border `--text-secondary`, bg `--bg-app` | Neutral / cancel. |
| `btn-success` | `--color-success` | white | darken | Confirm. |
| `btn-danger` | `--color-error` | white | darken | Destructive. |

One primary action per view. Pair `btn-primary` with `btn-secondary`/`btn-outline`, never two filled primaries side by side. Buttons are full-width in narrow forms (login), auto-width in toolbars.

### 3.4 Cards & panels

- **Card:** `--bg-card`, `border-radius: var(--radius-xl)` (12px), `box-shadow: --shadow-md`, `1px solid --border-light`, `overflow:hidden`, `transition: box-shadow var(--transition-normal)`; `:hover` → `--shadow-lg`. Header: `padding: var(--space-6) var(--space-6) var(--space-2)`, title `--font-size-xl`. Body: `padding: var(--space-6)`.
- **Stat/metric card:** card surface with an UPPERCASE `--text-secondary` label, then a `2rem` weight-600 value (`--text-main`, or `--color-success`/`--color-error` for status), optional thin progress bar (`--border-light` track, `--color-accent` fill; `--color-error` when over threshold). Group in a responsive grid, `gap: var(--space-3)`.
- **List/accordion item:** `--bg-card`, `1px --border-light`, `border-radius: var(--radius-lg)`, `--shadow-sm`; `:hover` → `--shadow-md` + border `#cbd5e1`; expanded/active → header bg `--bg-active` (`#f0f7ff`). Lead with a circular numbered index chip: `32px`, `--color-primary` bg, white, weight 600.

### 3.5 Forms (in-app)

Field group: UPPERCASE letter-spaced `--text-secondary` label above the control, `gap: var(--space-2)`, `margin-bottom: var(--space-4)`, left-aligned. Inputs/selects/textarea: `--font-size-base`, `padding:0.75rem 1rem`, `2px solid --border-light`, `border-radius: var(--radius-md)`, white bg. **Focus:** border `--border-focus`, `box-shadow:0 0 0 4px rgba(0,156,222,.2)`, bg `--bg-input-focus`. **Error:** border `--color-error`, bg `#fff8f8`, message in `--color-error` with `slideDown .2s`. Placeholders `--text-muted` at `0.6` opacity.

**Global Filter Bar (for dashboard lists and tables):**
- **Container (`.filter-bar`):** Horizontal flex layout, `align-items: center`, `justify-content: flex-start`, `gap: 20px`, background `--bg-card` (`var(--surface)`), padding `14px 24px`, bottom border `1px solid var(--border-light)`, rounded at `--radius-xl` (`var(--radius)`), elevated with `--shadow-md` (`var(--shadow)`).
- **Label (`.filter-label`):** UPPERCASE letter-spaced `--text-secondary` (or `--navy`), font size `0.8rem`, weight 700.
- **Select dropdowns (`.filter-select`):** background `var(--surface)`, border `1.5px solid var(--border)`, radius `6px`, padding `6px 12px`, font size `0.9rem`. On focus: border `--color-navy`, outline none, `box-shadow: 0 0 0 3px rgba(27,79,114,0.1)`.

### 3.6 Tables

`width:100%`. **Head:** UPPERCASE `--text-secondary` `--font-size-xs` weight 600, `.08em` spacing, `1px --border-light` bottom, left-aligned, padded `~0.75rem 0.875rem`. **Rows:** `1px --border-light` divider, hover bg `--bg-active`. Numeric cells right- or center-aligned, weight 500. Empty state: centered, italic, `--text-muted`, padded generously. Search/filter inputs above the table use a `1px` border that goes `--color-accent` + `0 0 0 3px rgba(0,156,222,.1)` on focus.

**Value-Based Cell Formatting:**
- **Exceeded limits / alert overrides (`.exceeded`):** Color text to `--color-error` / `--red` (with `!important` to override standard table cell styles).
- **Warning thresholds (`.warn`):** Color text to `--color-warning` / `--amber` (with `!important`).

### 3.7 Badges / status pills

Small, UPPERCASE, weight 600, `--font-size-xs`, `.05em` spacing, `padding:2px 7px`, `border-radius: var(--radius-sm)`. Success → teal on teal tint. Warning/pending → amber on amber tint. Error → `--color-error` on `#fef2f2`. Info → `--color-accent` on a cyan tint.

### 3.8 Modals

- **Overlay (`.modal-overlay`, `.export-modal-overlay`):** `rgba(0,0,0,.5)` color, `position:fixed; inset:0`, flex-centered, `z-index: 200`. When closed, `display: none`; when active, `display: flex`.
- **Dialogue container (`.modal`):** `--bg-card`, `border-radius: var(--radius-xl)` (12px), `box-shadow: --shadow-lg`, `animation: fadeUp 0.25s ease`.
- **Hierarchy of Modal Sizes & Layouts:**
  - **Success / Small Confirmation Modals:** `max-width: 380px`, `padding: 30px`. Icon center-aligned `.modal-success-icon` (`font-size: 3.5rem; color: var(--color-success)`), centered title and text.
  - **Standard Modals (Delete/Confirm):** `max-width: 480px`, `width: 90%`, `padding: 30px`.
  - **Form-based Input Modals (Create/Edit):** `max-width: 500px`, `max-height: 90vh; overflow-y: auto`. Header has a border divider `2px solid var(--border-light)`, footer action items separated by a top border `1.5px solid var(--border-light)`.
  - **Large Management Modals (Admin Panel/Report lists):** `max-width: 850px`, `width: 95%`, `max-height: 90vh; overflow-y: auto`, `padding: 25px 30px`.
  - **Specialized Modals (Export Modal - `.export-modal`):** Header is distinct with dark background (`.export-modal-hd` using background `--color-primary`), body has custom selection grids (`.export-type-btns` 3-column layout) with hover highlights.
- **Title / Headers:** Title uses `.modal h2` with `font-size: 1.25rem`, color `var(--color-navy)`. Management headers support dragging behavior (`cursor: move; user-select: none`).
- **Footer Actions (`.modal-actions`):** Flex alignment, `gap: 10px`, `margin-top: 20px`, `justify-content: flex-end`.

---

## 4. Logos & assets

Official Claflin A.L.M. 2025 mark. The logo is a stacked wordmark: **CLAFLIN** / *Est. 1817* / **A.L.M.** Use the variant that matches the surface behind it.

| Variant | File | Use on |
|---|---|---|
| **White wordmark (transparent)** | `Claflin_ALM_white_transparent.png` | Dark/navy surfaces — primary-header variant, navy login backdrop, footer, the navy chip below. Clean transparent background; drop straight onto any dark fill. |
| **Navy chip** | `Claflin_ALM_navy_chip.png` | **Light surfaces** — app headers and light login, where you want the mark on a light page. White wordmark on a `--color-navy` rounded tile; the on-brand way to place the logo on white without a transparent blue mark. |
| **Square (navy fill)** | `Claflin_ALM_Square_Logo_2025.jpg` | App icon, favicon, square avatar slots. Navy `#05294B` background, white mark. |
| **Circle (navy fill)** | `Claflin_ALM_Circle_Logo_2025_no_line.png` | Circular avatar/badge, PWA icon, login circular mark. Navy `#05294B` background, white mark. |
| **Blue wordmark (raw)** | `Claflin_ALM_Logo_2025_blue.png` | Print/dark-on-light contexts only. **Has a black background** — do not place on app surfaces. See asset note below. |

**Usage rules:**
- **Match the variant to the background.** White wordmark on dark/navy; the navy chip (or square/circle) when the surrounding surface is light. Never place a black-backed raw file on an app surface.
- **Don't recolor, stretch, rotate, or add effects.** Scale proportionally only.
- **Clear space:** keep padding around the mark equal to at least the height of the "A.L.M." line. (The navy chip already bakes in correct clear space.)
- **Sizing:** login ~`50–80px` tall; header ~`50px`; favicon/PWA use the square or circle fill version.
- **Alt text:** always `alt="Claflin A.L.M."`.
- App-specific second logos (e.g. a partner mark) sit beside the Claflin mark separated by a `1px --border-light` divider, per §3.1/§3.2.

> ⚠️ **Asset note:** clean **transparent white wordmark** and a **navy chip** (white mark on a navy tile) have been produced and are the assets to use on dark and light surfaces respectively. The only gap is a **transparent *blue* wordmark for direct placement on light backgrounds** — the supplied blue file is a dark navy mark on solid black with too little tonal separation to knock out cleanly, so use the navy chip for on-light placement, or request a **vector (SVG)** blue wordmark from Marketing for a pristine transparent blue mark. Prefer SVG for all wordmark uses where available — it stays crisp at any size.

---

## 5. Quick reference — do / don't

**Do:** declare tokens first; one sans family (Open Sans) at moderate sizes; brand-navy primary (#0A3B73) + cyan accent used sparingly; UPPERCASE letter-spaced labels everywhere; 12px cards / 8px buttons & inputs; cyan focus rings; the cyan bottom-rule on light headers; 44–48px touch targets and 16px inputs on mobile; reproduce the login layout exactly; light mode.

**Don't:** hardcode hex values inline; add a serif or second font; inflate text/inputs to fill space (1rem base, not 1.1+); introduce new accent colors; use cyan for small body text (fails contrast); stack two filled primary buttons; add decorative/looping animation; generate dark mode; redesign the login per app.

---

## 6. Mobile & touch

Apply these whenever an app will run on phones/tablets or as a PWA. They don't change the visual language — they make it usable under a finger. On desktop-only internal tools they're harmless to include but not required.

### 6.1 Touch targets
- Minimum hit area **44×44px** for any tappable control; **48px** min-height for primary actions and list rows. Use padding (not just font size) to reach it.
- Space adjacent tap targets at least `8px` apart so fingers don't mis-hit.
- For coarse pointers, enforce it:
  ```css
  @media (pointer: coarse) { .touch-target { min-height:44px; min-width:44px; } }
  ```

### 6.2 Inputs — prevent the zoom trap
- Form inputs render at **`16px`** font on touch devices. Anything smaller makes iOS Safari auto-zoom on focus, which jolts the layout. This overrides the normal `--font-size-sm` for inputs on mobile.
- Keep generous vertical padding on inputs/buttons (`py-4` / ~`1rem`) so they're comfortable to tap.
- Set the right `inputmode`/`type` (`type="number"`, `inputmode="numeric"`, `type="email"`, etc.) so the correct keyboard appears.

### 6.3 Layout & scroll
- Single-column layouts on small screens; stack toolbar controls; let tables scroll horizontally inside a wrapper rather than squashing.
- If the app locks the viewport (kiosk/PWA pattern), pin `html, body { height:100%; overflow:hidden; position:fixed; width:100% }` and make the scroll container `#root { height:100%; overflow-y:auto; -webkit-overflow-scrolling:touch }`.
- Disable tap highlight and accidental text selection on chrome elements (`-webkit-tap-highlight-color: transparent`), but keep selection enabled on actual content and inputs.
- Add `<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">` and respect safe-area insets on notched devices.

### 6.4 Connectivity & PWA (when offline-capable)
- **Offline indicator:** a fixed banner near the top using the warning tint (amber on `--color-warning` tint), with a small pulsing dot, shown only when the connection drops. Don't block the UI — inform.
- **Loading/skeleton states:** for slow or offline-first data, show skeleton placeholders (light `--muted` blocks with a subtle pulse) rather than spinners alone; reserve the spinning ring (in `--color-primary`) for explicit actions like scanning or submitting.
- Status pills for sync state reuse the §3.7 badge styles (e.g. info-cyan "Syncing", success-teal "Synced", warning-amber "Offline").

---

## 7. Reference & maintenance

*Reference implementations: NP Medical App (palette, type, components), Time Tracker (header, stats, tables, login backdrop, badge system), and Hospital Inventory (mobile/touch and PWA patterns). When this document is silent on a case, choose the option most consistent with the rules above. Brand colors and logos are confirmed (official Claflin A.L.M. 2025 assets); transparent white wordmark and navy chip are produced and ready (§4). The one open item is an optional transparent/SVG **blue** wordmark from Marketing for direct on-light placement.*
