<?php
require_once __DIR__ . '/bootstrap.php';

function render_header(string $title, string $key, string $subtitle = '', string $joinPill = ''): void {
    global $pdo;
    $users = $pdo->query('SELECT id, full_name FROM users ORDER BY id')->fetchAll();
    $ids = array_column($users, 'id');
    if (!in_array(current_user_id(), array_map('intval', $ids), true)) {
        $_SESSION['acting_user'] = $users ? (int)$users[0]['id'] : null;
    }
    $nav = [
        'home'   => ['index.php',      'Dashboard',        ''],
        'ijan'   => ['IJAN.php',       'Users & Roles',    'Ijan'],
        'ibib'   => ['ibib.php',       'Master Data',      'Ib-Ib'],
        'catalog'=> ['dinglasan.php',  'Item Catalog',     'Dinglasan'],
        'chat'   => ['espiritu.php',   'Chats & Meetups',  'Espiritu'],
        'trust'  => ['DELVO.php',      'Trust & Audit',    'Delvo'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · BentaHub</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/app.js" defer></script>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="index.php"><span class="brand-mark">B</span> BentaHub</a>
    <nav class="tabs" aria-label="Main">
      <?php foreach ($nav as $k => [$href, $label, $who]): ?>
        <a href="<?= e($href) ?>" class="<?= $k === $key ? 'active' : '' ?>"><?= e($label) ?><?php if ($who): ?><small><?= e($who) ?></small><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <form method="post" class="acting" title="Audit entries are recorded under this user">
      <?= csrf_field() ?>
      <label for="acting">Acting as</label>
      <select id="acting" name="switch_user" onchange="this.form.submit()">
        <?php foreach ($users as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === current_user_id() ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</header>
<main class="page">
  <div class="page-head">
    <div>
      <h1><?= e($title) ?></h1>
      <?php if ($subtitle): ?><p class="muted"><?= e($subtitle) ?></p><?php endif; ?>
    </div>
    <?php if ($joinPill): ?><span class="join-pill"><?= e($joinPill) ?></span><?php endif; ?>
  </div>
  <?php
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        echo '<div class="flash flash-' . e($type) . '" role="status">' . e($msg) . '</div>';
    }
    unset($_SESSION['flash']);
}

function render_footer(): void { ?>
</main>
<footer class="foot">BentaHub · DBMS 1 final project · NORSU BSIT</footer>
</body>
</html>
<?php }

// Small helper: tab links (?tab=...)
function render_tabs(array $tabs, string $current): void {
    echo '<div class="subtabs">';
    foreach ($tabs as $k => $label) {
        echo '<a class="' . ($k === $current ? 'active' : '') . '" href="' . e(url(['tab' => $k])) . '">' . e($label) . '</a>';
    }
    echo '</div>';
}
