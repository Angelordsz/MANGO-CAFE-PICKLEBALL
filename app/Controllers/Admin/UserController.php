<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Player;

/**
 * Use case A3 -- Manage Users / Customers. Administrator only.
 */
final class UserController extends Controller
{
    public function index(): void
    {
        $role   = (string) $this->request->query('role', '');
        $q      = (string) $this->request->query('q', '');

        $sql = "SELECT u.*,
                       p.id AS player_id, p.first_name, p.last_name, p.player_code
                  FROM users u
             LEFT JOIN players p ON p.user_id = u.id
                 WHERE 1 = 1";
        $params = [];

        if ($role !== '') {
            $sql .= ' AND u.role = :role';
            $params['role'] = $role;
        }
        if ($q !== '') {
            $sql .= " AND (u.username LIKE :q OR u.email LIKE :q2
                        OR CONCAT(p.first_name,' ',p.last_name) LIKE :q3)";
            $like = '%' . $q . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like];
        }

        $this->view('admin.users', [
            'title'   => 'User accounts · Back office',
            'users'   => Database::select($sql . ' ORDER BY u.created_at DESC LIMIT 200', $params),
            'role'    => $role,
            'q'       => $q,
            'counts'  => Database::selectOne(
                "SELECT SUM(role='admin') AS admins, SUM(role='staff') AS staff,
                        SUM(role='customer') AS customers, SUM(status='suspended') AS suspended
                   FROM users"
            ),
        ], 'admin');
    }

    public function create(): void
    {
        $this->view('admin.user_form', ['title' => 'New user account', 'user' => null], 'admin');
    }

    public function store(): void
    {
        $data = $this->validate([
            'username' => 'required|alpha_dash|min:4|max:50|unique:users,username',
            'email'    => 'required|email|max:190|unique:users,email',
            'role'     => 'required|in:admin,staff,customer',
            'password' => 'required|min:8|confirmed',
        ]);

        $userId = Database::insert('users', [
            'username'      => $data['username'],
            'email'         => $data['email'],
            'role'          => $data['role'],
            'password_hash' => Auth::hash($data['password']),
            'status'        => 'active',
            'must_change_password' => 1,
        ]);

        // A customer account is useless without a player record.
        if ($data['role'] === 'customer') {
            Player::create([
                'user_id'    => $userId,
                'first_name' => (string) ($this->request->input('first_name') ?: $data['username']),
                'last_name'  => (string) ($this->request->input('last_name') ?: ''),
                'email'      => $data['email'],
                'status'     => 'active',
            ]);
        }

        $this->log('user.create', "Created {$data['role']} account {$data['username']}", 'user', $userId);

        Response::redirectWith('/admin/users', 'success', 'Account created. The user must change the password at first login.');
    }

    public function edit(string $id): void
    {
        $user = $this->findOr404(
            Database::selectOne('SELECT * FROM users WHERE id = :id', ['id' => $id]),
            'User not found.'
        );

        $this->view('admin.user_form', ['title' => 'Edit ' . $user['username'], 'user' => $user], 'admin');
    }

    public function update(string $id): void
    {
        $user = $this->findOr404(
            Database::selectOne('SELECT * FROM users WHERE id = :id', ['id' => $id]),
            'User not found.'
        );

        $data = $this->validate([
            'username' => 'required|alpha_dash|min:4|max:50|unique:users,username,' . $id,
            'email'    => 'required|email|max:190|unique:users,email,' . $id,
            'role'     => 'required|in:admin,staff,customer',
        ]);

        // Never let the last administrator demote themselves out of existence.
        if ($user['role'] === 'admin' && $data['role'] !== 'admin' && $this->adminCount() <= 1) {
            Response::redirectWith('/admin/users', 'error', 'This is the only administrator account — its role cannot be changed.');
        }

        Database::update('users', $data, ['id' => $id]);

        $this->log('user.update', "Updated account {$data['username']}", 'user', (int) $id);

        Response::redirectWith('/admin/users', 'success', 'Account updated.');
    }

    public function toggleStatus(string $id): void
    {
        $user = $this->findOr404(
            Database::selectOne('SELECT * FROM users WHERE id = :id', ['id' => $id]),
            'User not found.'
        );

        if ((int) $id === (int) Auth::id()) {
            Response::redirectWith('/admin/users', 'error', 'You cannot suspend your own account.');
        }

        if ($user['role'] === 'admin' && $user['status'] === 'active' && $this->adminCount() <= 1) {
            Response::redirectWith('/admin/users', 'error', 'This is the only active administrator account.');
        }

        $next = $user['status'] === 'active' ? 'suspended' : 'active';

        Database::update('users', ['status' => $next, 'failed_attempts' => 0, 'locked_until' => null], ['id' => $id]);

        $this->log('user.status', "Set {$user['username']} to {$next}", 'user', (int) $id);

        Response::redirectWith('/admin/users', 'success', "Account {$next}.");
    }

    /** Issue a temporary password the admin reads out to the user. */
    public function resetPassword(string $id): void
    {
        $user = $this->findOr404(
            Database::selectOne('SELECT * FROM users WHERE id = :id', ['id' => $id]),
            'User not found.'
        );

        $temp = 'MDC' . random_int(100000, 999999);

        Database::update('users', [
            'password_hash'        => Auth::hash($temp),
            'must_change_password' => 1,
            'failed_attempts'      => 0,
            'locked_until'         => null,
        ], ['id' => $id]);

        $this->log('user.reset_password', "Reset password for {$user['username']}", 'user', (int) $id);

        Response::redirectWith(
            '/admin/users',
            'info',
            "Temporary password for {$user['username']}: {$temp} — give this to them directly."
        );
    }

    private function adminCount(): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'",
            [],
            0
        );
    }
}
