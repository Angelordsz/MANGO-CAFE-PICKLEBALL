# Documentation Gaps and Contradictions

**Reviewed:** `ONLINE-PICKLEBALL-2.docx` (Chapters I–III, Figures 3.1–3.13, Tables 3.1–3.2)
**Purpose:** these are inconsistencies *inside the submitted paper itself*. Each one is
something a panelist can point at during defense. Each has a recommended fix and a note
on how the built system currently handles it.

---

## Gap 1 — Scope excludes a mobile app, but Chapter II requires one

**Where:** Scope and Limitation vs. Software Specification / Hardware Specification.

> Scope: "The study shall not comprise … any form of a native mobile application."

> Software Spec: "Dart is the programming language utilized in developing the mobile application using the Flutter framework."

> Hardware Spec: "Android device for mobile app access to create user profile, player matching, court reservation, and function hall reservation."

These cannot both be true. The Scope forbids exactly what the two specification
sections require.

**Recommended fix — pick one and make all three sections agree:**

- **Option A (lower risk):** Remove Dart/Flutter from the Software Spec and the Android
  device from the Hardware Spec. Describe the deliverable as a *responsive web
  application* that works in a mobile browser. Keep "native mobile application" in the
  limitations.
- **Option B:** Delete the mobile-app exclusion from the Scope and commit to building
  the Flutter client. This roughly doubles the implementation workload.

**As built:** Option A. The web app is mobile-first and responsive, so it runs in an
Android browser without a native client.

---

## Gap 2 — Scope excludes online payment, but the design includes a payment module

**Where:** Scope and Limitation vs. Figure 3.4, Figures 3.8 and 3.10, Testing section.

> Scope: "The study shall not comprise a real-time online payment for any booking or rental made in the application as payment can be made according to existing payment system of Mango Drive Café."

Yet the `Payment` class specifies `processPayment()`, `verifyPayment()` and
`generateReceipt()`; the `Admin` class specifies `verifyPayment()`; Figure 3.8 is
"Admin Manage Participant Payment for Event"; Figure 3.10 is "Admin Generate Payment
Report"; and the Testing section lists a "Payment module."

**This one is reconcilable** — and Figure 3.5 shows how, with its "Record Walk-in
Payment" step. The system does not *process* payments; it *records* payments taken at
the counter and lets an admin verify them.

**Recommended fix:** Add one clarifying sentence to the Scope:

> "The application records and verifies payments received through Mango Drive Café's
> existing payment channels (cash, GCash, Maya) for reporting purposes. It does not
> process online payment transactions or integrate with any payment gateway."

Then rename `processPayment()` to `recordPayment()` in Figure 3.4 to remove the last
ambiguity.

**As built:** Record-only. The `payments` table has no gateway or transaction-ID
fields at all, which makes the limitation architecturally true rather than just stated.

---

## Gap 3 — The Event module has no use case and no class *(most serious)*

**Where:** Figures 3.6, 3.7, 3.8, 3.9 and the Testing section all describe Event
Management. But:

- There is **no Event class** in Figure 3.4 (Class Diagram).
- There is **no "Manage Events" use case** in Figure 3.2 (Admin Use Case Diagram).
- Events are **not mentioned** in the Objectives, Scope, or Definition of Terms.

Four of your nine activity diagrams describe a module that, on paper, does not exist.
This is the gap most likely to be raised, because the diagrams visibly disagree with
each other.

**Recommended fix — all three:**

1. Add to Figure 3.2: `Manage Events`, `Add Player to Event`, `Manage Participant Payment`.
2. Add an `Event` class to Figure 3.4:
   `eventID, title, eventType, description, location, startDateTime, endDateTime, capacity, fee, status`
   with `createEvent()`, `updateEvent()`, `cancelEvent()`, and an
   `EventParticipant` association class carrying `paymentStatus`.
   Relationships: `Admin 1 → 0..* Event`, `Event 1 → 0..* EventParticipant 0..* ← 1 Player`.
3. Add one objective: *"Provide an event management module for organizing and tracking
   pickleball events and participant registration."*

**As built:** `events` and `event_participants` tables exist and implement Figures
3.6–3.9 exactly as drawn.

---

## Gap 4 — Tournament functionality is both excluded and required

**Where:** Scope excludes "any tournament management function," but the `Player` class
in Figure 3.4 has a `joinTournament()` method.

**Recommended fix:** Delete `joinTournament()` from the Player class. It is the only
tournament reference in the design and removing it costs nothing.

**As built:** No tournament features. The `events` table has an `event_type` enum
(`open_play`, `clinic`, `league`, `exhibition`, `social`, `private`) which deliberately
does **not** include `tournament`.

---

## Gap 5 — "Staff" acts in the system but is not an account type

**Where:** Scope says "The proposed application should have 2 types of users such as
customer user accounts and admin accounts." But Figure 3.2's actor is labelled
**"Admin/Staff"**, and Figures 3.5 and 3.12 describe a *staff member* performing
walk-in registration.

Walk-in registration is a counter task. Giving every counter employee the full admin
account that can delete users, change settings and view system logs is a real security
weakness, and a panelist may ask about it.

**Recommended fix:** State that the admin account type has two permission levels —
Administrator (full access) and Staff (walk-in registration, reservations, payment
recording only). This keeps the "2 account types" claim accurate while matching what
the diagrams show.

**As built:** `users.role` is `admin` / `staff` / `customer`. Staff are blocked from
user management, settings, system logs and payment *verification* — a staff member can
record a payment, only an admin can verify it, which is standard separation of duties.

---

## Gap 6 — The `History` class is underspecified

**Where:** Figure 3.4, `History` class: `date`, `time`, `payment`, with
`submitHistory()` and `editHistory()`.

It is unclear what a History record *is*. It has a payment amount but no link to what
was paid for, and no activity type. `SkillLevel 1 → 0..* History` ("writes") is an odd
relationship — a skill level does not author history.

**Recommended fix:** Re-associate it as `Player 1 → 0..* History` and add an
`activityType` attribute.

**As built:** `player_history` is a per-player activity ledger with
`activity_type` (`reservation`, `match`, `event`, `payment`, `registration`),
`reference_id`, `activity_date`, `activity_time`, `description`, `payment`.

---

## Gap 7 — Reservation approval is in the diagrams but not the objectives

**Where:** Figure 3.2 has `Approve / Reject Reservation` and Figure 3.3 has `Submit
Reservation Request`, so reservations are clearly *requests* awaiting admin action. But
Objective 3 says only "Develop an online reservation system … with real-time schedule
availability," which reads as instant confirmation.

**Recommended fix:** Reword Objective 3: *"…with real-time schedule availability and
administrator approval of reservation requests."*

**As built:** `reservations.status` starts at `pending` and requires admin
approve/reject. Slots are held on submission so two people cannot request the same
slot.

---

## Gap 8 — Minor items

| Item | Issue | Fix |
|------|-------|-----|
| Figure 3.5 | The first two nodes are both labelled "Open Reservation System" — a copy-paste duplicate | Rename the second to "Staff Logs In" |
| Table 3.2 | A stray artifact `left1999100` appears in the caption area | Delete the stray text box |
| References | Merriam-Webster is dated 2026 and DUPR/USA Pickleball 2025, but deployment is planned for 2027 | Confirm the dates are intentional |
| DUPR reference | DUPR is cited in the References but appears nowhere in the design, and Scope explicitly excludes ranking algorithms | Either drop the citation or cite it in Chapter II as the rating concept the skill-level field simplifies |
| Ch. I typo | "Pickle ball" (two words) in the first sentence; "Pickleball" elsewhere | Make consistent |
