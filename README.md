# WordPress Community Groups Platform

> **This is an experimental project built primarily by AI agents (Claude Code).** It is a proof-of-concept and exploration of what's possible with agent-driven development. Nothing here should be considered set in stone — the architecture, code, and approach are all subject to change.

## What is this?

A prototype replacement for Meetup.com as the platform for WordPress community groups, designed to run within the existing WordCamp multisite network on events.wordpress.org.

## Architecture

- **Multisite-per-group:** Each WordPress community group gets its own site (like WordCamps)
- **Central tracker:** Uses the existing `wp_meetup` CPT on central.wordcamp.org
- **Plugin:** `wordpress-groups` — network-activated, provides events, RSVPs, membership, analytics
- **Themes:** `groups-directory` (central site) + `groups-site` (per-group sites)

See [PLAN.MD](PLAN.MD) for the full architecture plan.

## Development

```bash
npm install
npm run env:start    # Start WordPress multisite via wp-env
npm run build        # Build blocks
```

## Status

This project is in active development. See the [GitHub Issues](https://github.com/dd32/vibed-groups/issues) for current progress.
