<?php
/**
 * Main layout: public pages and the Customer/Player area.
 * Expects $content, $title.
 */
use App\Core\Auth;
use App\Core\Database;

$user      = Auth::user();
$player    = Auth::player();
$isStaff   = Auth::isStaff();
$unread    = 0;

if ($user) {
    $unread = (int) Database::scalar(
        'SELECT COUNT(*) FROM notifications WHERE user_id = :u AND status = :s',
        ['u' => $user['id'], 's' => 'unread'],
        0
    );
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="Reserve pickleball courts and the function hall at Mango Drive Cafe, and get matched with players at your level.">
<meta name="theme-color" content="#0f172a">
<title><?= e($title ?? 'Mango Drive Cafe Pickleball') ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script>
  // Set the theme before first paint so there is no flash of the wrong theme.
  (function () {
    try {
      var saved = localStorage.getItem('mdc-theme');
      var dark  = saved ? saved === 'dark'
                        : window.matchMedia('(prefers-color-scheme: dark)').matches;
      document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    } catch (e) {
      document.documentElement.setAttribute('data-theme', 'light');
    }
  })();
</script>
</head>
<body class="<?= $user && !$isStaff ? 'has-tabbar' : '' ?>">

<a class="skip-link" href="#main">Skip to main content</a>

<header class="topbar">
  <div class="container topbar-inner">
    <a href="<?= url($user ? ($isStaff ? '/admin' : '/dashboard') : '/') ?>" class="brand">
      <span class="brand-mark"><?= icon('paddle', 20) ?></span>
      <span class="brand-text">
        Mango Drive
        <span>Pickleball</span>
      </span>
    </a>

    <?php if ($user && !$isStaff): ?>
      <nav class="topnav" aria-label="Main">
        <a href="<?= url('/dashboard') ?>"    class="<?= nav_active('/dashboard') ?>">Home</a>
        <a href="<?= url('/availability') ?>" class="<?= nav_active('/availability') ?>">Availability</a>
        <a href="<?= url('/reserve') ?>"      class="<?= nav_active('/reserve') ?>">Reserve</a>
        <a href="<?= url('/matching') ?>"     class="<?= nav_active('/matching') ?>">Matching</a>
        <a href="<?= url('/reservations') ?>" class="<?= nav_active('/reservations') ?>">My Bookings</a>
        <a href="<?= url('/events') ?>"       class="<?= nav_active('/events') ?>">Events</a>
      </nav>
    <?php elseif (!$user): ?>
      <nav class="topnav" aria-label="Main">
        <a href="<?= url('/availability') ?>" class="<?= nav_active('/availability') ?>">Availability</a>
        <a href="<?= url('/events') ?>"       class="<?= nav_active('/events') ?>">Events</a>
        <a href="<?= url('/about') ?>"        class="<?= nav_active('/about') ?>">About</a>
      </nav>
    <?php endif; ?>

    <div class="topbar-actions">
      <button type="button" class="icon-btn" data-theme-toggle aria-label="Switch colour theme">
        <?= icon('sun', 18, 'theme-sun') ?>
      </button>

      <?php if ($user): ?>
        <a href="<?= url('/notifications') ?>" class="icon-btn" aria-label="Notifications<?= $unread ? " ($unread unread)" : '' ?>">
          <?= icon('bell', 18) ?>
          <?php if ($unread): ?><span class="dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
        </a>

        <div class="menu" data-menu>
          <button type="button" class="avatar" data-menu-trigger aria-haspopup="true" aria-label="Account menu">
            <?= e(initials($player['first_name'] ?? $user['username'], $player['last_name'] ?? '')) ?>
          </button>
          <div class="menu-panel" data-menu-panel>
            <div style="padding:9px 12px">
              <strong style="display:block"><?= e(trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '')) ?: $user['username']) ?></strong>
              <span class="small muted"><?= e($user['email']) ?></span>
            </div>
            <div class="menu-divider"></div>
            <?php if ($isStaff): ?>
              <a href="<?= url('/admin') ?>"><?= icon('chart', 17) ?> Back office</a>
            <?php else: ?>
              <a href="<?= url('/profile') ?>"><?= icon('user', 17) ?> My profile</a>
              <a href="<?= url('/reservations') ?>"><?= icon('ticket', 17) ?> My reservations</a>
              <a href="<?= url('/matching/results') ?>"><?= icon('shuffle', 17) ?> Match results</a>
            <?php endif; ?>
            <div class="menu-divider"></div>
            <form method="post" action="<?= url('/logout') ?>">
              <?= csrf_field() ?>
              <button type="submit"><?= icon('logout', 17) ?> Log out</button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= url('/login') ?>" class="btn btn-sm btn-outline" style="color:#fff;border-color:rgba(255,255,255,.35)">Log in</a>
        <a href="<?= url('/register') ?>" class="btn btn-sm btn-primary">Sign up</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main id="main" class="page">
  <div class="container">
    <?php if (!empty($flashes)): ?>
      <div class="alert-stack mb-2">
        <?php foreach ($flashes as $flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?>" role="status" data-flash>
            <?= icon($flash['type'] === 'success' ? 'check' : ($flash['type'] === 'error' ? 'warning' : 'info'), 18) ?>
            <span><?= e($flash['message']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?= $content ?>
  </div>
</main>

<?php if ($user && !$isStaff): ?>
  <nav class="tabbar" aria-label="Primary">
    <a href="<?= url('/dashboard') ?>" class="<?= nav_active('/dashboard') ?>">
      <span class="tab-icon"><?= icon('home', 19) ?></span> Home
    </a>
    <a href="<?= url('/availability') ?>" class="<?= nav_active('/availability') ?>">
      <span class="tab-icon"><?= icon('search', 19) ?></span> Explore
    </a>
    <a href="<?= url('/reserve') ?>" class="<?= nav_active('/reserve') ?>">
      <span class="tab-icon"><?= icon('plus', 19) ?></span> Reserve
    </a>
    <a href="<?= url('/reservations') ?>" class="<?= nav_active('/reservations') ?>">
      <span class="tab-icon"><?= icon('ticket', 19) ?></span> Bookings
    </a>
    <a href="<?= url('/profile') ?>" class="<?= nav_active('/profile') ?>">
      <span class="tab-icon"><?= icon('user', 19) ?></span> Profile
    </a>
  </nav>
<?php endif; ?>

<?php if (!$user): ?>
  <footer style="background:var(--brand-surface);color:rgba(255,255,255,.7);padding:28px 0;margin-top:40px">
    <div class="container">
      <div class="row-between">
        <div>
          <strong style="color:#fff">Mango Drive Cafe</strong>
          <p class="small" style="margin:.25rem 0 0">Pickleball courts &amp; function hall reservations.</p>
        </div>
        <p class="small" style="margin:0">&copy; <?= date('Y') ?> Mango Drive Cafe. All rights reserved.</p>
      </div>
    </div>
  </footer>
<?php endif; ?>

<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
