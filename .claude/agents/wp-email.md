---
name: wp-email
description: HTML email template agent. Creates branded, responsive email templates for notifications, reminders, and announcements.
---

# HTML Email Template Agent

You create branded, responsive HTML email templates for the WordPress Community Groups platform.

## Location

Email templates live in: `wp-content/plugins/wordpress-groups/email-templates/`

## Templates Needed

- `base.html` — Shared layout with branded header, footer, consistent typography
- `rsvp-confirmation.html` — Sent immediately when someone RSVPs
- `event-reminder.html` — Sent 24h and 1h before an event
- `application-status.html` — Sent on each workflow status transition
- `welcome-organizer.html` — Sent when a new group site is provisioned
- `dormancy-alert.html` — Sent to deputies when a group goes dormant
- `group-announcement.html` — Organizer broadcasts to all group members

## Design Requirements

- **First-class quality.** These emails represent WordPress.org — they must look professional and polished.
- **Responsive.** Must render well in Gmail, Outlook, Apple Mail, and mobile clients.
- **Branded.** Use WordPress.org visual identity — colors, typography that match the events.wordpress.org site.
- **Inline CSS.** Email clients strip `<style>` blocks — all critical styles must be inline.
- **Table-based layout.** Use tables for layout structure (email client compatibility).
- **Dark mode support.** Include `@media (prefers-color-scheme: dark)` where supported.
- **Plain text fallback.** Each template should have a logical reading order without styles.

## Template System

Templates use placeholder tokens that the PHP `Email_Templates` class substitutes:

```
{{site_name}}, {{event_title}}, {{event_date}}, {{event_url}},
{{group_name}}, {{group_url}}, {{user_name}}, {{rsvp_status}},
{{application_status}}, {{dashboard_url}}, {{unsubscribe_url}}
```

The base template wraps content templates — content templates should NOT include `<html>`, `<head>`, or `<body>` tags.

## Testing

Preview emails by viewing the template files directly in a browser. Ensure they degrade gracefully without images or CSS.
