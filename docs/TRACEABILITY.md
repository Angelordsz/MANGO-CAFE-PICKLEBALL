# Requirements Traceability Matrix

**System:** Online Pickleball Player Matching and Function Hall and Court Reservation Management Application
**Client:** Mango Drive Café
**Source document:** `ONLINE-PICKLEBALL-2.docx` — Chapter III, Figures 3.2, 3.3, 3.4

Every use case in the submitted use case diagrams maps to a route, a controller
method and a set of tables. Nothing in the approved scope is unimplemented, and
nothing outside the approved scope appears here (see `ENHANCEMENTS.md` for the
separated extras).

---

## Actor 1 — Admin / Staff (Figure 3.2)

| # | Use case | Route | Controller | Tables |
|---|----------|-------|------------|--------|
| A1 | Login | `GET/POST /login` | `AuthController@showLogin`, `@login` | `users`, `system_logs` |
| A2 | Dashboard Overview | `GET /admin` | `Admin\DashboardController@index` | `reservations`, `payments`, `players`, `v_*` views |
| A3 | Manage Users / Customers | `GET /admin/users` | `Admin\UserController@index/create/store/edit/update/toggle` | `users` |
| A4 | Manage Player Records | `GET /admin/players` | `Admin\PlayerController@index/show/create/store/edit/update` | `players`, `skill_levels`, `player_history` |
| A5 | Manage Courts | `GET /admin/courts` | `Admin\CourtController@index/create/store/edit/update/destroy` | `courts` |
| A6 | Manage Function Hall | `GET /admin/halls` | `Admin\HallController@index/create/store/edit/update/destroy` | `function_halls` |
| A7 | Manage Facility Schedules | `GET /admin/schedules` | `Admin\ScheduleController@index/generate/block/unblock` | `schedules`, `operating_hours`, `courts`, `function_halls` |
| A8 | Manage Reservations | `GET /admin/reservations` | `Admin\ReservationController@index/show` | `reservations`, `reservation_schedules` |
| A9 | Approve / Reject Reservation | `POST /admin/reservations/{id}/approve`, `/reject` | `Admin\ReservationController@approve/reject` | `reservations`, `notifications`, `system_logs` |
| A10 | Cancel / Reschedule Reservation | `POST /admin/reservations/{id}/cancel`, `GET/POST .../reschedule` | `Admin\ReservationController@cancel/reschedule` | `reservations`, `reservation_schedules`, `notifications` |
| A11 | Manage Player Matching | `GET /admin/matching` | `Admin\MatchingController@index/create/store/lock/cancel` | `matching_pools`, `matching_requests` |
| A12 | Generate Match Assignments | `POST /admin/matching/{id}/generate` | `Admin\MatchingController@generate` | `match_assignments`, `match_assignment_players`, `notifications` |
| A13 | Send Notifications | `GET/POST /admin/notifications` | `Admin\NotificationController@index/send` | `notifications`, `users`, `players` |
| A14 | Generate Reports | `GET /admin/reports/{type}` | `Admin\ReportController@reservation/utilisation/event/payment` | all `v_*` views, `reservations`, `payments`, `events` |
| A15 | View System Logs | `GET /admin/logs` | `Admin\LogController@index` | `system_logs` |

## Actor 2 — Customer / Player (Figure 3.3)

