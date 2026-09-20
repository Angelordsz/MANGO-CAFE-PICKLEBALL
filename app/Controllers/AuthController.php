<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Models\Notification;
use App\Models\Player;
use App\Models\SkillLevel;

/**
 * Use cases C1 (Register / Create Account), C2 / A1 (Login).
 *
 * Registration creates BOTH a `users` row (the account) and a `players` row
 * (the domain record), because Figure 3.4 models Player as carrying the
 * personal details while authentication is shared with Admin.
 */
final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth.login', [
            'title' => 'Log in · Mango Drive Pickleball',
        ], 'auth');
    }

    public function login(): void
    {
        $identity = (string) $this->request->input('identity', '');
        $password = (string) $this->request->input('password', '');

        if ($identity === '' || $password === '') {
            Session::flash('error', 'Enter your username/email and password.');
            Session::flashInput($this->request->all());
            $this->back('/login');
        }

        $result = Auth::attempt($identity, $password, $this->request->ip());

        if (!$result['ok']) {
            Session::flash('error', $result['error']);
            Session::flashInput(['identity' => $identity]);
            $this->back('/login');
        }

        $this->log('auth.login', 'Signed in');

        // Send them where they were originally headed, if anywhere.
        $intended = Session::get('_intended');
        Session::forget('_intended');

        if ($intended) {
            Response::redirect($intended);
        }

        Response::redirectWith(
            Auth::isStaff() ? '/admin' : '/dashboard',
            'success',
            'Welcome back!'
        );
    }

    public function showRegister(): void
    {
        $this->view('auth.register', [
            'title'  => 'Create an account · Mango Drive Pickleball',
            'levels' => SkillLevel::all(),
        ], 'auth');
    }

    public function register(): void
    {
        $data = $this->validate([
            'first_name'     => 'required|max:60',
            'last_name'      => 'required|max:60',
            'username'       => 'required|alpha_dash|min:4|max:50|unique:users,username',
            'email'          => 'required|email|max:190|unique:users,email',
            'phone_number'   => 'required|phone',
            'password'       => 'required|min:8|confirmed',
            'gender'         => 'nullable|in:male,female,other,prefer_not_to_say',
            'birthdate'      => 'nullable|date|before:today',
            'skill_level_id' => 'required|integer|exists:skill_levels,id',
            'preferred_time' => 'nullable|in:morning,afternoon,evening,any',
            'terms'          => 'required',
        ], [
            'terms.required'    => 'You must accept the terms to create an account.',
            'password.min'      => 'Use at least 8 characters for your password.',
            'password.confirmed'=> 'The two passwords do not match.',
            'username.unique'   => 'That username is taken. Try another.',
            'email.unique'      => 'An account with that email already exists.',
        ]);

        $userId = Database::transaction(function () use ($data): int {
            $userId = Database::insert('users', [
                'role'          => 'customer',
                'username'      => $data['username'],
                'email'         => $data['email'],
                'password_hash' => Auth::hash($data['password']),
                'status'        => 'active',
            ]);

            Player::create([
                'user_id'        => $userId,
                'first_name'     => $data['first_name'],
                'last_name'      => $data['last_name'],
                'gender'         => $data['gender'] ?? 'prefer_not_to_say',
                'birthdate'      => $data['birthdate'] ?: null,
                'phone_number'   => $data['phone_number'],
                'email'          => $data['email'],
                'skill_level_id' => $data['skill_level_id'],
                'preferred_time' => $data['preferred_time'] ?? 'any',
                'is_walk_in'     => 0,
                'status'         => 'active',
            ]);

            return $userId;
        });

        Auth::login($userId);

        Notification::send(
            $userId,
            'system',
            'Welcome to Mango Drive Pickleball',
            'Your account is ready. Reserve a court, or sign up for player matching to get paired with players at your level.',
            '/dashboard'
        );

        $this->log('auth.register', 'Created a customer account', 'user', $userId);

        Response::redirectWith('/dashboard', 'success', 'Your account is ready. Welcome!');
    }

    public function logout(): void
    {
        $this->log('auth.logout', 'Signed out');
        Auth::logout();
        Response::redirectWith('/login', 'success', 'You have been logged out.');
    }

    // ---- Password reset ---------------------------------------------------
    // No mail gateway is in scope, so the reset link is shown on screen for
    // the administrator to pass on. See docs/SETUP.md.

    public function showForgot(): void
    {
        $this->view('auth.forgot', ['title' => 'Reset your password'], 'auth');
    }

    public function sendReset(): void
    {
        $email = (string) $this->request->input('email', '');
        $user  = Database::selectOne('SELECT * FROM users WHERE email = :e', ['e' => $email]);

        // Always report success so the form cannot be used to discover emails.
        if (!$user) {
            Response::redirectWith('/login', 'success', 'If that email is registered, a reset link has been created.');
        }

        $token = bin2hex(random_bytes(32));

        Database::insert('password_resets', [
            'user_id'    => $user['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $this->log('auth.reset_requested', 'Password reset requested', 'user', (int) $user['id']);

        Session::flash('info', 'Reset link (valid 1 hour): ' . url('/reset-password/' . $token));
        Response::redirect('/login');
    }

    public function showReset(string $token): void
    {
        $this->findResetOr404($token);
        $this->view('auth.reset', ['title' => 'Choose a new password', 'token' => $token], 'auth');
    }

    public function resetPassword(string $token): void
    {
        $reset = $this->findResetOr404($token);

        $data = $this->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        Database::transaction(static function () use ($reset, $data): void {
            Database::update('users', [
                'password_hash'   => Auth::hash($data['password']),
                'failed_attempts' => 0,
                'locked_until'    => null,
            ], ['id' => $reset['user_id']]);

            Database::update('password_resets', ['used_at' => date('Y-m-d H:i:s')], ['id' => $reset['id']]);
        });

        Response::redirectWith('/login', 'success', 'Password updated. You can log in now.');
    }

    private function findResetOr404(string $token): array
    {
        $reset = Database::selectOne(
            'SELECT * FROM password_resets
              WHERE token_hash = :t AND used_at IS NULL AND expires_at > NOW()',
            ['t' => hash('sha256', $token)]
        );

        if (!$reset) {
            Response::abort(404, 'That reset link is invalid or has expired.');
        }

        return $reset;
    }
}
