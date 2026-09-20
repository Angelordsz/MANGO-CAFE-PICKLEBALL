<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * Figures 3.6 (Create Event), 3.7 (Add Player to Event),
 * 3.8 (Manage Participant Payment), 3.9 (Generate Event Report).
 *
 * NOTE: there is no Event class in Figure 3.4 and no Event use case in
 * Figures 3.2/3.3, even though four activity diagrams describe this module.
 * See docs/DOCUMENT-GAPS.md gap #3 -- Chapter III should be amended.
 */
final class Event
{
    public static function nextCode(): string
    {
        $seq = (int) Database::scalar('SELECT COUNT(*) + 1 FROM events', [], 1);
        return sprintf('EV-%05d', $seq);
    }

    public static function create(array $data): int
    {
        $data['event_code'] = $data['event_code'] ?? self::nextCode();
        $data['created_by'] = Auth::id();
        return Database::insert('events', $data);
    }

    public static function find(int $id): ?array
    {
        return Database::selectOne(
            'SELECT e.*, c.court_name, s.level_name,
                    (SELECT COUNT(*) FROM event_participants ep
                      WHERE ep.event_id = e.id AND ep.status <> :cancelled) AS participant_count
               FROM events e
          LEFT JOIN courts c       ON c.id = e.court_id
          LEFT JOIN skill_levels s ON s.id = e.skill_level_id
              WHERE e.id = :id',
            ['id' => $id, 'cancelled' => 'cancelled']
        );
    }

    public static function all(array $filters = [], int $limit = 50): array
    {
        $sql = "SELECT e.*, c.court_name, s.level_name,
                       (SELECT COUNT(*) FROM event_participants ep
                         WHERE ep.event_id = e.id AND ep.status <> 'cancelled') AS participant_count
                  FROM events e
             LEFT JOIN courts c       ON c.id = e.court_id
             LEFT JOIN skill_levels s ON s.id = e.skill_level_id
                 WHERE 1 = 1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND e.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND e.event_type = :type';
            $params['type'] = $filters['type'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(e.start_datetime) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(e.start_datetime) <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['public'])) {
            $sql .= " AND e.status IN ('upcoming','active','ongoing')";
        }

