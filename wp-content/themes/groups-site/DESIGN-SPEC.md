# WordPress Community Groups -- Visual Design Specification

**Issue:** #159
**Date:** 2026-03-20
**Status:** Draft
**Target:** `theme.json` + custom CSS in `assets/css/`

---

## Design Philosophy

Warm, inviting, community-first. The visual language should feel like walking into a well-lit coworking space -- not a corporate portal, not a tech startup dashboard. Events and people are the primary content; the chrome should disappear.

Key principles:
- **Content density over decoration.** No gratuitous gradients, no hero illustrations. Let event data breathe.
- **Warmth through color temperature.** Shift away from cold blue/charcoal toward warm neutrals with a single vibrant accent.
- **Typographic hierarchy does the heavy lifting.** Distinct heading and body faces create structure without needing heavy dividers or boxes.
- **Generous whitespace.** Inspired by Notion and Linear -- let elements float in space rather than cramming them into cards.

---

## 1. Color Palette

### Primary

| Token              | Hex       | Usage                                      |
|--------------------|-----------|---------------------------------------------|
| `primary-900`      | `#1B2559` | Darkest primary, focus rings, active states |
| `primary-800`      | `#243380` | Primary text on light backgrounds           |
| `primary-700`      | `#2D41A6` | Default links, primary buttons              |
| `primary-600`      | `#3B54D4` | Primary button hover                        |
| `primary-500`      | `#5570F1` | Interactive accent, active nav indicator     |
| `primary-400`      | `#7B91F5` | Secondary interactive elements              |
| `primary-300`      | `#A8B8FA` | Tags, light badges                          |
| `primary-200`      | `#CDD5FC` | Subtle backgrounds, selected states         |
| `primary-100`      | `#E8ECFE` | Tinted surface backgrounds                  |
| `primary-50`       | `#F4F6FF` | Barely-there tint for alternating rows      |

### Accent (Warm Coral)

| Token              | Hex       | Usage                                       |
|--------------------|-----------|----------------------------------------------|
| `accent-700`       | `#B83D2B` | Accent text on light backgrounds             |
| `accent-600`       | `#D4503C` | Accent button default                        |
| `accent-500`       | `#EF6351` | Primary accent -- CTA highlights, urgency    |
| `accent-400`       | `#F4887A` | Accent hover states                          |
| `accent-300`       | `#F9AEA4` | Accent badges, light decorative              |
| `accent-200`       | `#FCDAD5` | Accent tinted backgrounds                    |
| `accent-100`       | `#FEF0EE` | Subtle accent surface                        |

### Neutrals (Warm Gray)

| Token              | Hex       | Usage                                       |
|--------------------|-----------|----------------------------------------------|
| `neutral-950`      | `#141118` | Maximum contrast text (headings)             |
| `neutral-900`      | `#1C1922` | Body text default                            |
| `neutral-800`      | `#2E2B36` | Secondary headings                           |
| `neutral-700`      | `#47434F` | Strong secondary text                        |
| `neutral-600`      | `#635E6C` | Muted text, labels                           |
| `neutral-500`      | `#817C8A` | Placeholder text, disabled text              |
| `neutral-400`      | `#A9A5B0` | Icons (inactive), metadata                   |
| `neutral-300`      | `#CCC9D1` | Borders, dividers (strong)                   |
| `neutral-200`      | `#E4E2E7` | Borders, dividers (default)                  |
| `neutral-150`      | `#EDEBF0` | Card borders, subtle separators              |
| `neutral-100`      | `#F5F4F7` | Page background (secondary)                  |
| `neutral-50`       | `#FAFAFA` | Card backgrounds, input backgrounds          |
| `neutral-0`        | `#FFFFFF` | Base surface                                 |

### Semantic

| Token              | Hex       | Usage                                       |
|--------------------|-----------|----------------------------------------------|
| `success-700`      | `#15803D` | Success text                                 |
| `success-500`      | `#22C55E` | Success icons, confirmed badges              |
| `success-100`      | `#F0FDF4` | Success background tint                      |
| `warning-700`      | `#A16207` | Warning text                                 |
| `warning-500`      | `#EAB308` | Warning icons, waitlist badges               |
| `warning-100`      | `#FEFCE8` | Warning background tint                      |
| `error-700`        | `#B91C1C` | Error text, destructive actions              |
| `error-500`        | `#EF4444` | Error icons, validation states               |
| `error-100`        | `#FEF2F2` | Error background tint                        |
| `info-700`         | `#1D4ED8` | Info text                                    |
| `info-500`         | `#3B82F6` | Info icons                                   |
| `info-100`         | `#EFF6FF` | Info background tint                         |

### Dark Surface (Global Header, Hero)

| Token              | Hex       | Usage                                       |
|--------------------|-----------|----------------------------------------------|
| `surface-dark-1`   | `#141118` | Deepest dark background (global header)      |
| `surface-dark-2`   | `#1C1922` | Hero background, footer                      |
| `surface-dark-3`   | `#2E2B36` | Elevated dark surfaces                       |
| `surface-dark-text`| `#E4E2E7` | Body text on dark surfaces                   |
| `surface-dark-muted`| `#817C8A`| Secondary text on dark surfaces              |

---

## 2. Typography

### Font Stacks

| Token        | Family                                                                                   | Google Fonts URL                              |
|--------------|------------------------------------------------------------------------------------------|-----------------------------------------------|
| `heading`    | `'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`        | `Plus+Jakarta+Sans:wght@500;600;700;800`      |
| `body`       | `'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`            | `Inter:wght@400;500;600`                       |
| `mono`       | `'JetBrains Mono', 'IBM Plex Mono', 'Fira Code', monospace`                             | `JetBrains+Mono:wght@400;500`                 |

