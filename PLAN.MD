# WordPress Community Groups Platform

## Context

WordPress.org currently relies on Meetup.com to host ~700+ local WordPress community groups worldwide. This creates dependency on a third-party platform, limits data ownership, and prevents deep integration with the WordPress.org ecosystem. We are building an in-house replacement within the existing WordCamp multisite network on events.wordpress.org.

The platform must match the full feature set of major event platforms (excluding native mobile apps), provide network-level analytics for the WordPress Community Team, and integrate with the existing WordPress.org infrastructure (profiles, badges, the `official-wordpress-events` aggregation plugin, and the WordCamp application workflow).

**Naming convention:** Use `Groups` as the namespace. No `vg_` prefix. No `VibedGroups`.

---

## GatherPress Decision: Build From Scratch

GatherPress is a community-built event management plugin with good ideas we can learn from. However, for the WordPress.org Meta team's needs, building from scratch is the better path.

**Why building fresh is the right approach:**

1. **Different architecture.** Our platform is multisite-per-group (matching the WordCamp model), where each group is its own site with native WordPress roles, comments for RSVPs, and standard post types. GatherPress is a single-site plugin with its own custom tables and data model. Adapting it to our multisite architecture would mean rewriting its core while carrying design decisions made for a different deployment model.

2. **We need tight integration with existing WordPress.org infrastructure.** The `wp_meetup` tracker on central.wordcamp.org, the official-wordpress-events aggregation plugin, WordPress.org profile badges, Slack notifications — these are all specific to our network. Building from scratch lets us design around these integrations from day one rather than bolting them onto an existing plugin.

3. **Scope mismatch.** The features we need that don't exist in any event plugin — application workflow, group lifecycle management, network-wide analytics, dormancy detection, deputy dashboards, site provisioning — represent the majority of our scope. The event display and RSVP layer (what GatherPress provides) is a relatively small slice of the overall platform.

4. **Ownership and maintenance.** As the Meta team, we need to own and maintain this infrastructure long-term. Building it ourselves means we understand every line, can iterate quickly on WordPress.org-specific requirements, and aren't dependent on external release cycles or architectural decisions made for different use cases.

5. **Simplicity.** With multisite-per-group, much of what GatherPress builds custom solutions for (memberships, RSVPs, roles) is handled natively by WordPress itself. We need less code, not more.

**What to learn from GatherPress:** Leaflet/OpenStreetMap integration pattern, timezone handling approach, block registration patterns. Good reference material for the event display layer.

---

## Architecture Overview

### Core Concept: Multisite-Per-Group

Following the same model as WordCamp.org — each WordPress community group gets its own site in the multisite network, just as each WordCamp gets its own site.

- **Central tracker** on central.wordcamp.org uses the existing `wp_meetup` post type for group application lifecycle (pending → vetting → orientation → active → dormant → removed)
- **Each approved group** gets a new site in the multisite as a subdirectory (e.g., `events.wordpress.org/melbourne-user-group/`)
- **Events** are posts (or a simple CPT) within the group's site
- **RSVPs** use comments on event posts
- **Membership** uses native WordPress site roles — users are added to the group's site
- **Network-level plugin** handles analytics, dormancy detection, application workflow, directory aggregation

### Deployment Context
- **Network:** events.wordpress.org — already exists in the WordCamp multisite network
- **Plugin:** `wordpress-groups` — network-activated, provides all functionality
- **Themes:** Two block themes (FSE), following the design language of the existing `wporg-events-2023` theme from the WordCamp repo:
  - `groups-directory` — for the central/directory site (group discovery, search, maps, deputy dashboard)
  - `groups-site` — for individual group sites (events, RSVPs, venue display, member lists)
- **Stack:** PHP 8.3+, WordPress 6.7+, Node 20+, @wordpress/scripts
- **Autoloader:** WordPress.org autoloader pattern (`class-{name}.php` convention), not PSR-4/Composer. Uses `WordPressdotorg\Autoload\register_class_path()` from wporg-mu-plugins.
- **Data migration:** Out of scope — separate project after platform is functional

### Development Strategy
- **Developed standalone** in this repository for fast iteration
- **Designed for migration** into `WordPress/wordcamp.org` once functional — code patterns, file structure, and conventions should match what's already in that repo
- **Theme design** should match `wporg-events-2023` (`WordPress/wordcamp.org/public_html/wp-content/themes/wporg-events-2023/`) — reuse its `theme.json` design tokens (colors, typography, spacing), template part patterns (header/footer), and PostCSS build approach. Both new themes should look like they belong on events.wordpress.org alongside the existing content.

