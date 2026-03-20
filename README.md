# WordPress Community Groups Platform

> **This is an experimental project built primarily by AI agents (Claude Code).** It is a proof-of-concept exploring agent-driven WordPress development. Nothing here should be considered set in stone.

## What is this?

A platform for WordPress community groups to manage events, members, and RSVPs — built as a WordPress multisite network. Each community group gets its own site with event management, while a central directory site provides discovery across all groups.

## Architecture

- **Multisite-per-group:** Each group gets its own sub-site (e.g., `/melbourne/`, `/tokyo/`)
- **Central directory:** Main site provides group discovery, search, and admin dashboards
- **Plugin:** `wordpress-groups` — network-activated, provides all functionality
- **Themes:** `groups-directory` (central site) + `groups-site` (per-group sites)
- **16+ Gutenberg blocks** for events, RSVPs, membership, venues, analytics
- **REST API** for all operations
- **400+ PHPUnit tests** + Jest tests + E2E smoke tests

See [PLAN.MD](PLAN.MD) for the full architecture plan.

## Local Development

```bash
npm install
npm run env:start    # Starts WordPress multisite, builds blocks, seeds data
```

This creates:
- **Main site** — Group directory at `http://localhost:PORT/`
- **Melbourne** — Sample group at `http://localhost:PORT/melbourne/`
- **Tokyo** — Sample group at `http://localhost:PORT/tokyo/`

Login: `admin` / `password`

### Other commands

```bash
npm run build:blocks    # Rebuild blocks
npm run env:setup       # Re-run setup script
npm run env -- run cli  # Run wp-cli commands
npm run test:js         # Run Jest tests
npm run env:destroy     # Destroy environment
```

## Features

- Event management (create, RSVP, waitlist, attendance tracking)
- Group membership (join/leave, roles: organizer/co-organizer/member)
- Venue management with Leaflet/OpenStreetMap maps
- Recurring events (weekly, biweekly, monthly)
- iCal export
- Branded HTML email notifications (RSVP confirmations, event reminders, announcements)
- Application workflow for new groups (pending → vetting → orientation → active)
- Analytics dashboards (daily aggregation, dormancy detection, reports)
- Newcomer tracking (first-time attendee detection)
- Slack webhook notifications
- SEO structured data (JSON-LD) and Open Graph meta tags
- Location-based group search (Haversine)
- Performance caching layer

## Status

This project is in active development. See the [GitHub Issues](https://github.com/dd32/vibed-groups/issues) for current progress.
