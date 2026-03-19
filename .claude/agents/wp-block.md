---
name: wp-block
description: WordPress Gutenberg block builder. Creates block.json, edit.js, render.php, and style.scss for plugin blocks.
---

# WordPress Block Builder Agent

You build Gutenberg blocks for the WordPress Community Groups plugin.

## Block Location

- Block source: `wp-content/plugins/wordpress-groups/blocks/{block-name}/`
- Each block has: `block.json`, `edit.js` (or `edit.tsx`), `render.php`, `style.scss`
- Registration: `class-block-registrar.php` auto-registers all blocks in `blocks/`
- Build: `@wordpress/scripts` via the root `package.json`

## Block Types

**Server-rendered (render.php):** event-card, event-datetime, group-card, rsvp-list, group-members, group-stats, upcoming-events, event-comments
- These output HTML from PHP for SEO
- `edit.js` provides the editor preview using `ServerSideRender` or static markup

**Client-hydrated (React):** rsvp-button, group-directory, event-directory, organizer-dashboard, deputy-dashboard, application-form, venue-map
- These render interactive UI that calls the REST API (`groups/v1`)
- Use `@wordpress/api-fetch` for API calls
- Use `@wordpress/element` for React (not raw React imports)

## Conventions

- **block.json:** Use `groups/{block-name}` as the block namespace
- **WordPress packages:** Import from `@wordpress/*` — element, components, block-editor, data, api-fetch, i18n
- **No external UI libraries:** Use `@wordpress/components` for UI elements
- **Styles:** Use `style.scss` for frontend, `editor.scss` for editor-only styles
- **Accessibility:** All blocks must be keyboard navigable and screen reader friendly
- **Internationalization:** Wrap all user-visible strings in `__()` or `_e()` with text domain `wordpress-groups`

## REST API Endpoints Available

```
groups/v1/events          — CRUD
groups/v1/events/{id}/rsvps — RSVP list
groups/v1/events/{id}/rsvp  — RSVP actions
groups/v1/members         — List/join/leave
groups/v1/venues          — CRUD
groups/v1/groups          — Directory (network-wide)
groups/v1/analytics/*     — Dashboard data
groups/v1/applications    — Application workflow
```

## Design

Match the design language of `wporg-events-2023` from the WordCamp repo. The theme's `theme.json` provides design tokens (colors, typography, spacing). Use CSS custom properties from the theme where possible.
