<?php
// DELVO - Trust & Audit slice: reviews (full CRUD), computed trust scores, read-only audit log + FULL OUTER JOIN report.
// Feature owner (Part B of the unique feature): every review automatically recomputes the seller's score and tier
// inside the database (triggers + fn_trust_tier in database/features_trust_engine.sql). The score is never typed by hand.
require_once __DIR__ . '/includes/layout.php';

$tabs   = ['trust' => 'Trust engine & reviews', 'audit' => 'Audit log'];
$tab    = $_GET['tab'] ?? post('tab', 'trust');
if (!isset($tabs[$tab])) $tab = 'trust';
$errors = [];
$edit   = null;

/* ---------- CREATE / UPDATE / DELETE reviews ---------- */
if (is_post()) {
    csrf_verify();
    $action = post('action');
    $id     = (int)post('id');

    if ($action === 'save_review') {
        $mid = (int)post('meetup_id'); $rating = (int)post('rating'); $comment = post('comment');
        if (!$id && !$mid)                    $errors[] = 'Choose the meetup you are reviewing.';
        if ($rating < 1 || $rating > 5)       $errors[] = 'Rating must be between 1 and 5.';
        if (mb_strlen($comment) > 255)        $errors[] = 'Comment is limited to 255 characters.';
        if (!$errors) {
            try {
                if ($id) {
                    $pdo->prepare('UPDATE reviews SET rating=?, comment=? WHERE id=?')->execute([$rating, $comment ?: null, $id]);
                    $st = $pdo->prepare('SELECT seller_id FROM reviews WHERE id=?');
                    $st->execute([$id]);
                    $sellerId = (int)$st->fetchColumn();
                } else {
                    // The buyer and the seller come from the meetup's chat; the database trigger double-checks them.
                    $st = $pdo->prepare('SELECT t.buyer_id, t.seller_id FROM meetup_schedules m JOIN chat_threads t ON t.id=m.thread_id WHERE m.id=?');
                    $st->execute([$mid]);
                    $deal = $st->fetch();
                    if (!$deal) throw new DomainException('Meetup not found.');
                    $pdo->prepare('INSERT INTO reviews (meetup_id, reviewer_id, seller_id, rating, comment) VALUES (?,?,?,?,?)')
                        ->execute([$mid, $deal['buyer_id'], $deal['seller_id'], $rating, $comment ?: null]);
                    $id = (int)$pdo->lastInsertId();
                    $sellerId = (int)$deal['seller_id'];
                }
                $st = $pdo->prepare('SELECT u.full_name, s.rating_score, s.total_reviews, s.trust_tier FROM seller_trust_scores s JOIN users u ON u.id=s.seller_id WHERE s.seller_id=?');
                $st->execute([$sellerId]);
                $sc = $st->fetch();
                audit('Saved Review', "Review #$id, $rating star(s)");
                flash('success', 'Review saved.' . ($sc ? " {$sc['full_name']} is now {$sc['trust_tier']} ({$sc['rating_score']} avg from {$sc['total_reviews']} review(s))." : ''));
                redirect(url(['tab' => 'trust']));
            } catch (DomainException $e) { $errors[] = $e->getMessage();
            } catch (Throwable $e) {
                $errors[] = ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) ? 'This meetup already has a review.' : db_error($e);
            }
        }
        $edit = ['id' => $id, 'meetup_id' => $mid, 'rating' => $rating, 'comment' => $comment];
    }
    if ($action === 'delete_review') {
        try { $pdo->prepare('DELETE FROM reviews WHERE id=?')->execute([$id]); audit('Deleted Review', "Review #$id"); flash('success', 'Review deleted and the seller score was recalculated.'); }
        catch (Throwable $e) { flash('error', db_error($e)); }
        redirect(url(['tab' => 'trust']));
    }
}