### Type Scale

All sizes use `clamp()` for fluid scaling between 375px and 1440px viewport widths.

| Element    | Min (px) | Preferred            | Max (px) | Weight | Line Height | Letter Spacing | Font Family |
|------------|----------|----------------------|----------|--------|-------------|----------------|-------------|
| `h1`       | 32       | `2rem + 1.8vw`       | 56       | 800    | 1.1         | `-0.025em`     | heading     |
| `h2`       | 26       | `1.625rem + 1.2vw`   | 42       | 700    | 1.15        | `-0.02em`      | heading     |
| `h3`       | 22       | `1.375rem + 0.6vw`   | 30       | 700    | 1.2         | `-0.015em`     | heading     |
| `h4`       | 18       | `1.125rem + 0.4vw`   | 24       | 600    | 1.3         | `-0.01em`      | heading     |
| `h5`       | 16       | `1rem + 0.2vw`       | 20       | 600    | 1.35        | `0`            | heading     |
| `h6`       | 14       | `0.875rem + 0.1vw`   | 16       | 600    | 1.4         | `0.02em`       | heading     |
| `body`     | 15       | `0.9375rem + 0.1vw`  | 17       | 400    | 1.65        | `0`            | body        |
| `body-lg`  | 17       | `1.0625rem + 0.15vw` | 19       | 400    | 1.6         | `0`            | body        |
| `small`    | 13       | `0.8125rem + 0.05vw` | 14       | 400    | 1.5         | `0.01em`       | body        |
| `caption`  | 11       | `0.6875rem`          | 12       | 500    | 1.4         | `0.03em`       | body        |
| `overline` | 11       | `0.6875rem`          | 12       | 600    | 1.2         | `0.08em`       | body        |

### CSS Custom Properties (example output)

```css
--font-heading: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
--font-body: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
--font-mono: 'JetBrains Mono', 'IBM Plex Mono', monospace;

--text-h1: clamp(32px, 2rem + 1.8vw, 56px);
--text-h2: clamp(26px, 1.625rem + 1.2vw, 42px);
--text-h3: clamp(22px, 1.375rem + 0.6vw, 30px);
--text-h4: clamp(18px, 1.125rem + 0.4vw, 24px);
--text-h5: clamp(16px, 1rem + 0.2vw, 20px);
--text-h6: clamp(14px, 0.875rem + 0.1vw, 16px);
--text-body: clamp(15px, 0.9375rem + 0.1vw, 17px);
--text-body-lg: clamp(17px, 1.0625rem + 0.15vw, 19px);
--text-small: clamp(13px, 0.8125rem + 0.05vw, 14px);
--text-caption: clamp(11px, 0.6875rem, 12px);
```

---

## 3. Spacing System

**Base unit:** 4px
**Scale:** Increments of 4px, with named tokens at common sizes.

| Token    | Value   | Common Use                                  |
|----------|---------|----------------------------------------------|
| `space-1`  | `4px`   | Tight inline gaps, icon-to-label             |
| `space-2`  | `8px`   | Compact element spacing, badge padding       |
| `space-3`  | `12px`  | Input padding (vertical), small card padding |
| `space-4`  | `16px`  | Default element gap, input padding (horiz)   |
| `space-5`  | `20px`  | Card internal padding (compact)              |
| `space-6`  | `24px`  | Card internal padding (default)              |
| `space-8`  | `32px`  | Section gaps (small), card outer margins     |
| `space-10` | `40px`  | Section padding (compact)                    |
| `space-12` | `48px`  | Section padding (default)                    |
| `space-16` | `64px`  | Section padding (generous)                   |
| `space-20` | `80px`  | Page-level top/bottom padding                |
| `space-24` | `96px`  | Hero section vertical padding                |

### Edge Space (Horizontal page gutters)

```css
--edge-space: clamp(16px, 4vw, 80px);
```

### Layout Widths

| Token         | Value    | Usage                          |
|---------------|----------|--------------------------------|
| `content-sm`  | `640px`  | Narrow text content, forms     |
| `content-md`  | `768px`  | Blog posts, single event text  |
| `content-lg`  | `960px`  | Default content width          |
| `content-xl`  | `1120px` | Wide content, two-column       |
| `page-max`    | `1280px` | Maximum page width             |

### Border Radius

| Token         | Value  | Usage                            |
|---------------|--------|----------------------------------|
| `radius-sm`   | `4px`  | Badges, small chips              |
| `radius-md`   | `8px`  | Buttons, inputs, small cards     |
| `radius-lg`   | `12px` | Cards, modals                    |
| `radius-xl`   | `16px` | Hero images, large containers    |
| `radius-full` | `9999px` | Avatars, pills                 |

### Shadows

| Token           | Value                                                         | Usage                 |
|-----------------|---------------------------------------------------------------|-----------------------|
| `shadow-sm`     | `0 1px 2px rgba(20, 17, 24, 0.05)`                           | Subtle lift           |
| `shadow-md`     | `0 2px 8px rgba(20, 17, 24, 0.08), 0 1px 2px rgba(20, 17, 24, 0.04)` | Cards default  |
| `shadow-lg`     | `0 8px 24px rgba(20, 17, 24, 0.12), 0 2px 8px rgba(20, 17, 24, 0.06)` | Cards hover, dropdowns |
| `shadow-xl`     | `0 16px 48px rgba(20, 17, 24, 0.16), 0 4px 12px rgba(20, 17, 24, 0.08)` | Modals        |

---

## 4. Components

### 4.1 Event Card

The primary content unit. Used on group homepages, event archives, and the directory.

