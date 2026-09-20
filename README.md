# Online Pickleball Player Matching and Function Hall and Court Reservation Management Application

**Client:** Mango Drive Café
**Stack:** PHP 8.1+ · MySQL / MariaDB · vanilla JS · hand-written CSS · no build step, no Composer, no CDN

A web application that replaces Mango Drive Café's handwritten reservation book: customers
see real court and function-hall availability, submit reservation requests, and register for
automatic player matching; staff and administrators approve bookings, register walk-ins,
record payments and generate reports.

---

## Quick start (XAMPP)

```bash
# 1. Put the project where Apache can serve it
#    e.g. C:\xampp\htdocs\pickleball

# 2. Start Apache and MySQL from the XAMPP Control Panel

# 3. Configure
copy config\config.example.php config\config.php
#    Edit config/config.php only if your MySQL user/password is not root / blank

# 4. Create the schema and load demo data
C:\xampp\php\php.exe database\install.php
C:\xampp\php\php.exe database\seed.php

# 5. Open it
#    http://localhost/pickleball/public/
```

`base_url` defaults to `'auto'`, so the app works at `htdocs/pickleball/public`,
at a web root, or behind `php -S` without editing anything.

**No XAMPP?** See [docs/SETUP.md](docs/SETUP.md) for the portable PHP + MariaDB route
(no installer, no administrator rights).

---

## Demo logins

| Role | Username | Password | Can do |
|------|----------|----------|--------|
| Administrator | `admin` | `Admin@1234` | Everything, including users, facilities, settings, system logs, payment verification |
| Staff | `frontdesk` | `Staff@1234` | Walk-ins, reservations, matching, events, recording payments |
| Customer | `marco.v` | `Player@1234` | Book facilities, join matching, view their own records |

Seven more customer accounts exist (`liza.r`, `job.m`, `carmela.d`, `rafael.s`, `bea.o`,
`nico.a`, `trina.b`) — all with the password `Player@1234`.

**Change these before deploying anywhere public.**

---

## What the demo data contains

| | |
|---|---|
| Courts | 4 (Center, Garden, Covered, Practice) |
| Function halls | 2 (Mango Function Hall, Garden Pavilion) |
| Schedule slots | ~3,570 — 7 days back, 30 days forward, hourly |
| Players | 14 — 8 with online accounts, 6 walk-in records with no login |
| Reservations | 21 across pending / approved / completed / cancelled / rejected / no-show |
| Payments | ~10–20 recorded, a mix of verified and awaiting verification |
| Matching sessions | 4 — one already drawn (with a score recorded), three open |
| Events | 4 with ~25 participant registrations |

---

## Documentation

| Document | What it covers |
|----------|----------------|
| [docs/TRACEABILITY.md](docs/TRACEABILITY.md) | Every use case in Figures 3.2 and 3.3 mapped to a route, a controller method and its tables. **Bring this to the defense.** |
| [docs/DOCUMENT-GAPS.md](docs/DOCUMENT-GAPS.md) | Eight inconsistencies found *inside the submitted paper*, each with a recommended fix |
| [docs/SETUP.md](docs/SETUP.md) | Full installation, troubleshooting and deployment notes |
| [docs/ENHANCEMENTS.md](docs/ENHANCEMENTS.md) | What was built beyond the approved scope, and how to switch it off |
| [database/schema.sql](database/schema.sql) | 23 tables + 3 reporting views, commented with the class or use case each one serves |

---

## Scope decisions

Three things in this build are deliberate consequences of the approved scope. A panelist
may ask about each.

1. **No online payment.** The scope states the study "shall not comprise a real-time online
   payment." The `payments` table therefore has no gateway or transaction-ID columns at all —
   the limitation is architectural, not just stated. Staff record money taken at the counter;
   an administrator verifies it.

2. **Matching is random, not ranked.** The scope limits matching to "players that register
   for the selected date and the stated skill level… not incorporating statistical values or
   sophisticated ranking algorithm." Pairing is a Fisher–Yates shuffle inside a
   (date, time block, skill level) pool. Fairness comes from the pool, not from maths.

3. **Reservations are requests.** Figure 3.2 includes *Approve / Reject Reservation* and
   Figure 3.3 includes *Submit Reservation Request*, so a customer booking starts as
   `pending`. The time slot is held from the moment of submission, so two customers can never
   hold a pending request on the same slot.

---

## How double booking is prevented

This was the café's main problem, so it is worth stating precisely.

A bookable hour is a physical row in `schedules`. When a reservation claims it, a row goes
into `reservation_schedules`, which carries:

```sql
UNIQUE KEY `uq_slot_taken` (`schedule_id`)
```

The claim happens inside a transaction that re-reads the slots `FOR UPDATE` before inserting.
So even if two customers press "Submit" in the same second, the database itself rejects the
second claim — the guarantee does not depend on application code getting the timing right.
Rejecting or cancelling a reservation deletes the claim and returns the slot to the pool.

---

## Folder structure

```
PICKLEBALL_CAPS/
├─ app/
│  ├─ Controllers/          9 customer-facing + 14 admin controllers
│  │  └─ Admin/
│  ├─ Core/                 Router, Database (PDO), Auth, Validator, View, Session, …
│  ├─ Helpers/              functions.php (e, url, money, fdate…), icons.php (inline SVG)
│  ├─ Models/               Reservation, Schedule, Matching, Payment, Player, Event, …
│  └─ Views/
│     ├─ layouts/           app (customer) · admin (back office) · auth
│     ├─ auth/ customer/ admin/ errors/ home/
├─ config/
│  ├─ config.example.php    committed template
│  └─ config.php            your local credentials (git-ignored)
├─ database/
│  ├─ schema.sql            23 tables + 3 views
│  ├─ install.php           creates the database and schema
│  ├─ seed.php              demo data (passwords hashed at run time)
│  └─ seed_activity.php     reservations, payments, matching, events
├─ docs/                    traceability, document gaps, setup, enhancements
├─ public/                  ← Apache document root
│  ├─ index.php             front controller
│  ├─ .htaccess             pretty URLs + security headers
│  ├─ assets/css|js|img
│  └─ uploads/              avatars and event images
├─ routes/web.php           116 routes, annotated with the use case each serves
├─ tests/                   run.php + http_checks.php (50 checks, no dependencies)
├─ storage/logs/            application error log
└─ bootstrap.php            autoloader, config, error handling
```

---

## Verification

A test suite ships with the project. It needs no PHPUnit and no Composer — plain PHP.

```bat
php tests/run.php                                  :: static checks only
php tests/run.php http://localhost/pickleball/public   :: everything
```

Current result against PHP 8.3.33 and MariaDB 10.11.9: **50 passed, 0 failed.**

It covers all six modules named in Chapter III's Testing section — Registration, Matching,
Reservation, Function Hall, Payment and Reporting — plus the double-booking guarantee,
role enforcement and CSRF. See [tests/README.md](tests/README.md) for the full list and for
the two real bugs it caught during development.

---

## Security notes

- Passwords hashed with `password_hash()` (bcrypt), rehashed automatically when PHP's
  default cost increases
- Account lockout after 5 failed logins (15 minutes)
- Every `POST` requires a CSRF token; the router rejects requests without one
- All queries use PDO prepared statements with emulation **off**
- Every dynamic value in a view passes through `e()` (`htmlspecialchars`)
- Uploads validated by actual image type (`getimagesize`), not by file extension, and
  `php_flag engine off` is set on the uploads directory
- Separation of duties: staff record payments, only administrators verify them
- `config/config.php` is git-ignored so local credentials are never committed