if (!$edit && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM reviews WHERE id=?'); $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$f = $edit ?? ['id' => 0, 'meetup_id' => '', 'rating' => 5, 'comment' => ''];

/* ---------- READ ---------- */
$meetups = $pdo->query("
    SELECT s.id, s.status, l.title, bu.full_name AS buyer, se.full_name AS seller,
           (SELECT COUNT(*) FROM reviews r WHERE r.meetup_id = s.id) AS reviewed
    FROM meetup_schedules s
    JOIN chat_threads t ON t.id = s.thread_id
    JOIN listings l     ON l.id = t.listing_id
    JOIN users bu       ON bu.id = t.buyer_id
    JOIN users se       ON se.id = t.seller_id
    ORDER BY s.id DESC")->fetchAll();
$reviews = $pdo->query("
    SELECT r.id, r.rating, r.comment, r.created_at, bu.full_name AS buyer, se.full_name AS seller, l.title
    FROM reviews r
    JOIN users bu ON bu.id = r.reviewer_id
    JOIN users se ON se.id = r.seller_id
    JOIN meetup_schedules s ON s.id = r.meetup_id
    JOIN chat_threads t ON t.id = s.thread_id
    JOIN listings l ON l.id = t.listing_id
    ORDER BY r.id DESC")->fetchAll();
$scores = $pdo->query("
    SELECT u.full_name, s.rating_score, s.total_reviews, s.trust_tier
    FROM seller_trust_scores s JOIN users u ON u.id = s.seller_id
    ORDER BY s.rating_score DESC, s.total_reviews DESC")->fetchAll();

$logSearch = trim((string)($_GET['q'] ?? ''));
$st = $pdo->prepare("
    SELECT a.id, a.action, a.details, a.created_at, u.full_name
    FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
    WHERE (? = '' OR a.action LIKE ? OR a.details LIKE ?)
    ORDER BY a.id DESC LIMIT 100");
$like = '%' . $logSearch . '%';
$st->execute([$logSearch, $like, $like]);
$logs = $st->fetchAll();

function next_tier_hint(array $s): string {
    $avg = (float)$s['rating_score']; $n = (int)$s['total_reviews'];
    if ($s['trust_tier'] === 'Gold') return 'Top tier';
    if ($s['trust_tier'] === 'Silver') return max(0, 4 - $n) > 0 ? 'Needs ' . (4 - $n) . ' more review(s) and avg ≥ 4.50 for Gold' : 'Needs avg ≥ 4.50 for Gold';
    return $n < 2 ? 'Needs ' . (2 - $n) . ' more review(s) and avg ≥ 4.00 for Silver' : 'Needs avg ≥ 4.00 for Silver';
}

/* ---------- JOIN REPORT: FULL OUTER JOIN (emulated: LEFT JOIN ∪ RIGHT JOIN) ---------- */
$reportSql = "
SELECT x.user_id, u.full_name, x.trust_tier, x.rating_score, x.action, x.logged_at,
       CASE WHEN x.rating_score IS NULL THEN 'Audit log only (no trust score)'
            WHEN x.action IS NULL       THEN 'Trust score only (no audit activity)'
            ELSE 'Matched' END AS match_type
FROM (
    SELECT s.seller_id AS user_id, s.trust_tier, s.rating_score, a.action, a.created_at AS logged_at
    FROM seller_trust_scores s
    LEFT JOIN audit_logs a  ON a.user_id = s.seller_id
  UNION
    SELECT a.user_id, s.trust_tier, s.rating_score, a.action, a.created_at
    FROM seller_trust_scores s
    RIGHT JOIN audit_logs a ON a.user_id = s.seller_id
) x
LEFT JOIN users u ON u.id = x.user_id
ORDER BY u.full_name, x.logged_at";
$report = $pdo->query($reportSql)->fetchAll();

render_header('Trust & Audit', 'trust', 'Reviews automatically drive seller trust tiers. Every important action is logged.', 'FULL OUTER JOIN');
render_tabs($tabs, $tab);
if ($tab === 'audit'): ?>
<section class="card">
  <div class="card-head"><h2>Audit log <span class="muted">(latest <?= count($logs) ?>)</span></h2>
    <form method="get" class="inline" style="display:flex;gap:8px"><input type="hidden" name="tab" value="audit"><input class="search" type="search" name="q" value="<?= e($logSearch) ?>" placeholder="Search action or details…"><button class="btn btn-sm">Search</button></form></div>
  <p class="muted" style="margin-bottom:12px">Read-only on purpose: audit entries are written by the app and by database triggers and can never be edited or deleted from the UI.</p>
  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>When</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody>
    <?php foreach ($logs as $l): ?>
      <tr><td><?= (int)$l['id'] ?></td><td><?= e(dt($l['created_at'])) ?></td><td><?= $l['full_name'] ? e($l['full_name']) : '<span class="null">system / deleted user</span>' ?></td>
          <td><?= badge($l['action'], strpos($l['action'], 'Trust') !== false ? 'gold' : (strpos($l['action'], 'Delet') !== false ? 'red' : 'green')) ?></td><td class="muted"><?= e($l['details']) ?></td></tr>
    <?php endforeach; if (!$logs) echo empty_row(5, 'No log entries match.'); ?>
  </tbody></table></div>
</section>
<?php else: ?>
<div class="grid grid-form">
  <section class="card">
    <div class="card-head"><h2><?= $f['id'] ? 'Edit review #' . (int)$f['id'] : 'Leave a review' ?></h2>
      <?php if ($f['id']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url()) ?>">Cancel</a><?php endif; ?></div>
    <?= errors_box($errors) ?>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="action" value="save_review"><input type="hidden" name="tab" value="trust"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <?php if (!$f['id']): ?>
      <div class="field"><label>Meetup <span class="hint">(only completed meetups can be reviewed)</span></label>
        <select name="meetup_id" required><option value="">Choose…</option>
        <?php foreach ($meetups as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= (int)$f['meetup_id'] === (int)$m['id'] ? 'selected' : '' ?>>#<?= (int)$m['id'] ?> · <?= e($m['title']) ?> · <?= e($m['buyer']) ?> → <?= e($m['seller']) ?> [<?= e($m['status']) ?><?= $m['reviewed'] ? ', reviewed' : '' ?>]</option>
        <?php endforeach; ?></select></div>
      <?php endif; ?>
      <div class="field"><label>Rating</label><select name="rating">
        <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= (int)$f['rating'] === $i ? 'selected' : '' ?>><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?> (<?= $i ?>)</option><?php endfor; ?></select></div>
      <div class="field"><label>Comment <span class="hint">(optional)</span></label><textarea name="comment" maxlength="255"><?= e($f['comment']) ?></textarea></div>
      <div class="form-actions"><button class="btn" type="submit"><?= $f['id'] ? 'Save changes' : 'Submit review' ?></button></div>
    </form>
    <details class="sql" open><summary>How tiers are computed</summary>
      <p class="muted" style="margin-top:8px"><strong>Gold</strong>: 4+ reviews and average ≥ 4.50<br><strong>Silver</strong>: 2+ reviews and average ≥ 4.00<br><strong>Bronze</strong>: everyone else (one 5-star review is not enough evidence).<br>Try it: pick a <em>pending</em> meetup and submit, and the database refuses.</p></details>
  </section>

  <div class="stack">
    <section class="card">
      <div class="card-head"><h2>Seller trust leaderboard</h2></div>
      <div class="table-wrap"><table>
        <thead><tr><th>Seller</th><th>Tier</th><th>Average</th><th>Reviews</th><th>Next step</th></tr></thead><tbody>
        <?php foreach ($scores as $s): ?>
          <tr><td><strong><?= e($s['full_name']) ?></strong></td><td><?= tier_badge($s['trust_tier']) ?></td>
              <td><div class="tier-bar" title="<?= e($s['rating_score']) ?> / 5"><i style="width:<?= (int)round(((float)$s['rating_score']) / 5 * 100) ?>%"></i></div><?= e($s['rating_score']) ?> / 5</td>
              <td><?= (int)$s['total_reviews'] ?></td><td class="muted"><?= e(next_tier_hint($s)) ?></td></tr>
        <?php endforeach; if (!$scores) echo empty_row(5, 'No scores yet. Scores appear after the first review.'); ?>
      </tbody></table></div>
    </section>

    <section class="card">
      <div class="card-head"><h2>Reviews <span class="muted">(<?= count($reviews) ?>)</span></h2></div>
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>Deal</th><th>Rating</th><th>Comment</th><th></th></tr></thead><tbody>
        <?php foreach ($reviews as $r): ?>
          <tr><td><?= (int)$r['id'] ?></td><td><strong><?= e($r['title']) ?></strong><br><span class="muted"><?= e($r['buyer']) ?> → <?= e($r['seller']) ?></span></td>
              <td><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></td><td class="muted"><?= e($r['comment'] ?: '—') ?></td>
              <td class="actions"><a class="btn btn-sm btn-ghost" href="<?= e(url(['edit' => $r['id']])) ?>">Edit</a>
                <?= delete_form('delete_review', ['id' => $r['id'], 'tab' => 'trust'], 'Delete this review? The seller score will be recalculated.') ?></td></tr>
        <?php endforeach; if (!$reviews) echo empty_row(5, 'No reviews yet.'); ?>
      </tbody></table></div>
    </section>

    <section class="card">
      <div class="card-head"><h2>Report: trust scores vs audit activity</h2><?= badge('FULL OUTER JOIN', 'green') ?></div>
      <p class="muted" style="margin-bottom:12px">MySQL has no FULL OUTER JOIN, so it is built as <code>LEFT JOIN</code> <strong>UNION</strong> <code>RIGHT JOIN</code> on the same two tables. Rows that exist on only one side are flagged.</p>
      <div class="table-wrap"><table>
        <thead><tr><th>User</th><th>Tier</th><th>Score</th><th>Action</th><th>Match</th></tr></thead><tbody>
        <?php foreach ($report as $r): ?>
          <tr><td><strong><?= $r['full_name'] ? e($r['full_name']) : '<span class="null">deleted user</span>' ?></strong></td>
              <td><?= $r['trust_tier'] !== null ? tier_badge($r['trust_tier']) : '<span class="null">—</span>' ?></td>
              <td><?= $r['rating_score'] !== null ? e($r['rating_score']) : '<span class="null">—</span>' ?></td>
              <td><?= $r['action'] !== null ? e($r['action']) : '<span class="null">no activity</span>' ?></td>
              <td><?= badge($r['match_type'], $r['match_type'] === 'Matched' ? 'green' : 'amber') ?></td></tr>
        <?php endforeach; if (!$report) echo empty_row(5); ?>
      </tbody></table></div>
      <?= sql_block($reportSql) ?>
    </section>
  </div>
</div>
<?php endif; render_footer();
