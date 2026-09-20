-- ============================================================================
--  ONLINE PICKLEBALL PLAYER MATCHING AND FUNCTION HALL AND COURT
--  RESERVATION MANAGEMENT APPLICATION  --  Mango Drive Cafe
--  ------------------------------------------------------------------------
--  Database schema (MySQL 8.0+ / MariaDB 10.4+)
--
--  Traceability: every table below maps to a class in Figure 3.4 (Class
--  Diagram) or to a use case in Figure 3.2 / 3.3. The mapping is noted in the
--  comment above each table. Tables prefixed `enh_` are NOT part of the
--  approved scope -- they are the separated enhancement layer and can be
--  dropped without affecting any documented use case.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `pickleball_mdc`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE `pickleball_mdc`;

-- ============================================================================
-- 1. ACCOUNTS & IDENTITY
-- ============================================================================

-- Class: Admin + account half of Player. Use cases: Login, Register/Create
-- Account, Manage Users/Customers.
-- The class diagram models Admin and Player as separate classes. They are
-- implemented as one `users` table with a `role` discriminator because both
-- authenticate through the same login form; the Player's domain attributes
-- live in `players` (see below). This is a standard normalisation of the
-- diagram, not a change of scope.
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role`            ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer',
  `username`        VARCHAR(50)  NOT NULL,
  `email`           VARCHAR(190) NOT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `status`          ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at`   DATETIME NULL,
  `last_login_ip`   VARCHAR(45) NULL,
  `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`    DATETIME NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_status` (`role`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Use case: Login (forgot-password support).
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at`    DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pwreset_token` (`token_hash`),
  KEY `idx_pwreset_user` (`user_id`),
  CONSTRAINT `fk_pwreset_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class: SkillLevel. Use case: Select Skill Level.
