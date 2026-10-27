# Food Festival Fiesta

A PHP + MySQL platform for running a food festival: vendor registration with approval,
stall and menu management, visitor passes with QR codes, gate check-in, verified reviews,
and an organizer dashboard with reports.

**Start here:** `docs/SETUP_GUIDE.md` (run it on XAMPP) and `docs/DEPLOYMENT_GUIDE.md` (put it online).

## Roles
| Role | What they can do |
|---|---|
| Visitor | Browse approved stalls, register for a pass (QR code), review stalls with the pass ID |
| Vendor | Register a stall + account, sign in, manage only their own menu |
| Organizer | Approve / reject stalls, check visitors in at the gate, view analytics, download CSV report, see the audit log |

## Demo accounts (seed data) — change before going live
| Account | Username | Password |
|---|---|---|
| Organizer | `organizer` | `organizer2026` |
| Vendor (stall #7) | `ranjit` | `vendor2026` |
| Visitor passes | `FEST-DEMO0001`, `FEST-DEMO0002` | — |

## Folder map
```
config/      db.php (connection) · security.php (sessions, CSRF, validation, rate limits) · bootstrap.php
database/    schema.sql (fresh install) · upgrade_v2.sql (keep old data) · schema_hosting.sql (shared hosting)
includes/    shared header, navbar, footer
actions/     every form handler (POST only, CSRF checked)
assets/      css/style.css · js/app.js
tools/       set_admin_password.php (command line)
tests/       integration_test.php (74 automated functional + security tests)
docs/        setup guide, deployment guide
*.php        pages: index, stalls, visitor, vendor, feedback, dashboard, checkin
```

## Run the automated tests
On a freshly imported database, with Apache and MySQL running:
```
php tests/integration_test.php http://localhost/food-festival-fiesta
```
Expected: `TOTAL 74   PASSED 74   FAILED 0`.