        return Database::select($sql . ' ORDER BY e.start_datetime DESC LIMIT ' . (int) $limit, $params);
    }

    /** Upcoming events for the customer dashboard. */
    public static function upcoming(int $limit = 5): array
    {
        return Database::select(
            "SELECT e.*, c.court_name, s.level_name,
                    (SELECT COUNT(*) FROM event_participants ep
                      WHERE ep.event_id = e.id AND ep.status <> 'cancelled') AS participant_count
               FROM events e
          LEFT JOIN courts c       ON c.id = e.court_id
          LEFT JOIN skill_levels s ON s.id = e.skill_level_id
              WHERE e.status IN ('upcoming','active','ongoing')
                AND e.end_datetime >= NOW()
           ORDER BY e.start_datetime
              LIMIT " . (int) $limit
        );
    }

    /** Fig. 3.7 -- Add Player to Event. */
    public static function addParticipant(int $eventId, int $playerId, float $amountDue): array
    {
        $event = self::find($eventId);

        if (!$event) {
            return ['ok' => false, 'error' => 'Event not found.'];
        }
        if ((int) $event['participant_count'] >= (int) $event['capacity']) {
            return ['ok' => false, 'error' => 'This event is already at capacity.'];
        }

        $existing = Database::selectOne(
            'SELECT id, status FROM event_participants WHERE event_id = :e AND player_id = :p',
            ['e' => $eventId, 'p' => $playerId]
        );

        if ($existing && $existing['status'] !== 'cancelled') {
            return ['ok' => false, 'error' => 'That player is already registered for this event.'];
        }

        if ($existing) {
            Database::update('event_participants', [
                'status'     => 'registered',
                'amount_due' => $amountDue,
            ], ['id' => $existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $id = Database::insert('event_participants', [
                'event_id'   => $eventId,
                'player_id'  => $playerId,
                'amount_due' => $amountDue,
                'added_by'   => Auth::id(),
            ]);
        }

        Player::addHistory(
            $playerId,
            'event',
            $eventId,
            date('Y-m-d', strtotime($event['start_datetime'])),
            date('H:i:s', strtotime($event['start_datetime'])),
            'Registered for event: ' . $event['title'],
            0
        );

        Notification::sendToPlayer(
            $playerId,
            'event_invite',
            'You have been added to an event',
            sprintf('You are registered for "%s" on %s.', $event['title'], date('M j, Y g:i A', strtotime($event['start_datetime']))),
            '/events/' . $eventId
        );

        return ['ok' => true, 'id' => $id];
    }

    public static function removeParticipant(int $participantId): bool
    {
        return Database::update('event_participants', ['status' => 'cancelled'], ['id' => $participantId]) > 0;
    }

    /** Participant list with their payment state (Fig. 3.8). */
    public static function participants(int $eventId): array
    {
        return Database::select(
            "SELECT ep.*, p.first_name, p.last_name, p.player_code, p.phone_number,
                    p.avatar_path, p.is_walk_in, s.level_name,
                    (SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay
                      WHERE pay.payable_type = 'event_participant'
                        AND pay.payable_id = ep.id
                        AND pay.payment_status IN ('paid','verified','partial')) AS amount_paid
               FROM event_participants ep
               JOIN players p ON p.id = ep.player_id
          LEFT JOIN skill_levels s ON s.id = p.skill_level_id
              WHERE ep.event_id = :e AND ep.status <> 'cancelled'
           ORDER BY ep.registered_at",
            ['e' => $eventId]
        );
    }

    public static function findParticipant(int $participantId): ?array
    {
        return Database::selectOne(
            'SELECT ep.*, p.first_name, p.last_name, e.title, e.fee
               FROM event_participants ep
               JOIN players p ON p.id = ep.player_id
               JOIN events e  ON e.id = ep.event_id
              WHERE ep.id = :id',
            ['id' => $participantId]
        );
    }

    public static function cancel(int $eventId, string $reason): bool
    {
        $event = self::find($eventId);
        if (!$event) {
            return false;
        }

        Database::update('events', ['status' => 'cancelled'], ['id' => $eventId]);

        foreach (self::participants($eventId) as $participant) {
            Notification::sendToPlayer(
                (int) $participant['player_id'],
                'event_reminder',
                'Event cancelled',
                sprintf('"%s" has been cancelled. %s', $event['title'], $reason)
            );
        }

        return true;
    }

    /** Fig. 3.9 -- Generate Event Report. */
    public static function report(array $filters = []): array
    {
        $sql = "SELECT e.id, e.event_code, e.title, e.event_type, e.status,
                       e.start_datetime, e.capacity, e.fee,
                       COUNT(ep.id) AS registered,
                       SUM(CASE WHEN ep.status = 'attended' THEN 1 ELSE 0 END) AS attended,
                       SUM(CASE WHEN ep.payment_status IN ('paid') THEN 1 ELSE 0 END) AS paid_count,
                       COALESCE(SUM(ep.amount_due), 0) AS expected_revenue,
                       (SELECT COALESCE(SUM(pay.amount), 0)
                          FROM payments pay
                          JOIN event_participants ep2 ON ep2.id = pay.payable_id
                         WHERE pay.payable_type = 'event_participant'
                           AND ep2.event_id = e.id
                           AND pay.payment_status IN ('paid','verified','partial')) AS collected_revenue
                  FROM events e
             LEFT JOIN event_participants ep ON ep.event_id = e.id AND ep.status <> 'cancelled'
                 WHERE 1 = 1";
        $params = [];

        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(e.start_datetime) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(e.start_datetime) <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND e.status = :status';
            $params['status'] = $filters['status'];
        }

        return Database::select($sql . ' GROUP BY e.id ORDER BY e.start_datetime DESC', $params);
    }
}