-- Reference table so the matching module pairs on a controlled vocabulary
-- rather than free text (Scope: matching uses "the stated skill level").
DROP TABLE IF EXISTS `skill_levels`;
CREATE TABLE `skill_levels` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `level_name`  VARCHAR(40)  NOT NULL,
  `level_code`  VARCHAR(20)  NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `rating_low`  DECIMAL(3,1) NULL COMMENT 'Indicative self-rating floor, e.g. 2.0',
  `rating_high` DECIMAL(3,1) NULL COMMENT 'Indicative self-rating ceiling, e.g. 3.0',
  `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_skill_code` (`level_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class: Player. Use cases: Manage Profile, Manage Player Records,
-- Walk-in Registration (Fig. 3.5 / 3.12).
-- `user_id` is NULL for walk-in players recorded at the counter by staff --
-- they have a player record but no login. This is what makes Figure 3.5
-- implementable.
DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NULL COMMENT 'NULL = walk-in player, no account',
  `player_code`     VARCHAR(20)  NOT NULL COMMENT 'Human-readable ID e.g. PL-000123',
  `first_name`      VARCHAR(60)  NOT NULL,
  `last_name`       VARCHAR(60)  NOT NULL,
  `gender`          ENUM('male','female','other','prefer_not_to_say') NOT NULL DEFAULT 'prefer_not_to_say',
  `birthdate`       DATE NULL,
  `age`             SMALLINT UNSIGNED NULL COMMENT 'Denormalised from birthdate; kept because Fig. 3.4 lists age',
  `phone_number`    VARCHAR(30)  NULL,
  `email`           VARCHAR(190) NULL,
  `address`         VARCHAR(255) NULL,
  `skill_level_id`  TINYINT UNSIGNED NULL,
  `preferred_days`  VARCHAR(60)  NULL COMMENT 'CSV of 0-6 (Sun-Sat), used by matching',
  `preferred_time`  ENUM('morning','afternoon','evening','any') NOT NULL DEFAULT 'any',
  `avatar_path`     VARCHAR(255) NULL,
  `is_walk_in`      TINYINT(1) NOT NULL DEFAULT 0,
  `registered_by`   INT UNSIGNED NULL COMMENT 'Staff user who recorded a walk-in',
  `notes`           TEXT NULL,
  `status`          ENUM('active','inactive','blacklisted') NOT NULL DEFAULT 'active',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_players_code` (`player_code`),
  UNIQUE KEY `uq_players_user` (`user_id`),
  KEY `idx_players_skill` (`skill_level_id`),
  KEY `idx_players_name` (`last_name`,`first_name`),
  KEY `idx_players_phone` (`phone_number`),
  CONSTRAINT `fk_players_user`  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_players_skill` FOREIGN KEY (`skill_level_id`) REFERENCES `skill_levels`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_players_regby` FOREIGN KEY (`registered_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. FACILITIES  (Mango Drive Cafe only -- Scope: single establishment)
-- ============================================================================

-- Class: Court. Use case: Manage Courts.
DROP TABLE IF EXISTS `courts`;
CREATE TABLE `courts` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `court_name`    VARCHAR(60)  NOT NULL,
  `court_code`    VARCHAR(20)  NOT NULL,
  `type`          ENUM('indoor','outdoor','covered') NOT NULL DEFAULT 'outdoor',
  `surface`       VARCHAR(40)  NULL,
  `hourly_rate`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `capacity`      TINYINT UNSIGNED NOT NULL DEFAULT 4,
  `description`   TEXT NULL,
  `image_path`    VARCHAR(255) NULL,
  `status`        ENUM('available','maintenance','inactive') NOT NULL DEFAULT 'available',
  `sort_order`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_courts_code` (`court_code`),
  KEY `idx_courts_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class: FunctionHall. Use case: Manage Function Hall.
DROP TABLE IF EXISTS `function_halls`;
CREATE TABLE `function_halls` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hall_name`    VARCHAR(60)  NOT NULL,
  `hall_code`    VARCHAR(20)  NOT NULL,
  `capacity`     SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  `rental_fee`   DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Per hour',
  `min_hours`    TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `amenities`    TEXT NULL COMMENT 'CSV: projector, sound system, aircon, catering',
  `description`  TEXT NULL,
  `image_path`   VARCHAR(255) NULL,
  `status`       ENUM('available','maintenance','inactive') NOT NULL DEFAULT 'available',
  `sort_order`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_halls_code` (`hall_code`),
  KEY `idx_halls_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class: Schedule (Court 1..* Schedule, FunctionHall 1..* Schedule).
-- Use cases: Manage Facility Schedules, View Facility Schedules and Availability.
-- One row = one bookable time slot on one facility on one date. Admin generates
-- these in bulk; customers reserve against them. Because a slot is a physical
-- row, "is this free?" is a single indexed lookup rather than an overlap
-- calculation -- this is the mechanism that prevents the double bookings
-- described in the Project Context.
DROP TABLE IF EXISTS `schedules`;
CREATE TABLE `schedules` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `facility_type`  ENUM('court','function_hall') NOT NULL,
  `facility_id`    INT UNSIGNED NOT NULL,
  `slot_date`      DATE NOT NULL,
  `start_time`     TIME NOT NULL,
  `end_time`       TIME NOT NULL,
  `price`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Snapshot of rate when generated',
  `status`         ENUM('available','blocked','maintenance') NOT NULL DEFAULT 'available',
  `block_reason`   VARCHAR(255) NULL,
  `created_by`     INT UNSIGNED NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_slot` (`facility_type`,`facility_id`,`slot_date`,`start_time`),
  KEY `idx_schedule_lookup` (`slot_date`,`facility_type`,`status`),
  KEY `idx_schedule_facility` (`facility_type`,`facility_id`,`slot_date`),
  CONSTRAINT `fk_schedule_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_schedule_time` CHECK (`end_time` > `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. RESERVATIONS
-- ============================================================================

-- Class: Reservation. Use cases: Reserve Pickleball Court, Reserve Function
-- Hall, Submit Reservation Request, Approve/Reject Reservation,
-- Cancel/Reschedule Reservation, View My Reservations, Manage Reservations.
-- Status flow:  pending -> approved -> completed
--                       -> rejected
--               approved -> cancelled | rescheduled | no_show
DROP TABLE IF EXISTS `reservations`;
CREATE TABLE `reservations` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_code`  VARCHAR(20) NOT NULL COMMENT 'e.g. MDC-2026-000431',
  `player_id`         INT UNSIGNED NULL COMMENT 'Who the booking is for (incl. walk-ins)',
  `user_id`           INT UNSIGNED NULL COMMENT 'Account that submitted it; NULL for staff-entered walk-ins',
  `reservation_type`  ENUM('court','function_hall') NOT NULL,
  `facility_id`       INT UNSIGNED NOT NULL COMMENT 'courts.id or function_halls.id per reservation_type',
  `reservation_date`  DATE NOT NULL,
  `start_time`        TIME NOT NULL,
  `end_time`          TIME NOT NULL,
  `duration_hours`    DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  `party_size`        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `purpose`           VARCHAR(255) NULL COMMENT 'Event purpose for function hall bookings',
  `total_amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`            ENUM('pending','approved','rejected','cancelled','completed','no_show','rescheduled') NOT NULL DEFAULT 'pending',
  `source`            ENUM('online','walk_in','phone') NOT NULL DEFAULT 'online',
  `customer_notes`    TEXT NULL,
  `admin_remarks`     TEXT NULL,
  `reviewed_by`       INT UNSIGNED NULL,
  `reviewed_at`       DATETIME NULL,
  `cancelled_by`      INT UNSIGNED NULL,
  `cancelled_at`      DATETIME NULL,
  `cancel_reason`     VARCHAR(255) NULL,
  `rescheduled_from`  INT UNSIGNED NULL COMMENT 'Previous reservation this one replaces',
  `created_by`        INT UNSIGNED NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_res_code` (`reservation_code`),
  KEY `idx_res_player` (`player_id`,`status`),
  KEY `idx_res_user` (`user_id`,`status`),
  KEY `idx_res_date` (`reservation_date`,`status`),
  KEY `idx_res_facility` (`reservation_type`,`facility_id`,`reservation_date`),
  KEY `idx_res_status_created` (`status`,`created_at`),
  CONSTRAINT `fk_res_player`    FOREIGN KEY (`player_id`)        REFERENCES `players`(`id`)      ON DELETE SET NULL,
  CONSTRAINT `fk_res_user`      FOREIGN KEY (`user_id`)          REFERENCES `users`(`id`)        ON DELETE SET NULL,
  CONSTRAINT `fk_res_reviewer`  FOREIGN KEY (`reviewed_by`)      REFERENCES `users`(`id`)        ON DELETE SET NULL,
  CONSTRAINT `fk_res_canceller` FOREIGN KEY (`cancelled_by`)     REFERENCES `users`(`id`)        ON DELETE SET NULL,
  CONSTRAINT `fk_res_creator`   FOREIGN KEY (`created_by`)       REFERENCES `users`(`id`)        ON DELETE SET NULL,
  CONSTRAINT `fk_res_prev`      FOREIGN KEY (`rescheduled_from`) REFERENCES `reservations`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_res_time` CHECK (`end_time` > `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Links a reservation to the exact schedule slots it consumes.
-- The UNIQUE key on `schedule_id` is the database-level guarantee that two
-- reservations can never hold the same slot -- the core defect the study set
-- out to eliminate. Rows are deleted when a reservation is rejected or
-- cancelled, which returns the slot to the pool.
DROP TABLE IF EXISTS `reservation_schedules`;
CREATE TABLE `reservation_schedules` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id` INT UNSIGNED NOT NULL,
  `schedule_id`    INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slot_taken` (`schedule_id`),
  KEY `idx_resched_res` (`reservation_id`),
  CONSTRAINT `fk_resched_res`  FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_resched_slot` FOREIGN KEY (`schedule_id`)    REFERENCES `schedules`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. PAYMENTS  (RECORD-ONLY)
--    Scope: "The study shall not comprise a real-time online payment for any
--    booking or rental made in the application." Money changes hands at the
--    counter; this module records and verifies it. See Fig. 3.5 "Record
--    Walk-in Payment", Fig. 3.8, Fig. 3.10, Admin.verifyPayment().
-- ============================================================================

-- Class: Payment.
-- Polymorphic: a payment settles either a reservation or an event
-- registration. No gateway fields and no external transaction IDs by design.
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_code`    VARCHAR(20) NOT NULL COMMENT 'Receipt number, e.g. OR-000128',
  `payable_type`    ENUM('reservation','event_participant') NOT NULL,
  `payable_id`      INT UNSIGNED NOT NULL,
  `player_id`       INT UNSIGNED NULL,
  `amount_due`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `amount`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount actually received',
  `payment_method`  ENUM('cash','gcash','maya','bank_transfer','card','other') NOT NULL DEFAULT 'cash',
  `reference_no`    VARCHAR(60) NULL COMMENT 'GCash/Maya ref no. typed in by staff',
  `payment_date`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_status`  ENUM('unpaid','partial','paid','verified','refunded','void') NOT NULL DEFAULT 'unpaid',
  `recorded_by`     INT UNSIGNED NULL COMMENT 'Staff who took the money',
  `verified_by`     INT UNSIGNED NULL COMMENT 'Admin.verifyPayment()',
  `verified_at`     DATETIME NULL,
  `remarks`         VARCHAR(255) NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_code` (`payment_code`),
  KEY `idx_pay_payable` (`payable_type`,`payable_id`),
  KEY `idx_pay_status_date` (`payment_status`,`payment_date`),
  KEY `idx_pay_player` (`player_id`),
  CONSTRAINT `fk_pay_player`   FOREIGN KEY (`player_id`)   REFERENCES `players`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL,
  CONSTRAINT `fk_pay_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. PLAYER MATCHING
--    Class: PlayerMatching (matchingID, matchDate, status; findOpponent(),
--    matchPlayer(), cancelMatch()). Use cases: Register for Player Matching,
--    Select Playing Date and Time, Select Skill Level, View Matching Results,
--    Manage Player Matching, Generate Match Assignments.
--
--    Scope constraint: "Automatic players' matching is limited to players that
--    register for the selected date and the stated skill level of the players,
--    not incorporating statistical values or sophisticated ranking algorithm."
--    Therefore pairing is a RANDOM shuffle inside a (date, time block, skill
--    level) pool. No ELO, no seeding, no historical weighting.
-- ============================================================================

-- One pool = one (date, time block, skill level) bucket that players sign up
-- to. The admin locks a pool and generates assignments from it.
DROP TABLE IF EXISTS `matching_pools`;
CREATE TABLE `matching_pools` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pool_code`      VARCHAR(20) NOT NULL,
  `match_date`     DATE NOT NULL,
  `time_block`     ENUM('morning','afternoon','evening') NOT NULL,
  `start_time`     TIME NOT NULL,
  `end_time`       TIME NOT NULL,
  `skill_level_id` TINYINT UNSIGNED NOT NULL,
  `match_format`   ENUM('singles','doubles') NOT NULL DEFAULT 'doubles',
  `min_players`    TINYINT UNSIGNED NOT NULL DEFAULT 4,
  `max_players`    TINYINT UNSIGNED NOT NULL DEFAULT 16,
  `status`         ENUM('open','locked','generated','completed','cancelled') NOT NULL DEFAULT 'open',
  `generated_at`   DATETIME NULL,
  `generated_by`   INT UNSIGNED NULL,
  `notes`          VARCHAR(255) NULL,
  `created_by`     INT UNSIGNED NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pool_code` (`pool_code`),
  UNIQUE KEY `uq_pool_bucket` (`match_date`,`time_block`,`skill_level_id`),
  KEY `idx_pool_date_status` (`match_date`,`status`),
  CONSTRAINT `fk_pool_skill`   FOREIGN KEY (`skill_level_id`) REFERENCES `skill_levels`(`id`),
  CONSTRAINT `fk_pool_genby`   FOREIGN KEY (`generated_by`)   REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pool_creator` FOREIGN KEY (`created_by`)     REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_pool_time` CHECK (`end_time` > `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A player's signup to a pool. UNIQUE(pool_id, player_id) stops double entry.
DROP TABLE IF EXISTS `matching_requests`;
CREATE TABLE `matching_requests` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pool_id`       INT UNSIGNED NOT NULL,
  `player_id`     INT UNSIGNED NOT NULL,
  `status`        ENUM('registered','matched','unmatched','cancelled','no_show') NOT NULL DEFAULT 'registered',
  `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cancelled_at`  DATETIME NULL,
  `registered_by` INT UNSIGNED NULL COMMENT 'Set when staff signs up a walk-in',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_request_once` (`pool_id`,`player_id`),
  KEY `idx_request_player` (`player_id`,`status`),
  CONSTRAINT `fk_req_pool`   FOREIGN KEY (`pool_id`)       REFERENCES `matching_pools`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_req_player` FOREIGN KEY (`player_id`)     REFERENCES `players`(`id`)        ON DELETE CASCADE,
  CONSTRAINT `fk_req_regby`  FOREIGN KEY (`registered_by`) REFERENCES `users`(`id`)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Output of Generate Match Assignments: one row per generated match.
DROP TABLE IF EXISTS `match_assignments`;
CREATE TABLE `match_assignments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pool_id`     INT UNSIGNED NOT NULL,
  `court_id`    INT UNSIGNED NULL,
  `schedule_id` INT UNSIGNED NULL COMMENT 'Slot the match occupies, when one was reserved',
  `match_no`    SMALLINT UNSIGNED NOT NULL COMMENT 'Sequence within the pool',
  `round_no`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `start_time`  TIME NULL,
  `end_time`    TIME NULL,
  `status`      ENUM('scheduled','ongoing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assignment_no` (`pool_id`,`match_no`),
  KEY `idx_assign_court` (`court_id`),
  CONSTRAINT `fk_assign_pool`  FOREIGN KEY (`pool_id`)     REFERENCES `matching_pools`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_court` FOREIGN KEY (`court_id`)    REFERENCES `courts`(`id`)         ON DELETE SET NULL,
  CONSTRAINT `fk_assign_slot`  FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who plays in a generated match, and on which side.
DROP TABLE IF EXISTS `match_assignment_players`;
CREATE TABLE `match_assignment_players` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` INT UNSIGNED NOT NULL,
  `player_id`     INT UNSIGNED NOT NULL,
  `team`          TINYINT UNSIGNED NOT NULL COMMENT '1 or 2',
  `position`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_player_per_match` (`assignment_id`,`player_id`),
  KEY `idx_map_player` (`player_id`),
  CONSTRAINT `fk_map_assign` FOREIGN KEY (`assignment_id`) REFERENCES `match_assignments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_map_player` FOREIGN KEY (`player_id`)     REFERENCES `players`(`id`)           ON DELETE CASCADE,
  CONSTRAINT `chk_map_team` CHECK (`team` IN (1,2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. EVENTS
--    NOTE FOR THE PAPER: Events appear in Figures 3.6, 3.7, 3.8, 3.9 and in
--    the Testing section, but there is NO Event class in Figure 3.4 and no
--    Event use case in Figures 3.2/3.3. This table set implements the four
--    activity diagrams as drawn. See docs/DOCUMENT-GAPS.md -- Chapter III
--    should be amended to add an Event class and the matching admin use case.
-- ============================================================================

-- Fields taken verbatim from Fig. 3.6 "Fill in Event Information".
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_code`    VARCHAR(20) NOT NULL,
  `title`         VARCHAR(150) NOT NULL,
  `event_type`    ENUM('open_play','clinic','league','exhibition','social','private') NOT NULL DEFAULT 'open_play',
  `description`   TEXT NULL,
  `location`      VARCHAR(150) NOT NULL DEFAULT 'Mango Drive Cafe',
  `court_id`      INT UNSIGNED NULL,
  `start_datetime` DATETIME NOT NULL,
  `end_datetime`   DATETIME NOT NULL,
  `capacity`      SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  `fee`           DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Per participant',
  `skill_level_id` TINYINT UNSIGNED NULL COMMENT 'NULL = open to all levels',
  `image_path`    VARCHAR(255) NULL,
  `status`        ENUM('draft','upcoming','active','ongoing','completed','cancelled') NOT NULL DEFAULT 'draft',
  `created_by`    INT UNSIGNED NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_code` (`event_code`),
  KEY `idx_event_status_start` (`status`,`start_datetime`),
  CONSTRAINT `fk_event_court`   FOREIGN KEY (`court_id`)       REFERENCES `courts`(`id`)       ON DELETE SET NULL,
  CONSTRAINT `fk_event_skill`   FOREIGN KEY (`skill_level_id`) REFERENCES `skill_levels`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_event_creator` FOREIGN KEY (`created_by`)     REFERENCES `users`(`id`)        ON DELETE SET NULL,
  CONSTRAINT `chk_event_time` CHECK (`end_datetime` > `start_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fig. 3.7 Add Player to Event; Fig. 3.8 Manage Participant Payment.
DROP TABLE IF EXISTS `event_participants`;
CREATE TABLE `event_participants` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`       INT UNSIGNED NOT NULL,
  `player_id`      INT UNSIGNED NOT NULL,
  `amount_due`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('unpaid','partial','paid','waived','refunded') NOT NULL DEFAULT 'unpaid',
  `status`         ENUM('registered','confirmed','attended','cancelled','no_show') NOT NULL DEFAULT 'registered',
  `added_by`       INT UNSIGNED NULL COMMENT 'Admin who added the player (Fig. 3.7)',
  `registered_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `remarks`        VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_player` (`event_id`,`player_id`),
  KEY `idx_ep_player` (`player_id`),
  KEY `idx_ep_paystatus` (`payment_status`),
  CONSTRAINT `fk_ep_event`  FOREIGN KEY (`event_id`)  REFERENCES `events`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_ep_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ep_addedby` FOREIGN KEY (`added_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. NOTIFICATIONS, HISTORY, LOGS, SETTINGS
-- ============================================================================

-- Class: Notification (notificationID, message, notificationDate, status;
-- sendNotification(), markAsRead()). Use cases: Send Notifications,
-- Receive Notifications.
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL COMMENT 'Recipient account; NULL = broadcast',
  `player_id`   INT UNSIGNED NULL COMMENT 'Recipient player record (walk-ins have no account)',
  `type`        ENUM('reservation_submitted','reservation_approved','reservation_rejected',
                     'reservation_cancelled','reservation_reminder','match_assigned',
                     'matching_registered','matching_cancelled','event_invite','event_reminder',
                     'payment_recorded','payment_verified','announcement','system') NOT NULL DEFAULT 'system',
  `title`       VARCHAR(150) NOT NULL,
  `message`     TEXT NOT NULL,
  `link`        VARCHAR(255) NULL,
  `status`      ENUM('unread','read','archived') NOT NULL DEFAULT 'unread',
  `sent_by`     INT UNSIGNED NULL,
  `read_at`     DATETIME NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`,`status`,`created_at`),
  KEY `idx_notif_player` (`player_id`),
  CONSTRAINT `fk_notif_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_notif_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sent_by`)   REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class: History (date, time, payment; submitHistory(), editHistory()).
-- A per-player activity ledger: what they did, when, and what it cost. Feeds
-- the player record screen and the utilisation reports.
DROP TABLE IF EXISTS `player_history`;
CREATE TABLE `player_history` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `player_id`     INT UNSIGNED NOT NULL,
  `activity_type` ENUM('reservation','match','event','payment','registration') NOT NULL,
  `reference_id`  INT UNSIGNED NULL COMMENT 'PK of the row in the related table',
  `activity_date` DATE NOT NULL,
  `activity_time` TIME NULL,
  `description`   VARCHAR(255) NOT NULL,
  `payment`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hist_player_date` (`player_id`,`activity_date`),
  KEY `idx_hist_type` (`activity_type`),
  CONSTRAINT `fk_hist_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Use case: View System Logs (Fig. 3.2). Audit trail of every state change.
DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL,
  `action`      VARCHAR(60) NOT NULL COMMENT 'e.g. reservation.approve',
  `entity_type` VARCHAR(40) NULL,
  `entity_id`   INT UNSIGNED NULL,
  `description` VARCHAR(255) NOT NULL,
  `ip_address`  VARCHAR(45) NULL,
  `user_agent`  VARCHAR(255) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_user_date` (`user_id`,`created_at`),
  KEY `idx_log_action` (`action`,`created_at`),
  KEY `idx_log_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Operating hours used to generate schedule slots, plus platform settings.
DROP TABLE IF EXISTS `operating_hours`;
CREATE TABLE `operating_hours` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `day_of_week` TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday .. 6=Saturday',
  `open_time`   TIME NOT NULL,
  `close_time`  TIME NOT NULL,
  `is_closed`   TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hours_day` (`day_of_week`),
  CONSTRAINT `chk_dow` CHECK (`day_of_week` BETWEEN 0 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(60) NOT NULL,
  `setting_value` TEXT NULL,
  `setting_group` VARCHAR(40) NOT NULL DEFAULT 'general',
  `description`   VARCHAR(255) NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. ENHANCEMENT LAYER  (NOT IN THE APPROVED SCOPE)
--    Everything below is outside Figures 3.2/3.3/3.4. It is isolated behind
--    the `enh_` prefix and the ENHANCEMENTS feature flag in config/app.php.
--    Dropping these four tables leaves every documented use case working.
-- ============================================================================

-- Score entry for generated matches -> win/loss record on the profile.
DROP TABLE IF EXISTS `enh_match_results`;
CREATE TABLE `enh_match_results` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` INT UNSIGNED NOT NULL,
  `team1_score`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `team2_score`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `winning_team`  TINYINT UNSIGNED NULL,
  `recorded_by`   INT UNSIGNED NULL,
  `recorded_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_result_assignment` (`assignment_id`),
  CONSTRAINT `fk_result_assign` FOREIGN KEY (`assignment_id`) REFERENCES `match_assignments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_result_user`   FOREIGN KEY (`recorded_by`)   REFERENCES `users`(`id`)             ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Denormalised counters so the profile page is one row read.
DROP TABLE IF EXISTS `enh_player_stats`;
CREATE TABLE `enh_player_stats` (
  `player_id`     INT UNSIGNED NOT NULL,
  `games_played`  INT UNSIGNED NOT NULL DEFAULT 0,
  `wins`          INT UNSIGNED NOT NULL DEFAULT 0,
  `losses`        INT UNSIGNED NOT NULL DEFAULT 0,
  `last_played_at` DATE NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`player_id`),
  CONSTRAINT `fk_stats_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 9. REPORTING VIEWS  (Use case: Generate Reports, Fig. 3.9 / 3.10)
-- ============================================================================

-- Daily facility utilisation: slots offered vs slots taken.
CREATE OR REPLACE VIEW `v_facility_utilisation` AS
SELECT
  s.slot_date,
  s.facility_type,
  s.facility_id,
  COUNT(*)                                        AS slots_offered,
  SUM(CASE WHEN rs.id IS NOT NULL THEN 1 ELSE 0 END) AS slots_booked,
  ROUND(100 * SUM(CASE WHEN rs.id IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*), 1) AS utilisation_pct
FROM `schedules` s
LEFT JOIN `reservation_schedules` rs ON rs.schedule_id = s.id
GROUP BY s.slot_date, s.facility_type, s.facility_id;

-- Revenue actually collected, by day and method.
CREATE OR REPLACE VIEW `v_payment_summary` AS
SELECT
  DATE(p.payment_date) AS payment_day,
  p.payment_method,
  p.payable_type,
  COUNT(*)             AS txn_count,
  SUM(p.amount)        AS total_collected
FROM `payments` p
WHERE p.payment_status IN ('paid','verified','partial')
GROUP BY DATE(p.payment_date), p.payment_method, p.payable_type;

-- Reservation funnel, for the dashboard and the reservation report.
CREATE OR REPLACE VIEW `v_reservation_summary` AS
SELECT
  r.reservation_date,
  r.reservation_type,
  r.status,
  COUNT(*)            AS reservation_count,
  SUM(r.total_amount) AS gross_amount
FROM `reservations` r
GROUP BY r.reservation_date, r.reservation_type, r.status;
