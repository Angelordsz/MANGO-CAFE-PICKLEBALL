<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;

/**
 * Use case A15 -- View System Logs. Administrator only.
 */
final class LogController extends Controller
{
    public function index(): void
    {
        $filters = [
            'action'  => (string) $this->request->query('action', ''),
            'user_id' => (string) $this->request->query('user_id', ''),
            'from'    => (string) $this->request->query('from', date('Y-m-d', strtotime('-7 days'))),
            'to'      => (string) $this->request->query('to', date('Y-m-d')),
            'q'       => (string) $this->request->query('q', ''),
        ];

        $sql = "SELECT l.*, u.username, u.role
                  FROM system_logs l
             LEFT JOIN users u ON u.id = l.user_id
                 WHERE DATE(l.created_at) BETWEEN :from AND :to";
        $params = ['from' => $filters['from'], 'to' => $filters['to']];

        if ($filters['action'] !== '') {
            $sql .= ' AND l.action LIKE :action';
            $params['action'] = $filters['action'] . '%';
        }
        if ($filters['user_id'] !== '') {
            $sql .= ' AND l.user_id = :uid';
            $params['uid'] = $filters['user_id'];
        }
        if ($filters['q'] !== '') {
            $sql .= ' AND l.description LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $logs = Database::select($sql . ' ORDER BY l.created_at DESC LIMIT 500', $params);

        if ($this->request->query('export') === 'csv') {
            Response::csv(
                'system-logs-' . $filters['from'] . '-to-' . $filters['to'] . '.csv',
                ['Timestamp', 'User', 'Role', 'Action', 'Entity', 'Entity ID', 'Description', 'IP'],
                array_map(static fn($l) => [
                    $l['created_at'], $l['username'] ?? 'system', $l['role'] ?? '',
                    $l['action'], $l['entity_type'], $l['entity_id'], $l['description'], $l['ip_address'],
                ], $logs)
            );
        }

        // Action prefixes, for the filter dropdown.
        $actions = Database::select(
            "SELECT DISTINCT SUBSTRING_INDEX(action, '.', 1) AS prefix
               FROM system_logs ORDER BY prefix"
        );

        $users = Database::select(
            "SELECT DISTINCT u.id, u.username
               FROM system_logs l JOIN users u ON u.id = l.user_id
           ORDER BY u.username"
        );

        $this->view('admin.logs', [
            'title'   => 'System logs · Back office',
            'logs'    => $logs,
            'filters' => $filters,
            'actions' => $actions,
            'users'   => $users,
        ], 'admin');
    }
}
