# Test Suite

Written for: whoever needs to show the system works — you, before the defense, and a
panelist who asks "how did you test this?"

This maps directly onto Chapter III's **Testing** section, which names six modules:
Registration, Matching, Reservation, Function Hall, Payment and Reporting. Each is
exercised here.

---

## Running it

**Static checks only** — no server needed:

```bat
php tests\run.php
```

**Everything**, including page loads and the write flows — pass the URL the app is served at:

```bat
php tests\run.php http://localhost/pickleball/public
```

or, behind PHP's built-in server:

```bat
php tests\run.php http://127.0.0.1:8000
```

Exit code is `0` when everything passes, `1` otherwise, so it can be wired into CI later.

> **Run this against a development database only.** The functional checks create
> reservations, payments and a walk-in player. Test rows are deleted afterwards, but the
> reservations they create are deliberately left behind so you can inspect them. Re-seed
> with `php database/seed.php --force` for a clean slate.

---

## What it checks

### Static (no server required)

| # | Check | Why it matters |
|---|-------|----------------|
| 1 | Every `.php` file parses | Catches typos before they reach a page |
| 2 | Every route resolves to a real controller method | A route pointing at a missing method 500s only when someone clicks it |
| 3 | Every `view()` call has a matching file | Same reason — a missing view fails at render time, not at boot |
| 4 | No SQL statement reuses a named parameter | MySQL rejects a repeated `:name` when PDO emulation is off. This check exists because that exact bug broke the availability page during development |

### Database integrity

| Check | Why it matters |
|-------|----------------|
| 23+ tables present | Schema was installed |
| `reservation_schedules.schedule_id` is UNIQUE | **This is the double-booking guarantee.** If the index is gone, the whole premise of the study is gone |
| No slot is claimed by two reservations | Proves the guarantee holds in the data, not just in theory |
| Walk-in players have no `user_id` | Figure 3.5's requirement, enforced |
| No payment exceeds the amount due | Catches arithmetic slips in the payment module |

### Functional (server required)

| Module | What is proven |
|--------|----------------|
| **Authentication** | All three roles log in; a wrong password is refused |
| **Page loads** | 45 pages return HTTP 200 across guest, customer, staff and administrator |
| **Reservation** | A customer finds a free slot, sees the price breakdown, submits, and the booking lands at `pending` |
| **Double booking** | A second customer claiming the same slot is refused, and the database still holds exactly one claim |
| **Approval** | An administrator approves; status changes and the customer is notified |
| **Payment** | Staff record a part payment; staff receive **403** on verify; an administrator verifies successfully |
| **Registration** | Walk-in registration creates a player with `is_walk_in = 1`, `user_id NULL`, and records who registered them |
| **Matching** | Generation produces matches; every match is full (4 for doubles, 2 for singles); nobody is drawn twice into one match |
| **Role enforcement** | Staff get 403 on four administrator-only pages; customers get 403 on `/admin`; guests are redirected to login |
| **CSRF** | A `POST` with an invalid token is rejected |
| **Reporting** | All five reports export valid CSV |

---

## Current result

Run against PHP 8.3.33 and MariaDB 10.11.9:

```
passed: 50   failed: 0   skipped: 0
```

---

## Files

| File | Contains |
|------|----------|
| `run.php` | Entry point and the static + database checks |
| `http_checks.php` | The functional checks; included by `run.php` when a base URL is given |

Both are plain PHP with no dependencies — no PHPUnit, no Composer. They run wherever the
application runs.

---

## Two findings worth mentioning at the defense

Real bugs this suite caught during development, which is the honest answer to "did your
testing actually find anything?":

1. **A reused SQL named parameter** broke the availability page entirely. MySQL will not
   accept the same `:placeholder` twice in one statement when prepared-statement emulation
   is off. Check #4 now guards against it across the whole codebase.

2. **Optional form fields that were not submitted at all** were missing from the validated
   data, so reading them raised an undefined-key error — this crashed walk-in registration
   whenever the optional email field was left off the request. `Validator::validated()` now
   returns every rule key, with `null` for anything absent.
