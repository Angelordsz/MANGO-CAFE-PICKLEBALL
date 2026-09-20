<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Models\MatchResult;
use App\Models\Player;
use App\Models\SkillLevel;

/**
 * Use case C3 -- Manage Profile.
 */
final class ProfileController extends Controller
{
    public function edit(): void
    {
        $player = Auth::player();
        $user   = Auth::user();

        $this->view('customer.profile', [
            'title'   => 'My profile',
            'player'  => $player,
            'user'    => $user,
            'levels'  => SkillLevel::all(),
            'summary' => $player ? Player::summary((int) $player['id']) : [],
            'history' => $player ? Player::history((int) $player['id'], 10) : [],
            // ENHANCEMENT -- win/loss counters, see docs/ENHANCEMENTS.md
            'stats'   => $player ? MatchResult::statsFor((int) $player['id']) : [],
            'showStats' => MatchResult::statsEnabled(),
        ]);
    }

    public function update(): void
    {
        $user   = Auth::user();
        $player = Auth::player();

        $data = $this->validate([
            'first_name'     => 'required|max:60',
            'last_name'      => 'required|max:60',
            'email'          => 'required|email|max:190|unique:users,email,' . $user['id'],
            'phone_number'   => 'required|phone',
            'gender'         => 'nullable|in:male,female,other,prefer_not_to_say',
            'birthdate'      => 'nullable|date|before:today',
            'address'        => 'nullable|max:255',
            'skill_level_id' => 'required|integer|exists:skill_levels,id',
            'preferred_time' => 'nullable|in:morning,afternoon,evening,any',
        ]);

        $preferredDays = implode(',', array_filter(
            array_map('intval', $this->request->array('preferred_days')),
            static fn($d) => $d >= 0 && $d <= 6
        ));

        Database::transaction(function () use ($data, $user, $player, $preferredDays): void {
            Database::update('users', ['email' => $data['email']], ['id' => $user['id']]);

            $update = [
                'first_name'     => $data['first_name'],
                'last_name'      => $data['last_name'],
                'email'          => $data['email'],
                'phone_number'   => $data['phone_number'],
                'gender'         => $data['gender'] ?? 'prefer_not_to_say',
                'birthdate'      => $data['birthdate'] ?: null,
                'address'        => $data['address'] ?: null,
                'skill_level_id' => $data['skill_level_id'],
                'preferred_time' => $data['preferred_time'] ?? 'any',
                'preferred_days' => $preferredDays ?: null,
            ];

            if (!empty($data['birthdate'])) {
                $update['age'] = Player::ageFromBirthdate($data['birthdate']);
            }

            if ($player) {
                Database::update('players', $update, ['id' => $player['id']]);
            } else {
                // Account exists but the player record does not (e.g. an admin
                // created the login). Create it now.
                Player::create(array_merge($update, ['user_id' => $user['id'], 'status' => 'active']));
            }
        });

        $this->log('profile.update', 'Updated profile');

        Response::redirectWith('/profile', 'success', 'Profile updated.');
    }

    public function updatePassword(): void
    {
        $user = Auth::user();

        $current = (string) $this->request->input('current_password', '');
        $new     = (string) $this->request->input('password', '');
        $confirm = (string) $this->request->input('password_confirmation', '');

        if (!password_verify($current, $user['password_hash'])) {
            Response::redirectWith('/profile', 'error', 'Your current password is incorrect.');
        }

        if (strlen($new) < 8) {
            Response::redirectWith('/profile', 'error', 'The new password must be at least 8 characters.');
        }

        if ($new !== $confirm) {
            Response::redirectWith('/profile', 'error', 'The two new passwords do not match.');
        }

        Database::update('users', ['password_hash' => Auth::hash($new)], ['id' => $user['id']]);

        $this->log('profile.password', 'Changed password');

        Response::redirectWith('/profile', 'success', 'Password changed.');
    }

    public function updateAvatar(): void
    {
        $player = Auth::player();
        $file   = $this->request->file('avatar');

        if (!$player) {
            Response::redirectWith('/profile', 'error', 'Complete your profile first.');
        }

        if (!$file) {
            Response::redirectWith('/profile', 'error', 'Choose an image to upload.');
        }

        $path = $this->storeImage($file, 'avatars');

        if ($path === null) {
            Response::redirectWith('/profile', 'error', 'Upload a JPG, PNG or WEBP image under 2 MB.');
        }

        Database::update('players', ['avatar_path' => $path], ['id' => $player['id']]);

        Response::redirectWith('/profile', 'success', 'Photo updated.');
    }

    /**
     * Validate and store an uploaded image. Returns the path relative to
     * public/uploads, or null when the file is rejected.
     */
    private function storeImage(array $file, string $folder): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) {
            return null;
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return null;
        }

        $allowed = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];

        if (!isset($allowed[$info[2]])) {
            return null;
        }

        $name   = $folder . '/' . bin2hex(random_bytes(12)) . '.' . $allowed[$info[2]];
        $target = PUBLIC_PATH . '/uploads/' . $name;

        if (!is_dir(dirname($target))) {
            @mkdir(dirname($target), 0775, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return null;
        }

        return $name;
    }
}
