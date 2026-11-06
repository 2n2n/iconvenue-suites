---
paths:
  - "tests/**"
---

# Writing tests

Conventions live in `docs/testing/bdd.md` — read it before writing or renaming a test. The core rule:

Every test case is a **verb + objective from the user's behaviour**: `<Actor> <verb>s <object> to <objective>`.

- Scenario: `Guest checks venue availability to plan an event date`
- Method: `test_guest_checks_venue_availability_to_plan_an_event_date`

Actors are **Guest**, **Staff**, **Admin**. This applies to PHPUnit feature/unit tests as well as Dusk (`test_admin_uploads_venue_images_to_showcase_the_space`, not `test_upload`).
