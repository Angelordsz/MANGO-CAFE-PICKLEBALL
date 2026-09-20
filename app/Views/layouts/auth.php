<?php
/**
 * Centred single-card layout for login / register / password reset.
 */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<title><?= e($title ?? 'Sign in') ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script>
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
<style>
  .auth-wrap {
    min-height: 100vh; min-height: 100dvh;
    display: grid; grid-template-columns: 1fr;
  }
  .auth-aside { display: none; }
  .auth-main {
    display: flex; flex-direction: column; justify-content: center;
    padding: 28px 16px calc(28px + env(safe-area-inset-bottom, 0px));
  }
  .auth-card { width: 100%; max-width: 420px; margin: 0 auto; }
  @media (min-width: 900px) {
    .auth-wrap { grid-template-columns: 1fr 1fr; }
    .auth-aside {
      display: flex; flex-direction: column; justify-content: space-between;
      padding: 40px; color: #fff;
      background: linear-gradient(150deg, var(--navy-900), var(--navy-700) 60%, var(--lime-700));
      position: relative; overflow: hidden;
    }
    .auth-aside::after {
      content: ""; position: absolute; right: -90px; bottom: -90px;
      width: 320px; height: 320px; border-radius: 50%;
      background: radial-gradient(circle, rgba(163,230,53,.28), transparent 70%);
    }
    .auth-aside h2 { color: #fff; font-size: 2rem; max-width: 12ch; }
    .auth-aside p { color: rgba(255,255,255,.8); max-width: 42ch; }
  }
  .auth-feature { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 16px; position: relative; z-index: 1; }
  .auth-feature-icon {
    width: 36px; height: 36px; border-radius: 10px; flex: none;
    background: rgba(163,230,53,.18); color: var(--lime-300);
    display: grid; place-items: center;
  }
</style>
</head>
<body>

<div class="auth-wrap">
  <aside class="auth-aside">
    <a href="<?= url('/') ?>" class="brand" style="color:#fff">
      <span class="brand-mark"><?= icon('paddle', 20) ?></span>
      <span class="brand-text">Mango Drive<span>Pickleball</span></span>
    </a>

    <div style="position:relative;z-index:1">
      <h2>Book a court. Find a game.</h2>
      <p class="mb-3">Reserve pickleball courts and the function hall online, and get matched with players at your skill level.</p>

      <div class="auth-feature">
        <span class="auth-feature-icon"><?= icon('calendar', 18) ?></span>
        <div>
          <strong>Real-time availability</strong>
          <p class="small" style="margin:0">See every open slot before you book. No more double bookings.</p>
        </div>
      </div>
      <div class="auth-feature">
        <span class="auth-feature-icon"><?= icon('shuffle', 18) ?></span>
        <div>
          <strong>Automatic player matching</strong>
          <p class="small" style="margin:0">Pick a date and your skill level — we pair you with a partner and opponents.</p>
        </div>
      </div>
      <div class="auth-feature">
        <span class="auth-feature-icon"><?= icon('hall', 18) ?></span>
        <div>
          <strong>Function hall booking</strong>
          <p class="small" style="margin:0">Reserve the hall for parties, meetings and gatherings.</p>
        </div>
      </div>
    </div>

    <p class="tiny" style="position:relative;z-index:1;margin:0">&copy; <?= date('Y') ?> Mango Drive Cafe</p>
  </aside>

  <main class="auth-main" id="main">
    <div class="auth-card">
      <a href="<?= url('/') ?>" class="brand mb-3" style="color:var(--text)">
        <span class="brand-mark"><?= icon('paddle', 20) ?></span>
        <span class="brand-text">Mango Drive<span style="color:var(--lime-600)">Pickleball</span></span>
      </a>

      <?php if (!empty($flashes)): ?>
        <div class="mb-2">
          <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> mb-1" role="status">
              <?= icon($flash['type'] === 'success' ? 'check' : ($flash['type'] === 'error' ? 'warning' : 'info'), 18) ?>
              <span><?= e($flash['message']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?= $content ?>
    </div>
  </main>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
