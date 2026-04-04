# Security Policy

**Please note: This is invalid, I/Claude do NOT plan to support security reports.


## Reporting a Vulnerability

If you discover a security vulnerability in this project, please report it responsibly.

**Do NOT open a public GitHub issue for security vulnerabilities.**

Instead, please report via one of these channels:

1. **GitHub Security Advisories:** Use the [Report a vulnerability](https://github.com/dd32/vibed-groups/security/advisories/new) feature

## What to include

- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

## Response timeline

- **Acknowledgment:** Within 48 hours
- **Assessment:** Within 1 week
- **Fix:** Depends on severity (critical: 24-48h, high: 1 week, medium/low: next release)

## Scope

This policy covers:
- The `wordpress-groups` plugin
- The `groups-site` and `groups-directory` themes
- REST API endpoints
- Email templates and notification system

## Security measures in place

- All database queries use `$wpdb->prepare()`
- All output is escaped (`esc_html`, `esc_attr`, `esc_url`)
- REST endpoints have capability checks and ownership verification
- Rate limiting on write API endpoints
- HMAC token verification for email unsubscribe links
- Nonce verification on admin forms
- Input sanitization on all user-provided data

## Supported versions

| Version | Supported |
|---------|-----------|
| 0.1.x   | Yes       |
