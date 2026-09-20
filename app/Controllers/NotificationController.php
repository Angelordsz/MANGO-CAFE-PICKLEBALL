<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Notification;

/**
 * Use case C14 -- Receive Notifications.
 */
final class NotificationController extends Controller
{
    public function index(): void
    {
        $this->view('customer.notifications', [
            'title'         => 'Notifications',
            'notifications' => Notification::forUser((int) Auth::id()),
            'unread'        => Notification::unreadCount((int) Auth::id()),
        ]);
    }

    public function read(string $id): void
    {
        Notification::markRead((int) $id, (int) Auth::id());
        $this->back('/notifications');
    }

    public function readAll(): void
    {
        $count = Notification::markAllRead((int) Auth::id());
        Response::redirectWith('/notifications', 'success', "Marked {$count} as read.");
    }
}