| # | Use case | Route | Controller | Tables |
|---|----------|-------|------------|--------|
| C1 | Register / Create Account | `GET/POST /register` | `AuthController@showRegister`, `@register` | `users`, `players`, `skill_levels` |
| C2 | Login | `GET/POST /login` | `AuthController@showLogin`, `@login` | `users` |
| C3 | Manage Profile | `GET/POST /profile` | `ProfileController@edit/update` | `players`, `users`, `skill_levels` |
| C4 | View Facility Schedules and Availability | `GET /availability` | `AvailabilityController@index` | `schedules`, `courts`, `function_halls`, `reservation_schedules` |
| C5 | Reserve Pickleball Court | `GET /reserve/court` | `ReservationController@court` | `courts`, `schedules` |
| C6 | Reserve Function Hall | `GET /reserve/hall` | `ReservationController@hall` | `function_halls`, `schedules` |
| C7 | Submit Reservation Request | `POST /reserve` | `ReservationController@store` | `reservations`, `reservation_schedules`, `notifications` |
| C8 | Cancel / Reschedule Reservation | `POST /reservations/{id}/cancel`, `GET/POST .../reschedule` | `ReservationController@cancel/reschedule` | `reservations`, `reservation_schedules` |
| C9 | Register for Player Matching | `GET/POST /matching` | `MatchingController@index/register` | `matching_pools`, `matching_requests` |
| C10 | Select Playing Date and Time | part of C9 form | `MatchingController@index` | `matching_pools` |
| C11 | Select Skill Level | part of C9 form and C1 | `MatchingController@index`, `AuthController@register` | `skill_levels`, `players` |
| C12 | View Matching Results | `GET /matching/results` | `MatchingController@results` | `match_assignments`, `match_assignment_players` |
| C13 | View My Reservations | `GET /reservations` | `ReservationController@index/show` | `reservations` |
| C14 | Receive Notifications | `GET /notifications` | `NotificationController@index/read` | `notifications` |

## Activity diagrams (Figures 3.5 – 3.10)

| Figure | Flow | Route | Notes |
|--------|------|-------|-------|
| 3.5 | Walk-in Player registration | `GET/POST /admin/players/walk-in` | Creates a `players` row with `user_id = NULL`, `is_walk_in = 1`, then optionally chains into a reservation and a recorded payment |
| 3.6 | Admin Create Event | `GET/POST /admin/events/create` | Fields match the diagram exactly: title, type, description, location, date/time, capacity, image, status |
| 3.7 | Admin Add Player to Event | `POST /admin/events/{id}/participants` | Player search by name / contact / email, then insert into `event_participants` |
| 3.8 | Admin Manage Participant Payment | `GET/POST /admin/events/{id}/participants/{pid}/payment` | Writes `payments` with `payable_type = 'event_participant'` |
| 3.9 | Admin Generate Event Report | `GET /admin/reports/event` | Filters: date, event status, participant count; CSV + print |
| 3.10 | Admin Generate Payment Report | `GET /admin/reports/payment` | Summary or detailed mode, per Figure 3.10 |

## Sequence diagrams (Figures 3.11 – 3.13)

| Figure | Interaction | Implemented by |
|--------|-------------|----------------|
| 3.11 | Admin Create Event — dashboard → event controller → database | `Admin\EventController@create/store` |
| 3.12 | Admin Register Walk-in Player | `Admin\PlayerController@walkInForm/walkInStore` |
| 3.13 | Admin Generate Report | `Admin\ReportController` + `v_*` views |

## Class diagram → table mapping (Figure 3.4)

| Class | Table(s) | Deviation |
|-------|----------|-----------|
| Admin | `users` (role = admin/staff) | Merged with Player's account half — both authenticate identically. Domain data separated into `players`. |
| Player | `users` + `players` | Split so walk-in players can exist without an account (required by Fig. 3.5) |
| SkillLevel | `skill_levels` | Reference table, seeded |
| History | `player_history` | `date`, `time`, `payment` kept as named |
| PlayerMatching | `matching_pools` + `matching_requests` | Split: the pool is the match event, the request is a player's signup |
| Reservation | `reservations` + `reservation_schedules` | Join table added to guarantee no double booking |
| Court | `courts` | — |
| FunctionHall | `function_halls` | — |
| Schedule | `schedules` | Single table for both facility types via `facility_type` discriminator |
| Payment | `payments` | Gateway fields intentionally absent (scope excludes online payment) |
| Notification | `notifications` | — |
| *(none)* | `events`, `event_participants` | **No Event class exists in Fig. 3.4.** See `DOCUMENT-GAPS.md` gap #3. |
| *(none)* | `system_logs`, `settings`, `operating_hours`, `password_resets` | Infrastructure tables with no class-diagram counterpart |
