<?php
// Dashboard: live numbers + a map of the five member slices and the unique feature.
require_once __DIR__ . '/includes/layout.php';

$stats = null; $triggers = 0;
try {
    $stats = $pdo->query("SELECT
        (SELECT COUNT(*) FROM users) AS users,
        (SELECT COUNT(*) FROM listings WHERE status = 'active') AS active_listings,
        (SELECT COUNT(*) FROM listings WHERE status = 'reserved') AS reserved,
        (SELECT COUNT(*) FROM meetup_schedules WHERE status IN ('pending','confirmed')) AS open_meetups,
        (SELECT COUNT(*) FROM reviews) AS reviews,
        (SELECT COUNT(*) FROM audit_logs) AS logs")->fetch();
    $triggers = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')->fetchColumn();
    $top = $pdo->query("SELECT u.full_name, s.trust_tier, s.rating_score FROM seller_trust_scores s JOIN users u ON u.id = s.seller_id ORDER BY s.rating_score DESC, s.total_reviews DESC LIMIT 3")->fetchAll();
} catch (Throwable $e) { $stats = null; $top = []; }

$slices = [
    ['IJAN.php',      'Users & Roles',   'Ijan',      'INNER JOIN',      'users · roles · user_roles',                         'Accounts, bcrypt passwords and role assignment. Report: verified sellers with their roles.'],
    ['ibib.php',      'Master Data',     'Ib-Ib',     'LEFT JOIN',       'categories · item_conditions · safe_meetup_zones',   'Reference data. Report: every safe zone with its meetup count, even zero.'],
    ['dinglasan.php', 'Item Catalog',    'Dinglasan', 'RIGHT JOIN',      'listings · listing_images · price_drop_alerts',      'Listings, photos and price-drop alerts. Report: every category with its items and alerts.'],
    ['espiritu.php',  'Chats & Meetups', 'Espiritu',  'INNER JOIN',      'chat_threads · chat_messages · meetup_schedules',    'Chat then schedule a meetup. Meetup status drives the listing status automatically.'],
    ['DELVO.php',     'Trust & Audit',   'Delvo',     'FULL OUTER JOIN', 'seller_trust_scores · reviews · audit_logs',         'Reviews compute seller tiers automatically. Report: trust scores vs audit activity.'],
];

render_header('BentaHub', 'home', 'A campus marketplace where trust is computed, not claimed. Pick a slice to see its CRUD screens and join report.');
?>
<?php if ($stats === null): ?>
  <div class="flash flash-error">The tables are not ready. Run <code>database/schema.sql</code>, the three <code>features_*.sql</code> files, then <code>seed.sql</code> (in that order) and reload.</div>
<?php else: ?>
  <?php if ($triggers < 10): ?><div class="flash flash-error">The <code>features_*.sql</code> files have not been loaded (found <?= $triggers ?> triggers, expected 12). Run them after schema.sql, then seed.sql again.</div><?php endif; ?>
  <div class="grid grid-stats">
    <div class="card stat"><b><?= (int)$stats['users'] ?></b><span>Users</span></div>
    <div class="card stat"><b><?= (int)$stats['active_listings'] ?></b><span>Active listings</span></div>
    <div class="card stat"><b><?= (int)$stats['reserved'] ?></b><span>Reserved items</span></div>
    <div class="card stat"><b><?= (int)$stats['open_meetups'] ?></b><span>Open meetups</span></div>
    <div class="card stat"><b><?= (int)$stats['reviews'] ?></b><span>Reviews</span></div>
    <div class="card stat"><b><?= (int)$stats['logs'] ?></b><span>Audit entries</span></div>
  </div>
<?php endif; ?>

<div class="grid grid-cards">
  <?php foreach ($slices as [$href, $name, $who, $join, $tables, $desc]): ?>
    <a class="card link-card" href="<?= e($href) ?>">
      <?= badge($join, 'green') ?>
      <h3><?= e($name) ?> <span class="muted">· <?= e($who) ?></span></h3>
      <p><?= e($desc) ?></p>
      <p style="margin-top:8px"><code><?= e($tables) ?></code></p>
    </a>
  <?php endforeach; ?>
</div>

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr));margin-top:18px">
  <section class="card">
    <h2 style="margin-bottom:6px">Unique feature: Deal Workflow + Trust Engine</h2>
    <p class="muted" style="margin-bottom:12px">Rules live inside MySQL (triggers, a function, procedures), so no screen can bypass them.</p>
    <ol class="steps">
      <li><span><strong>Confirm a meetup</strong> → the listing becomes <em>Reserved</em>; <strong>complete</strong> it → <em>Sold</em>; <strong>cancel</strong> → <em>Active</em> again.</span></li>
      <li><span><strong>Edge case:</strong> a second buyer cannot confirm a meetup for an item that is already reserved or sold.</span></li>
      <li><span><strong>Only completed meetups</strong> can be reviewed, and only by that deal's buyer, once.</span></li>
      <li><span>Every review <strong>recomputes the seller's average and tier</strong> (Bronze, Silver, Gold) and writes an audit entry when the tier changes.</span></li>
      <li><span><strong>Edge case:</strong> one 5-star review stays Bronze. A tier needs enough reviews, not only a high score.</span></li>
    </ol>
  </section>
  <section class="card">
    <h2 style="margin-bottom:10px">Top sellers right now</h2>
    <?php foreach ($top as $t): ?>
      <p style="display:flex;justify-content:space-between;padding:8px 0;border-top:1px solid var(--line)"><strong><?= e($t['full_name']) ?></strong><span><?= tier_badge($t['trust_tier']) ?> <span class="muted"><?= e($t['rating_score']) ?> / 5</span></span></p>
    <?php endforeach; if (!$top) echo '<p class="muted">No trust scores yet.</p>'; ?>
    <p class="muted" style="margin-top:12px">Demo logins use the password <code>password123</code>. The “Acting as” switch in the top bar decides who is recorded in the audit log.</p>
  </section>
</div>
<?php render_footer();
