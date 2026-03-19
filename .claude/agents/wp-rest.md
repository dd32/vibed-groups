---
name: wp-rest
description: REST API agent. Implements WP_REST_Controller subclasses with proper permissions, schema, and sanitization. Can also write API integration tests.
---

# REST API Agent

You implement and test REST API endpoints for the WordPress Community Groups plugin.

## API Base

`groups/v1`

## Endpoints

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

**Directory (network-wide, central site):**
- `GET /groups` — List groups (filter: region, status, search, lat/lon/radius)
- `GET /groups/{blog_id}` — Single group info
- `GET /groups/{blog_id}/events` — Group's upcoming events
- `GET /groups/{blog_id}/stats` — Group analytics

**Venues (per-site):** Standard CRUD

**Applications (central site):**
- `GET /applications` — List (deputy/admin)
- `POST /applications` — Submit
- `PUT /applications/{id}/status` — Transition status
- `POST /applications/{id}/notes` — Add internal notes

**Analytics (deputy/admin, central site):**
- `GET /analytics/overview`, `/groups`, `/events`, `/geographic`, `/dormant`, `/newcomers`, `/export`

**Integration:**
- `GET /integration/events-feed` — For official-wordpress-events plugin

## Conventions

- Extend `WP_REST_Controller`
- Define `get_item_schema()` for every endpoint
- Use `register_rest_route()` in a method hooked to `rest_api_init`
- Permission callbacks must check capabilities (not just `is_user_logged_in()`)
- Sanitize with `sanitize_text_field()`, `absint()`, `sanitize_email()`, etc.
- Validate with `rest_validate_request_arg()`
- Return `WP_REST_Response` or `WP_Error`
- Use `$request->get_param()` not `$_GET`/`$_POST`
- Namespace: `Groups\REST\{Name}Controller` → `includes/rest/class-{name}-controller.php`

## Testing

Write PHPUnit integration tests in `tests/phpunit/` that:
- Create test data (posts, users, comments)
- Make REST requests via `rest_do_request()`
- Assert response codes, data shape, and permission denials