```
+--------------------------------------------------+
|  [Thumbnail Image -- 16:9 aspect ratio]          |
|  (fallback: gradient from primary-100 to          |
|   accent-100 with calendar icon in primary-300)   |
+--------------------------------------------------+
|  space-6 padding all sides                        |
|                                                   |
|  OVERLINE (caption size, neutral-500, uppercase,  |
|  600 weight, 0.08em letter-spacing):              |
|  "SAT, MAR 28 · 2:00 PM"                         |
|                                                   |
|  space-2 gap                                      |
|                                                   |
|  TITLE (h4 size, neutral-950, heading font,       |
|  600 weight, max 2 lines, ellipsis overflow):     |
|  "Building Your First Block Theme"                |
|                                                   |
|  space-3 gap                                      |
|                                                   |
|  LOCATION (small size, neutral-600):              |
|  pin-icon(16px) + "Coffee & Code, 123 Main St"   |
|                                                   |
|  space-4 gap                                      |
|                                                   |
|  FOOTER ROW (flex, space-between, align-center):  |
|  [Avatar stack (max 3, 24px each, -8px overlap)]  |
|  [Attendee count: "12 going" small, neutral-500]  |
|                                                   |
+--------------------------------------------------+
```

**Sizing:**
- Card min-width: `280px`
- Card max-width: `400px` (in grid, fills column)
- Thumbnail height: `180px` (object-fit: cover)
- Card border: `1px solid neutral-200`
- Card border-radius: `radius-lg` (12px)
- Card background: `neutral-0` (#FFFFFF)

**States:**
- **Default:** `shadow-sm`, border `neutral-200`
- **Hover:** `shadow-lg`, border `neutral-300`, translate `0, -2px`, thumbnail image scale `1.03`. Transition: `all 0.2s ease-out`
- **Focus-visible:** `outline: 2px solid primary-500`, `outline-offset: 2px`, no shadow change
- The entire card is a single click target (anchor wrapping the card)

**Past event variant:**
- Thumbnail has a `neutral-900` overlay at 40% opacity
- A "PAST" badge overlays top-right of thumbnail: `caption` size, `neutral-0` text on `neutral-700` background, `radius-sm`, `space-1` vertical / `space-2` horizontal padding

### 4.2 Group Card (Directory)

Used on the central directory page to browse all community groups.

```
+--------------------------------------------------+
|  space-6 padding all sides                        |
|                                                   |
|  TOP ROW (flex, gap space-4, align-center):       |
|  [Group Avatar: 48px, radius-lg, border 2px       |
|   neutral-200. Fallback: initials on              |
|   primary-100 bg with primary-700 text]           |
|  [GROUP NAME: h4, neutral-950, heading font]      |
|                                                   |
|  space-3 gap                                      |
|                                                   |
|  LOCATION (small, neutral-600):                   |
|  globe-icon(14px) + "Tokyo, Japan"                |
|                                                   |
|  space-3 gap                                      |
|                                                   |
|  DESCRIPTION (body size, neutral-700,             |
|  max 2 lines, ellipsis):                          |
|  "A community of WordPress enthusiasts..."        |
|                                                   |
|  space-4 gap                                      |
|                                                   |
|  STATS ROW (flex, gap space-6):                   |
|  [people-icon + "142 members" small neutral-500]  |
|  [calendar-icon + "3 upcoming" small neutral-500] |
|                                                   |
|  space-4 gap                                      |
|                                                   |
|  TAGS (flex, gap space-2, wrap):                  |
|  [Tag pill: caption, primary-700 text,            |
|   primary-100 bg, radius-full, px space-3,        |
|   py space-1]                                     |
|  Examples: "WordPress" "Development" "Design"     |
|                                                   |
+--------------------------------------------------+
```

**Sizing:**
- Same border/radius/shadow behavior as Event Card
- Min-width: `300px`

**States:**
- Same hover/focus pattern as Event Card
- Entire card is a link

### 4.3 RSVP Button

Four distinct states. The button lives inside the event sidebar info card.

**Dimensions:**
- Full width of its container
- Height: `48px`
- Border-radius: `radius-md` (8px)
- Font: body font, `16px`, weight `600`
- Transition: `all 0.15s ease`

| State         | Background     | Text           | Border               | Icon (left)       | Extra                    |
|---------------|----------------|----------------|----------------------|-------------------|--------------------------|
| **Not Attending** (default) | `primary-700` (#2D41A6) | `neutral-0` (#FFF) | none | none | cursor: pointer |
| **Not Attending** (hover) | `primary-600` (#3B54D4) | `neutral-0` (#FFF) | none | none | `shadow-sm` |
| **Attending** (confirmed) | `success-100` (#F0FDF4) | `success-700` (#15803D) | `1px solid` `success-500` | checkmark 18px `success-500` | Shows "You're going" |
| **Attending** (hover -- reveals cancel) | `error-100` (#FEF2F2) | `error-700` (#B91C1C) | `1px solid` `error-500` | x-circle 18px `error-500` | Text changes to "Cancel RSVP" |
| **Waitlisted** | `warning-100` (#FEFCE8) | `warning-700` (#A16207) | `1px solid` `warning-500` | clock 18px `warning-500` | Shows "On waitlist" |
| **Waitlisted** (hover) | `error-100` | `error-700` | `1px solid error-500` | x-circle 18px | Text: "Leave waitlist" |
| **Loading**    | `neutral-100`  | `neutral-400`  | `1px solid neutral-200` | spinner 18px (animating) | `pointer-events: none`, `opacity: 0.7` |
| **Disabled** (past event / logged out) | `neutral-100` | `neutral-400` | `1px solid neutral-200` | none | `cursor: not-allowed` |

### 4.4 Member Avatar + Name + Role Badge

Used in RSVP lists, member grids, and attendee sections.

**Inline variant** (RSVP list, attendee row):
```
[Avatar 36px radius-full] space-3 [Name: body, 500wt, neutral-900] space-2 [Badge]
```

**Card variant** (Members page grid):
```
+--------------------------------------------+
| space-6 padding, text-align: center        |
|                                            |
| [Avatar 64px radius-full, centered]        |
| space-3                                    |
| [Name: h5 size, neutral-950, heading font] |
| space-1                                    |
| [Username: small, neutral-500]             |
| space-2                                    |
| [Badge]                                    |
+--------------------------------------------+
```

**Avatar:**
- Sizes: `24px` (stacked), `36px` (inline), `48px` (group card), `64px` (member card), `96px` (profile)
- Border-radius: `radius-full`
- Border: `2px solid neutral-0` (only when stacked/overlapping)
- Fallback: Initials rendered in `heading` font, `600` weight, centered on a background color derived from a hash of the username. Use this palette for fallback backgrounds: `primary-200`, `accent-200`, `success-100`, `warning-100`, `info-100` (cycle based on first letter).

**Role Badges:**

| Role        | Text Color     | Background     | Border                |
|-------------|----------------|----------------|-----------------------|
| Organizer   | `primary-800`  | `primary-100`  | `1px solid primary-200` |
| Co-Organizer| `primary-700`  | `primary-50`   | `1px solid primary-200` |
| Speaker     | `accent-700`   | `accent-100`   | `1px solid accent-200`  |
| Member      | `neutral-600`  | `neutral-100`  | `1px solid neutral-200` |

Badge dimensions: `caption` font, `500` weight, `radius-full`, padding `space-1` vertical / `space-3` horizontal. Uppercase, `0.04em` letter-spacing.

### 4.5 Navigation

#### Global Header (WordPress.org bar)

- Background: `surface-dark-1` (#141118)
- Height: `40px`
- Content: WordPress.org link (left) + secondary links (right)
- Text: `caption` size, `500` weight
- Link color: `surface-dark-muted` (#817C8A)
- Link hover: `neutral-0` (#FFFFFF)
- WordPress.org link: `surface-dark-text` (#E4E2E7), `600` weight
- Padding: `0 var(--edge-space)`
- Border-bottom: `1px solid` `surface-dark-3`

#### Local Header (Group navigation)

- Background: `neutral-0` (#FFFFFF)
- Height: `60px`
- Content: Site title (left) + primary nav links (right)
- Border-bottom: `1px solid` `neutral-200`
- Site title: `heading` font, `h5` size, `700` weight, `neutral-950`
- Nav links: `body` font, `small` size, `500` weight, `neutral-700`
- Nav link hover: `neutral-950`
- Active nav link: `primary-700` text, with a `2px` bottom border in `primary-500` (inset, touching the header bottom border)
- Padding: `0 var(--edge-space)`
- Sticky on scroll: `position: sticky; top: 0; z-index: 100;` with `backdrop-filter: blur(8px)` and `background: rgba(255,255,255,0.9)`

#### Sub-Navigation (Group sections)

Only appears on group pages when there are multiple sections (Events, Members, About, etc.).

- Background: `neutral-0`
- Border-bottom: `1px solid neutral-200`
- Horizontally scrollable on mobile (overflow-x: auto, no scrollbar)
- Tab-style items: `small` font, `500` weight, `neutral-600`
- Active tab: `neutral-950` text, `2px` bottom border `primary-500`
- Tab hover: `neutral-800` text
- Tab padding: `space-3` vertical, `space-4` horizontal
- No background changes on tab hover (just text color)

### 4.6 Footer

Two-tier structure.

#### Community Callout Band
- Background: `surface-dark-2` (#1C1922)
- Padding: `space-12` vertical, `var(--edge-space)` horizontal
- Layout: flex, space-between, wrap
- Text: `heading` font, `h4` size, `500` weight, `surface-dark-text`
- CTA button: outline style, `neutral-0` text, border `1px solid rgba(255,255,255,0.2)`, `radius-md`, hover fill `rgba(255,255,255,0.1)`

#### Footer Proper
- Background: `surface-dark-1` (#141118)
- Padding: `space-10` vertical, `var(--edge-space)` horizontal
- Layout: flex, space-between, wrap
- Links: `small` font, `400` weight, `surface-dark-muted`
- Link hover: `neutral-0`
- "Code is Poetry" tagline: `caption` font, `neutral-500`, `mono` font
- Separator between link groups: `1px` vertical divider in `surface-dark-3` (desktop) or omitted (mobile)

### 4.7 Form Fields

#### Text Input / Textarea

- Height: `44px` (input), auto (textarea, min `120px`)
- Padding: `space-3` vertical, `space-4` horizontal
- Background: `neutral-0`
- Border: `1px solid neutral-300`
- Border-radius: `radius-md` (8px)
- Font: `body`, `body` size, `neutral-900`
- Placeholder: `neutral-500`
- Focus: border `primary-500`, ring `0 0 0 3px primary-200`, outline none
- Error: border `error-500`, ring `0 0 0 3px` `rgba(239, 68, 68, 0.15)`
- Disabled: background `neutral-100`, text `neutral-400`, border `neutral-200`, cursor not-allowed
- Transition: `border-color 0.15s ease, box-shadow 0.15s ease`

#### Label

- Font: `body`, `small` size, `600` weight, `neutral-800`
- Margin-bottom: `space-2`
- Required indicator: `*` in `error-500`, `space-1` left margin

#### Helper Text

- Font: `small` size, `400` weight, `neutral-500`
- Margin-top: `space-2`
- Error variant: `error-700` text

#### Select

- Same dimensions and styling as text input
- Custom chevron icon: `neutral-400`, `16px`, positioned right `space-4`
- `appearance: none`

#### Checkbox / Radio

- Size: `18px` x `18px`
- Border: `2px solid neutral-300`
- Border-radius: `radius-sm` (checkbox), `radius-full` (radio)
- Checked: background `primary-700`, border `primary-700`, white checkmark/dot
- Focus: `0 0 0 3px primary-200`
- Label gap: `space-3`

### 4.8 Status Badges

Pill-shaped badges for event and RSVP states.

**Dimensions:** `caption` font, `600` weight, uppercase, `0.04em` letter-spacing, `radius-full`, padding `space-1` vertical / `space-3` horizontal.

| Badge           | Text Color     | Background     | Left Icon (12px) |
|-----------------|----------------|----------------|-------------------|
| Upcoming        | `primary-800`  | `primary-100`  | dot `primary-500` |
| Happening Now   | `success-700`  | `success-100`  | pulsing dot `success-500` |
| Past            | `neutral-600`  | `neutral-100`  | none              |
| Cancelled       | `error-700`    | `error-100`    | x-circle `error-500` |
| Draft           | `warning-700`  | `warning-100`  | pencil `warning-500` |
| Full            | `neutral-600`  | `neutral-100`  | lock `neutral-400` |
| Spots Available | `success-700`  | `success-100`  | none              |
| Online          | `info-700`     | `info-100`     | video `info-500`  |
| In Person       | `neutral-700`  | `neutral-100`  | map-pin `neutral-500` |
| Hybrid          | `primary-700`  | `primary-100`  | monitor `primary-500` |

---

## 5. Page Layouts

### 5.1 Group Homepage

The landing page for an individual community group. Events are the hero content.

```
+================================================================+
| GLOBAL HEADER (40px, dark)                                      |
+================================================================+
| LOCAL HEADER (60px, sticky)                                     |
| [Group Name]                       [Events] [Members] [About]  |
+================================================================+
|                                                                  |
|  HERO SECTION                                                    |
|  Background: neutral-100                                         |
|  Padding: space-16 top, space-12 bottom                         |
|  Max-width: page-max (1280px), centered                         |
|                                                                  |
|  [Group Avatar 64px]                                             |
|  space-4                                                         |
|  [Group Name: h1, neutral-950]                                   |
|  space-2                                                         |
|  [Tagline: body-lg, neutral-600, max 2 lines]                   |
|  space-4                                                         |
|  [Stats row: "142 members · 28 events · Tokyo, Japan"           |
|   small, neutral-500, dot-separated]                             |
|  space-6                                                         |
|  [Join Group button + Share button (icon only, ghost)]           |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  NEXT EVENT SPOTLIGHT (only if upcoming event exists)            |
|  Padding: space-12 top, space-8 bottom                          |
|  Max-width: page-max, centered                                  |
|                                                                  |
|  Overline: "NEXT EVENT" (overline style, primary-500)            |
|  space-3                                                         |
|  Full-width card variant:                                        |
|  +------------------------------------------------------------+ |
|  | Flex row (image left 40%, content right 60%)                | |
|  | Image: event thumbnail, radius-lg left corners only         | |
|  | Content: space-8 padding                                    | |
|  |   Date: overline style, neutral-500                         | |
|  |   Title: h2, neutral-950                                    | |
|  |   Description: body, neutral-700, max 3 lines               | |
|  |   space-4                                                   | |
|  |   Location + Attendee count                                 | |
|  |   space-6                                                   | |
|  |   [RSVP button, inline, max-width 200px]                    | |
|  +------------------------------------------------------------+ |
|  Card: neutral-0 bg, 1px neutral-200 border, radius-lg,         |
|         shadow-md, hover shadow-lg                               |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  UPCOMING EVENTS GRID                                            |
|  Padding: space-8 top, space-12 bottom                          |
|  Max-width: page-max, centered                                  |
|                                                                  |
|  Section heading row (flex, space-between):                      |
|  [h2 "Upcoming Events"]  [Link "View all ->" small, primary-700]|
|  space-8                                                         |
|  Grid: 3 columns desktop, 2 tablet, 1 mobile                    |
|  Gap: space-6                                                    |
|  [Event Card] [Event Card] [Event Card]                          |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  ABOUT SECTION                                                   |
|  Padding: space-12 vertical                                      |
|  Max-width: content-md (768px), left-aligned within page-max     |
|  Background: neutral-0 (default page bg)                         |
|  Top border: 1px solid neutral-200                               |
|                                                                  |
|  [h2 "About This Group"]                                         |
|  space-4                                                         |
|  [Post content: body, neutral-800, standard WP block content]    |
|                                                                  |
+------------------------------------------------------------------+
| COMMUNITY CALLOUT BAND                                           |
+------------------------------------------------------------------+
| FOOTER                                                           |
+================================================================+
```

**Page background:** `neutral-0` for content sections. Hero is `neutral-100`.

### 5.2 Single Event Page

Two-column layout: wide content left, narrow info card right.

```
+================================================================+
| GLOBAL HEADER                                                    |
+================================================================+
| LOCAL HEADER                                                     |
+================================================================+
|                                                                  |
|  FEATURED IMAGE / HERO                                           |
|  Full-bleed to page-max width                                    |
|  Aspect ratio: 3:1 (max-height 400px)                           |
|  Border-radius: 0 (full bleed) or radius-xl at page-max         |
|  Fallback: gradient primary-100 -> accent-100,                   |
|    subtle pattern overlay                                        |
|  Margin-bottom: space-10                                         |
|                                                                  |
+------------------------------------------------------------------+
|  Max-width: content-xl (1120px), centered                        |
|  Padding: 0 var(--edge-space) space-16                           |
|                                                                  |
|  +---------------------------+  space-10  +------------------+   |
|  | CONTENT COLUMN (62%)      |  (gap)     | SIDEBAR (38%)    |   |
|  |                           |            |                  |   |
|  | [Status Badge: "Upcoming"]|            | INFO CARD        |   |
|  | space-3                   |            | (sticky top 92px)|   |
|  | [Title: h1]               |            |                  |   |
|  | space-6                   |            | Background:      |   |
|  |                           |            |   neutral-0      |   |
|  | [h3 "Details"]            |            | Border: 1px      |   |
|  | space-3                   |            |   neutral-200    |   |
|  | [Post content]            |            | Radius: radius-lg|   |
|  |                           |            | Shadow: shadow-md |   |
|  | space-10                  |            | Padding: space-6  |   |
|  |                           |            |                  |   |
|  | [h3 "Attendees"]          |            | Sections inside: |   |
|  | space-3                   |            |                  |   |
|  | [RSVP list grid:          |            | DATE & TIME      |   |
|  |  3 cols, avatar+name      |            | (calendar icon)  |   |
|  |  inline variant]          |            | "Sat, Mar 28"    |   |
|  |                           |            | "2:00 - 4:00 PM" |   |
|  |                           |            | [Add to Calendar]|   |
|  |                           |            |                  |   |
|  |                           |            | -- divider --    |   |
|  |                           |            |                  |   |
|  |                           |            | VENUE            |   |
|  |                           |            | (map-pin icon)   |   |
|  |                           |            | "Coffee & Code"  |   |
|  |                           |            | "123 Main St"    |   |
|  |                           |            | [Mini map embed] |   |
|  |                           |            |                  |   |
|  |                           |            | -- divider --    |   |
|  |                           |            |                  |   |
|  |                           |            | ATTENDEES        |   |
|  |                           |            | Avatar stack +   |   |
|  |                           |            | "12 / 30 spots"  |   |
|  |                           |            |                  |   |
|  |                           |            | -- divider --    |   |
|  |                           |            |                  |   |
|  |                           |            | [RSVP BUTTON]    |   |
|  |                           |            | (full width)     |   |
|  +---------------------------+            +------------------+   |
|                                                                  |
+------------------------------------------------------------------+
| FOOTER                                                           |
+================================================================+
```

**Info card dividers:** `1px solid neutral-150`, margin `space-5` vertical.
**Info card section labels:** `overline` style, `neutral-500`, margin-bottom `space-2`.
**Info card values:** `body` size, `500` weight, `neutral-900`.
**Sidebar sticky:** `top: 92px` (header height 60px + space-8 offset). On mobile (below 1024px), sidebar stacks below content and is not sticky.

### 5.3 Event Archive Page

List of upcoming and past events with filtering.

```
+================================================================+
| GLOBAL HEADER                                                    |
+================================================================+
| LOCAL HEADER                                                     |
+================================================================+
|                                                                  |
|  Max-width: page-max, centered                                   |
|  Padding: space-12 top                                           |
|                                                                  |
|  [h1 "Events"]                                                   |
|  space-8                                                         |
|                                                                  |
|  FILTER BAR                                                      |
|  flex row, gap space-3, wrap, align-center                       |
|  margin-bottom: space-8                                          |
|  +------------------------------------------------------------+ |
|  | [Segmented control: "Upcoming" | "Past"]                    | |
|  |   Active: primary-700 bg, neutral-0 text                   | |
|  |   Inactive: transparent bg, neutral-600 text               | |
|  |   Container: neutral-100 bg, radius-md, 1px neutral-200    | |
|  |   Each segment: space-2 vert / space-5 horiz padding       | |
|  |                                                             | |
|  | [Search input: 280px, magnifying glass icon left]           | |
|  +------------------------------------------------------------+ |
|                                                                  |
|  UPCOMING SECTION                                                |
|  [h2 "Upcoming" -- only if both sections visible]               |
|  space-6                                                         |
|  Grid: 3 columns desktop, 2 tablet, 1 mobile                    |
|  Gap: space-6                                                    |
|  [Event Card] [Event Card] [Event Card]                          |
|  [Event Card] [Event Card] [Event Card]                          |
|                                                                  |
|  space-16                                                        |
|  Divider: 1px solid neutral-200, full width within page-max      |
|  space-16                                                        |
|                                                                  |
|  PAST SECTION                                                    |
|  [h2 "Past Events"]                                              |
|  space-6                                                         |
|  Grid: same as above                                             |
|  [Past Event Card] [Past Event Card] [Past Event Card]           |
|                                                                  |
|  space-10                                                        |
|  [Pagination: centered, body font, small size]                   |
|  "< Previous   1  2  3  ...  12   Next >"                        |
|  Active page: primary-700 text, 600 weight                       |
|  Other pages: neutral-600 text, hover neutral-900                |
|  Prev/Next: neutral-500, hover neutral-900                       |
|                                                                  |
+------------------------------------------------------------------+
| FOOTER                                                           |
+================================================================+
```

### 5.4 Members Page

Grid of member cards.

```
+================================================================+
| GLOBAL HEADER                                                    |
+================================================================+
| LOCAL HEADER                                                     |
+================================================================+
|                                                                  |
|  Max-width: page-max, centered                                   |
|  Padding: space-12 top                                           |
|                                                                  |
|  [h1 "Members"]                                                  |
|  space-2                                                         |
|  [Paragraph: "142 community members" body, neutral-600]          |
|  space-8                                                         |
|                                                                  |
|  ORGANIZERS SECTION (if any)                                     |
|  [Overline: "ORGANIZERS"]                                        |
|  space-4                                                         |
|  Grid: 4 columns desktop, 3 tablet, 2 mobile                    |
|  Gap: space-4                                                    |
|  [Member Card] [Member Card] [Member Card]                       |
|  Card variant: neutral-0 bg, 1px neutral-200 border,             |
|    radius-lg, shadow-sm, hover shadow-md                         |
|                                                                  |
|  space-12                                                        |
|                                                                  |
|  ALL MEMBERS SECTION                                             |
|  [Overline: "ALL MEMBERS"]                                       |
|  space-4                                                         |
|  Grid: 4 columns desktop, 3 tablet, 2 mobile                    |
|  Gap: space-4                                                    |
|  [Member Card] [Member Card] [Member Card] [Member Card]         |
|  [Member Card] [Member Card] [Member Card] [Member Card]         |
|                                                                  |
|  space-10                                                        |
|  [Pagination]                                                    |
|                                                                  |
+------------------------------------------------------------------+
| FOOTER                                                           |
+================================================================+
```

### 5.5 Directory Homepage

The top-level page listing all WordPress community groups. This is the front page of the multisite/network.

```
+================================================================+
| GLOBAL HEADER                                                    |
+================================================================+
|                                                                  |
|  HERO SECTION                                                    |
|  Background: linear-gradient(135deg, surface-dark-1, #1a1540)    |
|  Padding: space-24 top, space-20 bottom                         |
|  Text-align: center                                              |
|                                                                  |
|  [Overline: "WORDPRESS COMMUNITY" primary-400, overline style]   |
|  space-3                                                         |
|  [h1: "Find Your Local WordPress Community"                      |
|   neutral-0, max-width 720px, centered]                          |
|  space-4                                                         |
|  [Subtitle: body-lg, surface-dark-text,                          |
|   max-width 560px, centered                                      |
|   "Connect with WordPress enthusiasts near you.                  |
|    Join meetups, workshops, and contributor days."]               |
|  space-10                                                        |
|                                                                  |
|  SEARCH BAR (centered, max-width 560px)                          |
|  +------------------------------------------------------------+ |
|  | [Search icon] [Input: "Search by city or group name..."]    | |
|  |  Height: 56px                                               | |
|  |  Background: rgba(255,255,255,0.08)                         | |
|  |  Border: 1px solid rgba(255,255,255,0.15)                   | |
|  |  Radius: radius-lg                                          | |
|  |  Text: neutral-0                                            | |
|  |  Placeholder: surface-dark-muted                            | |
|  |  Focus: border primary-400,                                 | |
|  |    ring 0 0 0 3px rgba(85, 112, 241, 0.3)                  | |
|  +------------------------------------------------------------+ |
|                                                                  |
|  space-6                                                         |
|  QUICK FILTERS (centered, flex, gap space-2)                     |
|  Pill buttons: caption, 500wt, radius-full                       |
|  Default: rgba(255,255,255,0.08) bg, surface-dark-muted text     |
|  Hover: rgba(255,255,255,0.15) bg, neutral-0 text                |
|  Active: primary-500 bg, neutral-0 text                          |
|  Examples: "All" "North America" "Europe" "Asia" "Online"        |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  GROUPS GRID                                                     |
|  Background: neutral-100                                         |
|  Padding: space-16 top, space-20 bottom                         |
|  Max-width: page-max, centered                                   |
|                                                                  |
|  RESULTS HEADER (flex, space-between):                           |
|  ["142 groups" body, neutral-600]                                |
|  [Sort dropdown: "Most active" / "Newest" / "A-Z"]              |
|  space-8                                                         |
|                                                                  |
|  Grid: 3 columns desktop, 2 tablet, 1 mobile                    |
|  Gap: space-6                                                    |
|  [Group Card] [Group Card] [Group Card]                          |
|  [Group Card] [Group Card] [Group Card]                          |
|  [Group Card] [Group Card] [Group Card]                          |
|                                                                  |
|  space-10                                                        |
|  [Pagination]                                                    |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  START A GROUP CTA                                               |
|  Background: neutral-0                                           |
|  Padding: space-20 vertical                                      |
|  Text-align: center                                              |
|  Max-width: content-sm (640px), centered                         |
|                                                                  |
|  [h2: "Don't see your city?"]                                    |
|  space-3                                                         |
|  [body: "Start a WordPress community group in your area          |
|   and connect with local enthusiasts." neutral-600]              |
|  space-6                                                         |
|  [Button: "Become an Organizer" primary style]                   |
|                                                                  |
+------------------------------------------------------------------+
| FOOTER                                                           |
+================================================================+
```

---

## 6. Responsive Breakpoints

| Name      | Min Width | Columns (cards) | Layout Notes                              |
|-----------|-----------|-----------------|-------------------------------------------|
| `mobile`  | 0         | 1               | Stack all columns, full-width cards       |
| `tablet`  | `640px`   | 2               | Two-column grids, sidebar stacks below    |
| `desktop` | `1024px`  | 3               | Side-by-side layout, sticky sidebar       |
| `wide`    | `1280px`  | 3-4             | Max page width reached, 4-col member grid |
| `ultra`   | `1440px`  | 4               | 4-column event grids on directory         |

---

## 7. Motion and Transitions

| Property                | Duration | Easing                | Usage                          |
|-------------------------|----------|-----------------------|--------------------------------|
| Color changes           | `0.15s`  | `ease`                | Buttons, links, borders        |
| Shadow + transform      | `0.2s`   | `ease-out`            | Card hover lift                |
| Layout shifts           | `0.25s`  | `ease-in-out`         | Expanding/collapsing sections  |
| Page transitions        | `0.3s`   | `ease-in-out`         | If View Transitions API used   |
| Loading spinner         | `0.8s`   | `linear` (infinite)   | RSVP button loading state      |
| Pulsing dot (live)      | `1.5s`   | `ease-in-out` (infinite) | "Happening now" badge       |

**Reduced motion:** Wrap all transitions and animations in `@media (prefers-reduced-motion: no-preference) { ... }`. Under reduced motion, use `transition-duration: 0.01ms`.

---

## 8. Accessibility Requirements

- All interactive elements must have `:focus-visible` outlines: `2px solid primary-500`, `outline-offset: 2px`
- Color contrast ratios must meet WCAG 2.1 AA: minimum 4.5:1 for normal text, 3:1 for large text
- Verified ratios for key combinations:
  - `neutral-900` (#1C1922) on `neutral-0` (#FFFFFF): **17.5:1** -- passes AAA
  - `neutral-600` (#635E6C) on `neutral-0` (#FFFFFF): **5.3:1** -- passes AA
  - `neutral-500` (#817C8A) on `neutral-0` (#FFFFFF): **3.8:1** -- passes AA for large text only; use only for non-essential labels/metadata
  - `primary-700` (#2D41A6) on `neutral-0` (#FFFFFF): **7.2:1** -- passes AAA
  - `surface-dark-text` (#E4E2E7) on `surface-dark-1` (#141118): **13.8:1** -- passes AAA
  - `surface-dark-muted` (#817C8A) on `surface-dark-1` (#141118): **4.6:1** -- passes AA
- All icon-only buttons must have `aria-label`
- Card links must have descriptive text (event title is the link, not "Read more")
- Skip links for keyboard navigation

---

## 9. Theme.json Mapping Reference

This section maps the design tokens above to their `theme.json` equivalents.

### Palette mapping (abbreviated)

```json
{ "slug": "primary-700", "color": "#2D41A6", "name": "Primary" }
{ "slug": "primary-500", "color": "#5570F1", "name": "Primary Light" }
{ "slug": "primary-100", "color": "#E8ECFE", "name": "Primary Tint" }
{ "slug": "accent-500", "color": "#EF6351", "name": "Accent" }
{ "slug": "accent-100", "color": "#FEF0EE", "name": "Accent Tint" }
{ "slug": "neutral-950", "color": "#141118", "name": "Ink" }
{ "slug": "neutral-900", "color": "#1C1922", "name": "Ink Light" }
{ "slug": "neutral-600", "color": "#635E6C", "name": "Muted" }
{ "slug": "neutral-200", "color": "#E4E2E7", "name": "Border" }
{ "slug": "neutral-100", "color": "#F5F4F7", "name": "Surface" }
{ "slug": "neutral-0", "color": "#FFFFFF", "name": "White" }
{ "slug": "success-500", "color": "#22C55E", "name": "Success" }
{ "slug": "success-100", "color": "#F0FDF4", "name": "Success Tint" }
{ "slug": "warning-500", "color": "#EAB308", "name": "Warning" }
{ "slug": "warning-100", "color": "#FEFCE8", "name": "Warning Tint" }
{ "slug": "error-500", "color": "#EF4444", "name": "Error" }
{ "slug": "error-100", "color": "#FEF2F2", "name": "Error Tint" }
```

### Font family mapping

```json
{
  "fontFamily": "'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
  "name": "Plus Jakarta Sans",
  "slug": "heading",
  "fontFace": [
    { "fontFamily": "Plus Jakarta Sans", "fontWeight": "500", "fontStyle": "normal", "src": ["https://fonts.gstatic.com/..."] },
    { "fontFamily": "Plus Jakarta Sans", "fontWeight": "600", "fontStyle": "normal", "src": ["https://fonts.gstatic.com/..."] },
    { "fontFamily": "Plus Jakarta Sans", "fontWeight": "700", "fontStyle": "normal", "src": ["https://fonts.gstatic.com/..."] },
    { "fontFamily": "Plus Jakarta Sans", "fontWeight": "800", "fontStyle": "normal", "src": ["https://fonts.gstatic.com/..."] }
  ]
}
```

### Spacing mapping

```json
{ "slug": "10", "size": "4px",  "name": "4px" }
{ "slug": "20", "size": "8px",  "name": "8px" }
{ "slug": "30", "size": "12px", "name": "12px" }
{ "slug": "40", "size": "16px", "name": "16px" }
{ "slug": "50", "size": "24px", "name": "24px" }
{ "slug": "60", "size": "32px", "name": "32px" }
{ "slug": "70", "size": "48px", "name": "48px" }
{ "slug": "80", "size": "64px", "name": "64px" }
{ "slug": "90", "size": "96px", "name": "96px" }
{ "slug": "edge-space", "size": "clamp(16px, 4vw, 80px)", "name": "Edge Space" }
```

### Layout

```json
{
  "contentSize": "960px",
  "wideSize": "1280px"
}
```

---

## 10. Icon System

Use [Lucide Icons](https://lucide.dev/) (MIT licensed, tree-shakeable, 24x24 default grid).

**Standard sizes:**
- `12px` -- badge inline icons
- `16px` -- inline with small/body text, form field icons
- `18px` -- button icons
- `20px` -- nav icons, card metadata
- `24px` -- standalone icons, section headers

**Stroke width:** `1.75px` (slightly lighter than Lucide default of 2px for a refined feel)

**Key icons used:**
- `calendar` -- event date
- `map-pin` -- location
- `users` -- member count
- `clock` -- time, waitlist
- `check` -- confirmed RSVP
- `x` -- cancel, close
- `search` -- search input
- `chevron-down` -- dropdowns
- `chevron-left` / `chevron-right` -- pagination
- `globe` -- online events, location in group cards
- `share-2` -- share button
- `plus` -- add/join actions
- `monitor` -- hybrid events
- `video` -- online events
- `lock` -- full/closed events
- `external-link` -- outbound links
