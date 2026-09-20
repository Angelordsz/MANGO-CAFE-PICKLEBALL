<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Notification;

/**
 * Use case A13 -- Send Notifications.
 *
 * In-app delivery only: the approved scope does not include an email or SMS
 * gateway, so a notification is a row the recipient sees when they log in.
 */
final class NotificationController extends Controller
{
    public function index(): void
    {
        $sent = Database::select(
            "SELECT n.*, u.username AS recipient, s.username AS sender
               FROM notifications n
          LEFT JOIN users u ON u.id = n.user_id
          LEFT JOIN users s ON s.id = n.sent_by
              WHERE n.sent_by IS NOT NULL
           ORDER BY n.created_at DESC
              LIMIT 100"
        );

        $stats = Database::selectOne(
            "SELECT COUNT(*) AS total,
                    SUM(status='unread') AS unread,
                    SUM(DATE(created_at)=CURDATE()) AS today
               FROM notifications"
        );

        $this->view('admin.notifications', [
            'title' => 'Send notifications · Back office',
            'sent'  => $sent,
            'stats' => $stats,
        ], 'admin');
    }

    public function send(): void
    {
        $data = $this->validate([
            'audience' => 'required|in:all,customer,staff,admin,single',
            'title'    => 'required|max:150',
            'message'  => 'required|max:2000',
            'link'     => 'nullable|max:255',
        ]);

        $link = $data['link'] ?: null;

        if ($data['audience'] === 'single') {
            $playerId = $this->request->integer('player_id');

            if ($playerId <= 0) {
                Response::redirectWith('/admin/notifications', 'error', 'Search for and select a player first.');
            }

            $sent = Notification::sendToPlayer($playerId, 'announcement', $data['title'], $data['message'], $link)
                ? 1 : 0;

            if ($sent === 0) {
                Response::redirectWith(
                    '/admin/notifications',
                    'warning',
                    'That player has no online account, so there is nowhere to deliver an in-app notification.'
                );
            }
        } else {
            $sent = Notification::broadcast($data['audience'], 'announcement', $data['title'], $data['message'], $link);
        }

        $this->log('notification.send', sprintf('Sent "%s" to %d recipients', $data['title'], $sent));

        Response::redirectWith(
            '/admin/notifications',
            'success',
            sprintf('Notification sent to %d %s.', $sent, pluralise($sent, 'recipient'))
        );
    }
}
