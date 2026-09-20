# Enhancement Layer

Everything listed here is **outside the approved capstone scope**. It is isolated so that a
panelist asking "where is that in your documentation?" gets a clean answer: *it isn't — it's
an enhancement, and here is the switch that turns it off.*

Switch in `config/config.php`:

```php
'features' => [
    'enhancements'  => true,   // master switch
    'match_results' => true,   // score entry for generated matches
    'player_stats'  => true,   // win/loss counters on the profile
    'dark_mode'     => true,   // theme toggle
],
```

Set `'enhancements' => false` for a strictly document-faithful demo. Every one of the 27
documented use cases keeps working.

---

## What is in the layer

### 1. Match result entry — `enh_match_results`

Records a score against a generated match (`match_assignments`), which produces a win/loss
record per player.

**Why it is out of scope:** the Scope explicitly excludes a "live scoring system," and
Figure 3.4 has no result or score class.

**Why it was built anyway:** the `History` class in Figure 3.4 implies some record of what
happened in a match, and the café asked for player records. This is the smallest thing that
satisfies both without adding a ranking algorithm.

### 2. Player statistics — `enh_player_stats`

Denormalised counters (`games_played`, `wins`, `losses`, `last_played_at`) so the profile
page is a single row read instead of three aggregate queries.

**Why it is out of scope:** the Scope excludes "statistical values or sophisticated ranking
algorithm" *for matching*. These counters are display-only and are never read by
`Matching::generate()` — the draw stays random, as documented. Worth saying plainly if a
panelist raises it.

### 3. Dark mode

A theme toggle in the header, remembered per browser in `localStorage`, defaulting to the
operating system preference.

**Why it is out of scope:** not mentioned anywhere in the document. It costs nothing and
helps on the phones staff actually use at the counter.

### 4. Customer-facing event browsing

Chapter III's activity diagrams (3.6–3.9) describe event management as entirely
administrator-driven. This build adds read-only `/events` and `/events/{id}` pages so
customers can see what is coming up.

**Registration is still administrator-driven**, exactly as Figure 3.7 specifies — the
customer pages carry no "join" button, only a note to speak to staff.

---

## What was deliberately NOT built

These were in the original feature brief but are excluded by the approved scope. They are
listed so the decision is on the record.

| Feature | Why not |
|---------|---------|
| Online payment gateway (GCash / Maya / Stripe / PayMongo) | Scope: "shall not comprise a real-time online payment" |
| Tournament management | Scope: "shall not comprise any tournament management function" |
| Live scoring | Scope excludes it |
| GPS / maps | Scope: "shall not comprise… GPS tracking technology" |
| Native mobile app (Flutter) | Scope excludes a native app — see [DOCUMENT-GAPS.md](DOCUMENT-GAPS.md) gap #1, which is the contradiction to resolve first |
| Multiple venues | Scope: "shall only apply to Mango Drive Café facility only" |
| Clubs / communities | Not in the document at all |
| DUPR integration, ELO ratings, leaderboards | Scope excludes ranking algorithms |
| Email / SMS notifications | No gateway in scope; notifications are in-app only |
| Split payments, wallets, promo codes, payouts | No payment processing in scope |

If your adviser wants any of these added, the corresponding section of Chapter I
(Scope and Limitation) has to change first — otherwise the code and the paper disagree,
which is exactly the problem [DOCUMENT-GAPS.md](DOCUMENT-GAPS.md) catalogues.

---

## Removing the layer entirely

To strip it from the codebase rather than just switching it off:

```sql
DROP TABLE IF EXISTS enh_match_results;
DROP TABLE IF EXISTS enh_player_stats;
```

Then delete the `features` block from `config/config.php`. No controller, model or view in
the documented paths depends on either table — they are referenced only through
`feature()` guards.