### Decisions Made
- **Group tracker:** Reuse existing `wp_meetup` CPT on central.wordcamp.org
- **GatherPress:** Not using (see rationale above)
- **Multisite:** Each group = a site, subdirectory model (same as WordCamp)
- **Autoloader:** WordPress.org autoloader, not PSR-4
- **Theme design:** Match existing `wporg-events-2023` from the WordCamp repo
- **Emails:** All HTML, branded, responsive — first-class design
- **Repo:** Standalone now, migrate into WordCamp repo later
- **Data migration:** Out of scope

---

## Data Architecture

### Central Tracker (central.wordcamp.org)

Uses the existing `wp_meetup` post type with its established status lifecycle:

```
meetup-pending      → Application received
meetup-vetting      → Under review by deputies
meetup-feedback     → Awaiting applicant feedback
meetup-orientation  → Organizer completing orientation
meetup-scheduling   → Setting up first event
meetup-active       → Running normally
meetup-dormant      → No events in 90+ days
meetup-suspended    → Temporarily suspended (policy issue)
meetup-removed      → Permanently removed from program
meetup-declined     → Application declined
```

**Meetup post meta** (on central tracker): organizer wporg username, organizer email, city, state, country, country code, latitude, longitude, timezone, online link, application date, vetting date, orientation date, activation date, deputy assigned, mentor assigned, last event date, member count, code of conduct accepted, site ID (link to the group's multisite site).

### Per-Group Site

Each group site contains:

**Event CPT** (`event`) with statuses:
```
event-draft         → Being created
event-scheduled     → Published, upcoming
event-active        → Currently happening
event-past          → Completed
event-cancelled     → Cancelled by organizer
```

**Venue CPT** (`venue`) — reusable physical locations within the group's site.

**Event post meta:** venue ID, online link, attendee limit, waitlist enabled, RSVP questions (JSON), RSVP open/close dates, start datetime (UTC), end datetime (UTC), timezone, is recurring, recurrence rule.

**Venue post meta:** address, city, state, country, zip, latitude, longitude, capacity, accessibility notes, website.

**RSVPs:** Stored as comments on event posts. Comment meta tracks: status (`attending`, `waitlisted`, `not_attending`, `no_show`), guest count, custom question answers (JSON), attendance confirmed, is first event (newcomer flag).

**Membership:** Native WordPress site roles. Users are added to the group's site:
- `organizer` — full event/member management
- `co-organizer` — event management
- `member` — RSVP access (mapped to `subscriber`)

**Taxonomies:** Standard categories and tags on events. No custom taxonomies per-site — categories cover format (Workshop, Presentation, Social, Hack Day) and type (In-person, Online, Hybrid) via terms.

### Network-Wide Custom Tables (2 tables)

Only two custom tables, both network-wide (`$wpdb->base_prefix`), both primarily write-append:

1. **`{prefix}groups_analytics_daily`** — Pre-aggregated daily metrics per group site. Metrics: members, members joined, members left, events held, events created, RSVPs total, attendees total, newcomers, no shows. Unique on `(date, blog_id, metric)`.

2. **`{prefix}groups_activity_log`** — Audit trail of significant actions across the network (joined, left, RSVP, event created, status changed, etc.) with JSON meta. Indexed on `(blog_id, created_at)` and `(action, created_at)`.

---

## Plugin Architecture

```
wp-content/plugins/wordpress-groups/
├── wordpress-groups.php                # Bootstrap: constants, autoloader, plugin init
├── includes/
│   ├── class-plugin.php                # Central orchestrator, hooks registration
│   ├── post-types/
│   │   ├── class-event.php             # event CPT + custom statuses (per-site)
│   │   └── class-venue.php             # venue CPT (per-site)
│   ├── database/
│   │   ├── class-schema.php            # dbDelta table creation + version upgrades
│   │   ├── class-analytics-table.php   # Query builder for groups_analytics_daily
│   │   └── class-activity-log-table.php # Query builder for groups_activity_log
│   ├── models/
│   │   ├── class-event.php             # Event business logic (datetime, status)
│   │   ├── class-rsvp.php              # RSVP via comments, waitlist promotion, attendance
│   │   └── class-membership.php        # Site role management, join/leave
│   ├── rest/
│   │   ├── class-event-controller.php
│   │   ├── class-rsvp-controller.php
│   │   ├── class-venue-controller.php
│   │   ├── class-membership-controller.php
│   │   ├── class-directory-controller.php    # Network-wide group/event listing
│   │   ├── class-analytics-controller.php
│   │   └── class-application-controller.php
│   ├── admin/
│   │   ├── class-network-dashboard.php       # Network admin dashboard
│   │   ├── class-event-meta-box.php
│   │   ├── class-application-tracker.php     # List table on central site
│   │   └── class-reports.php
│   ├── blocks/
│   │   └── class-block-registrar.php         # Auto-registers all blocks/*
│   ├── analytics/
│   │   ├── class-aggregator.php              # Daily cron → groups_analytics_daily
│   │   ├── class-dormancy-detector.php       # Flags groups with no recent events
│   │   ├── class-newcomer-tracker.php        # First-time attendee detection
│   │   └── class-report-generator.php        # CSV/JSON export
│   ├── notifications/
│   │   ├── class-email-notifier.php          # HTML email sending
│   │   ├── class-email-templates.php         # Branded HTML email templates
│   │   └── class-scheduler.php               # wp_cron for reminders
│   ├── workflow/
│   │   ├── class-application-workflow.php    # State machine for wp_meetup statuses
│   │   ├── class-site-provisioner.php        # Creates group site on approval
│   │   ├── class-organizer-onboarding.php
│   │   └── class-status-transition.php       # Hooks on transition_post_status
│   ├── calendar/
│   │   └── class-ical-export.php
│   └── integrations/
│       ├── class-wordpress-org-profile.php   # Organizer badge integration
│       ├── class-official-events-api.php     # Feed for official-wordpress-events plugin
│       └── class-slack-notifier.php
├── blocks/                                   # JS/CSS source per block
│   ├── group-card/                           # block.json, edit.js, render.php, style.scss
│   ├── event-card/
│   ├── event-datetime/
│   ├── rsvp-button/
│   ├── rsvp-list/
│   ├── group-members/
│   ├── group-directory/
│   ├── event-directory/
│   ├── venue-map/
│   ├── group-stats/
│   ├── upcoming-events/
│   ├── event-comments/
│   ├── organizer-dashboard/
│   ├── application-form/
│   └── deputy-dashboard/
├── email-templates/
│   ├── base.html                             # Shared HTML layout, branded header/footer
│   ├── rsvp-confirmation.html
│   ├── event-reminder.html
│   ├── application-status.html
│   ├── welcome-organizer.html
│   ├── dormancy-alert.html
│   └── group-announcement.html
├── assets/
│   ├── css/
│   └── js/
└── tests/
    ├── phpunit/
    └── jest/
```

**Namespace:** `Groups\` → `includes/` via WordPress.org autoloader

Example: `Groups\Models\Event` → `includes/models/class-event.php`

**Registration:**
```php
\WordPressdotorg\Autoload\register_class_path( 'Groups', __DIR__ . '/includes' );
```

---

## Theme Architecture

### Theme 1: `groups-directory` (central/directory site)

```
wp-content/themes/groups-directory/
├── style.css
├── functions.php
├── theme.json                          # Design tokens
├── templates/
│   ├── index.html
│   ├── front-page.html                 # Discovery: search, map, featured groups
│   ├── archive-wp_meetup.html          # Group directory (uses central tracker CPT)
│   ├── single-wp_meetup.html           # Group profile/overview
│   ├── page-apply.html                 # Application form
│   ├── page-deputy-dashboard.html      # Deputy/admin dashboard
│   ├── 404.html
│   └── search.html
├── parts/
│   ├── header.html
│   └── footer.html
└── patterns/
    ├── group-hero.php
    ├── group-grid.php
    └── stats-overview.php
```

### Theme 2: `groups-site` (individual group sites)

```
wp-content/themes/groups-site/
├── style.css
├── functions.php
├── theme.json                          # Design tokens (shared with directory theme)
├── templates/
│   ├── index.html
│   ├── front-page.html                 # Group homepage: upcoming events, about
│   ├── single-event.html               # Event detail page
│   ├── single-venue.html               # Venue page
│   ├── archive-event.html              # Event listing (past + upcoming)
│   ├── page-members.html               # Member directory
│   ├── page-dashboard.html             # Organizer dashboard
│   ├── 404.html
│   └── search.html
├── parts/
│   ├── header.html
│   ├── footer.html
│   └── group-navigation.html           # Tabs: Events, Members, About
└── patterns/
    ├── event-details.php
    ├── event-list.php
    └── upcoming-events.php
```

**Strategy:** Both themes are block themes using plugin-provided blocks. Data-display blocks are server-rendered (`render.php`) for SEO. Interactive blocks (RSVP button, directories, dashboards) hydrate client-side React and call the REST API. Shared design tokens ensure visual consistency across both themes.

---

## REST API Design

**Base:** `groups/v1`

**Directory (network-wide, served from central site):**
- `GET /groups` — List groups (filter: region, category, status, search, lat/lon/radius)
- `GET /groups/{blog_id}` — Single group info
- `GET /groups/{blog_id}/events` — Group's upcoming events (cross-site query)
- `GET /groups/{blog_id}/stats` — Group analytics

**Events (per-site):**
- `GET /events` — List (filter: date range, type, category)
- `GET /events/{id}` — Single event
- `POST /events` — Create (organizer+)
- `PUT /events/{id}` — Update
- `DELETE /events/{id}` — Cancel
- `GET /events/{id}/rsvps` — RSVP list
- `POST /events/{id}/rsvp` — RSVP (creates comment)
- `PUT /events/{id}/rsvp` — Update RSVP
- `DELETE /events/{id}/rsvp` — Cancel RSVP
- `POST /events/{id}/attendance` — Mark attendance (organizer)

**Membership (per-site):**
- `GET /members` — List site members with roles
- `POST /members/join` — Join group (adds user to site)
- `DELETE /members/leave` — Leave group (removes user from site)

**Venues (per-site):** Standard CRUD

**Applications (central site):**
- `GET /applications` — List (deputy/admin)
- `POST /applications` — Submit
- `PUT /applications/{id}/status` — Transition status
- `POST /applications/{id}/notes` — Add internal notes

**Analytics (deputy/admin, central site):**
- `GET /analytics/overview` — Global summary
- `GET /analytics/groups` — Per-group metrics
- `GET /analytics/events` — Event trends over time
- `GET /analytics/geographic` — Distribution by country/region
- `GET /analytics/dormant` — Dormant/at-risk groups
- `GET /analytics/newcomers` — First-time attendee trends
- `GET /analytics/export` — CSV export

**Integration endpoint** (for `official-wordpress-events` plugin):
- `GET /integration/events-feed` — Replaces Meetup.com as data source

---

## Analytics System

**Three layers:**

1. **Event-driven logging** — Every significant action (join, leave, RSVP, event created, status changed) writes to `groups_activity_log` via hooks. Raw audit trail, write-append only.

2. **Daily aggregation cron** — Iterates over group sites, reads activity logs + site data, writes summary rows to `groups_analytics_daily`. This is the query layer for dashboards.

3. **Report generation** — Reads from `groups_analytics_daily` for efficient aggregation. Supports date range filtering, group filtering, CSV/JSON export.

**Network metrics:**
- Events per week/month/year (global, per-region, per-group)
- Geographic distribution of groups and events
- Attendance per event and trends over time
- Newcomer tracking (first-time attendees per event, per group, per period)
- Group growth (new groups, member counts over time)
- Dormancy detection (60 days = at-risk, 90 days = dormant)
- Member retention (returning vs. one-time attendees)
- Organizer activity (events created, RSVP rates)

**Dormancy detector** runs on daily cron:
- 60+ days without event → flag as "at-risk" (meta on `wp_meetup` post)
- 90+ days → auto-transition to `meetup-dormant`
- Email notification to assigned deputy

**Newcomer tracker:** On RSVP creation, checks if user has ever attended any event on any group site before. If not, sets `is_first_event` comment meta. Enables per-event newcomer counts and organizer notifications.

---

## Email System

All emails are **HTML, branded, and responsive**. First-class design quality.

- **Base template:** Shared HTML layout with branded header, footer, and consistent typography
- **Template system:** HTML templates in `email-templates/` with placeholder substitution
- **Sending:** Via `wp_mail()` with HTML content type
- **Types:** RSVP confirmation (immediate), event reminders (24h and 1h before), application status changes, welcome organizer, dormancy alerts, group announcements (organizer → all members)
- **User preferences:** Opt in/out per notification type, stored as user meta

---

## Organizer Workflow

Mirrors WordCamp.org's proven process, using the `wp_meetup` CPT on central.wordcamp.org:

1. **Application** — User submits via `application-form` block. Creates `wp_meetup` post in `meetup-pending`. Fields: proposed city, group name, organizer background, WordPress experience, WordPress.org profile link, why they want to organize.

2. **Vetting** — Deputy reviews in Application Tracker. Verifies WordPress.org profile, checks CoC compliance. May request feedback (`meetup-feedback`). On approval → `meetup-orientation`.

3. **Orientation** — Applicant completes orientation (checklist tracked via post meta). Deputy marks complete → `meetup-scheduling`.

4. **Site Provisioning** — `Site_Provisioner` creates a new multisite site for the group, assigns the `groups-site` theme, adds the organizer as site admin. Links the site back to the `wp_meetup` post via meta.

5. **Activation** — Organizer creates first event on their new site → auto-transition to `meetup-active`.

6. **Ongoing** — Deputy monitors via network dashboard. Mentor assignment optional. Dormancy detection automatic.

**Status transitions** logged to `groups_activity_log`, trigger HTML email notifications, update date meta fields, optionally send Slack webhooks.

---

## Integration with WordPress.org Infrastructure

1. **`official-wordpress-events` plugin** — REST API events feed endpoint replaces Meetup.com as data source.
2. **WordPress.org profiles** — Organizer/attendee badge assignment on group activation (same pattern as WordCamp.org).
3. **WordCamp network** — Plugin deploys into the existing multisite. Follows WordPress.org coding patterns.
4. **Slack** — Configurable webhook notifications for application submissions, status transitions, and dormancy alerts.

---

## Development Phases

### Phase 1: Foundation
Plugin scaffold with WordPress.org autoloader, event and venue CPTs with custom statuses, basic REST API for events, initial blocks (event-card, event-datetime), groups-site theme with base templates and theme.json.

### Phase 2: RSVPs & Membership
RSVP via comments with waitlist promotion logic, join/leave groups via site roles, attendance tracking, newcomer detection, rsvp-button and rsvp-list blocks.

### Phase 3: Venues & Discovery
Venue management with geo fields, Leaflet/OpenStreetMap venue-map block, group-directory and event-directory blocks, location-based search, iCal export, groups-directory theme with front-page layout.

### Phase 4: Organizer Workflow
Application form block, wp_meetup status machine on central site, site provisioner for new groups, application tracker admin UI, HTML email notifications on transitions, mentor assignment, orientation tracking.

### Phase 5: Recurring Events & Notifications
Recurrence rules stored as event post meta, cron generation of future event instances, email reminders (24h/1h), RSVP confirmations, group announcements, user notification preferences.

### Phase 6: Analytics & Dashboards
Activity logging to network table, daily aggregation cron, dormancy detection, analytics REST endpoints, group-stats/organizer-dashboard/deputy-dashboard blocks, CSV export, network reports.

### Phase 7: Integration & Polish
WordPress.org profile badges, official-wordpress-events feed endpoint, Slack webhooks, accessibility audit (WCAG 2.1 AA), performance optimization (object cache, transients), test suites, documentation.

---

## GitHub Issues Breakdown

### Epic: Foundation
1. Plugin scaffold: WordPress.org autoloader, constants, Plugin class
2. Register CPTs: `event` and `venue` with custom statuses (per-site)
3. Database schema: create 2 network-wide custom tables via dbDelta (analytics_daily, activity_log)
4. Event model: create with datetime in post meta, status transitions
5. REST API: Events CRUD endpoints with permission checks
6. Block: event-card (server-rendered)
7. Block: event-datetime (timezone-aware display)
8. Theme: groups-site base templates (single-event, archive-event, single-venue) + theme.json

### Epic: RSVPs & Membership
9. RSVP model: comment-based RSVPs with status meta, waitlist promotion on cancellation
10. Membership model: join/leave via WordPress site roles
11. REST API: RSVP endpoints (RSVP/update/cancel/attendance marking)
12. REST API: Membership endpoints (join/leave)
13. Block: rsvp-button (interactive React with REST calls)
14. Block: rsvp-list (attendee display with status indicators)
15. Block: group-members (member grid with roles and avatars)
16. Newcomer tracking: first-event detection on RSVP creation

### Epic: Venues & Discovery
17. Venue CPT with geo fields (address, lat/lon, capacity, accessibility notes)
18. Block: venue-map (Leaflet/OpenStreetMap integration)
19. Block: group-directory (network-wide filterable listing with optional map view)
20. Block: event-directory (calendar/list toggle, date and location filters)
21. Location-based search (Haversine queries on lat/lon from wp_meetup meta)
22. REST API: Venues CRUD endpoints
23. REST API: Directory endpoints (network-wide group/event listing)
24. iCal export: per-group and per-event feeds
25. Theme: groups-directory with front-page discovery layout

### Epic: Organizer Workflow
26. Application workflow state machine (wp_meetup status transitions with validation)
27. Site provisioner: create multisite site on group approval, assign theme, add organizer
28. Block: application-form (multi-step form with WordPress.org profile validation)
29. Admin: Application Tracker list table (status filters, bulk actions, notes)
30. HTML email notifications for each workflow status transition
31. Mentor assignment system (deputy assigns mentor, mentor gets dashboard access)
32. Orientation tracking (checklist via post meta)

### Epic: Recurring Events
33. Recurrence rules in event post meta (weekly, biweekly, monthly, custom)
34. Cron job: generate recurring event instances from template events
35. UI for setting recurrence in event editor sidebar

### Epic: Notifications
36. HTML email framework (branded template system, wp_mail with HTML content type)
37. Event reminder cron jobs (24h and 1h before event)
38. RSVP confirmation emails (immediate on RSVP)
39. Group announcement system (organizer → all members broadcast)
40. User notification preferences (opt in/out per type, stored as user meta)

### Epic: Analytics
41. Activity logging system (hooks on significant actions → groups_activity_log)
42. Daily aggregation cron (summarize across sites → groups_analytics_daily)
43. Dormancy detector (flag at-risk at 60d, transition to dormant at 90d)
44. REST API: Analytics endpoints (overview, geographic, trends, export)
45. Block: group-stats (summary cards for a single group)
46. Block: organizer-dashboard (charts, member management, event tools)
47. Block: deputy-dashboard (network overview, application queue, dormant groups)
48. Admin reports page (tabular reports with date range filtering)
49. CSV/JSON export for all report types

### Epic: Integration & Polish
50. WordPress.org profile integration (organizer/attendee badges)
51. official-wordpress-events feed endpoint (replace Meetup.com data source)
52. Slack webhook notifications (applications, transitions, dormancy)
53. Accessibility audit (WCAG 2.1 AA compliance)
54. Performance: caching layer (object cache for hot paths, transients for computed data)
55. Test suites: PHPUnit (unit + integration) and Jest (block components)
56. Developer documentation (architecture, hooks, filters, REST API reference)

---

## Verification Plan

1. **Local development:** `npm run env:start` spins up wp-env with plugin and both themes active
2. **Per-phase testing:** Each phase should be testable end-to-end before moving to the next
3. **Phase 1 verification:** Create an event via REST API on a group site, view it on the frontend
4. **Phase 2 verification:** Join a group site, RSVP to an event, verify waitlist promotion when someone cancels
5. **Phase 3 verification:** Browse the group directory on the central site, filter by location, see venue on map, export iCal
6. **Phase 4 verification:** Submit an application, walk through vetting → orientation → site provisioning → activation
7. **Phase 5 verification:** Create a recurring weekly event, verify instances auto-generate, receive HTML email reminders
8. **Phase 6 verification:** Check deputy dashboard shows accurate metrics, export CSV, verify dormancy detection triggers
9. **Phase 7 verification:** Confirm badge assignment, verify official-wordpress-events plugin can consume our feed
10. **Automated tests:** PHPUnit for models/REST/workflow, Jest for block components

---

## Critical Files

- `wp-content/plugins/wordpress-groups/wordpress-groups.php` — Bootstrap with WordPress.org autoloader
- `wp-content/plugins/wordpress-groups/includes/` — All PHP classes (`class-*.php` naming)
- `wp-content/plugins/wordpress-groups/blocks/` — Block source (JS/CSS)
- `wp-content/plugins/wordpress-groups/email-templates/` — Branded HTML email templates
- `wp-content/themes/groups-directory/theme.json` — Directory theme design tokens
- `wp-content/themes/groups-directory/templates/` — Central site FSE templates
- `wp-content/themes/groups-site/theme.json` — Group site design tokens
- `wp-content/themes/groups-site/templates/` — Per-group site FSE templates
- `package.json` — Build config for blocks
- `.wp-env.json` — Dev environment with multisite enabled
