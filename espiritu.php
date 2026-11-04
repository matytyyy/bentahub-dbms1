<?php
// ESPIRITU - Inquiries & Chat slice: chat threads, messages, meetup schedules (full CRUD) + INNER JOIN report.
// Feature owner (Part A of the unique feature): meetup status automatically drives the listing status (see database/features_deal_workflow.sql).
require_once __DIR__ . '/includes/layout.php';

$tabs     = ['threads' => 'Chats', 'meetups' => 'Meetup schedules'];
$tab      = $_GET['tab'] ?? post('tab', 'threads');
if (!isset($tabs[$tab])) $tab = 'threads';
$statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
$errors   = [];
$edit     = null;

function listing_status_of_thread(PDO $pdo, int $threadId): string {
    $st = $pdo->prepare('SELECT l.status FROM chat_threads t JOIN listings l ON l.id = t.listing_id WHERE t.id = ?');
    $st->execute([$threadId]);
    return (string)$st->fetchColumn();
}
function parse_when(string $raw): ?string {
    $ts = strtotime(str_replace('T', ' ', $raw));
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

/* ---------- CREATE / UPDATE / DELETE ---------- */
if (is_post()) {
    csrf_verify();
    $action = post('action');
    $id     = (int)post('id');

    if ($action === 'start_thread') {
        $lid = (int)post('listing_id'); $buyer = (int)post('buyer_id');
        $st = $pdo->prepare('SELECT seller_id FROM listings WHERE id=?'); $st->execute([$lid]);
        $seller = $st->fetchColumn();
        if (!$lid || !$buyer || $seller === false) {
            $errors[] = 'Choose a listing and a buyer.';
        } else {
            try {
                $pdo->prepare('INSERT INTO chat_threads (listing_id, buyer_id, seller_id) VALUES (?,?,?)')->execute([$lid, $buyer, (int)$seller]);
                $tid = (int)$pdo->lastInsertId();
                audit('Started Chat', "Thread #$tid on listing #$lid");
                flash('success', 'Chat started.');
                redirect(url(['tab' => 'threads', 'thread' => $tid]));
            } catch (Throwable $e) {
                $errors[] = ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) ? 'This buyer already has a chat on that listing.' : db_error($e);
            }
        }
    }
    if ($action === 'delete_thread') {
        try { $pdo->prepare('DELETE FROM chat_threads WHERE id=?')->execute([$id]); audit('Deleted Chat', "Thread #$id"); flash('success', 'Chat deleted with its messages and meetups. Re-save a meetup to refresh listing status if needed.'); }
        catch (Throwable $e) { flash('error', db_error($e)); }
        redirect(url(['tab' => 'threads']));
    }

    if ($action === 'send_message') {
        $tid = (int)post('thread_id'); $sender = (int)post('sender_id'); $body = post('message_body');
        $st = $pdo->prepare('SELECT buyer_id, seller_id FROM chat_threads WHERE id=?'); $st->execute([$tid]);
        $th = $st->fetch();
        if (!$th || !in_array($sender, [(int)$th['buyer_id'], (int)$th['seller_id']], true)) flash('error', 'Only the buyer or the seller can write in this chat.');
        elseif ($body === '' || mb_strlen($body) > 1000) flash('error', 'A message must be 1 to 1000 characters.');
        else {
            try { $pdo->prepare('INSERT INTO chat_messages (thread_id, sender_id, message_body) VALUES (?,?,?)')->execute([$tid, $sender, $body]); }
            catch (Throwable $e) { flash('error', db_error($e)); }
        }
        redirect(url(['tab' => 'threads', 'thread' => $tid]));
    }
    if ($action === 'delete_message') {
        $tid = (int)post('thread_id');
        try { $pdo->prepare('DELETE FROM chat_messages WHERE id=? AND thread_id=?')->execute([$id, $tid]); flash('success', 'Message deleted.'); }
        catch (Throwable $e) { flash('error', db_error($e)); }
        redirect(url(['tab' => 'threads', 'thread' => $tid]));
    }

    if ($action === 'save_meetup') {
        $tid = (int)post('thread_id'); $zid = (int)post('zone_id'); $when = parse_when(post('scheduled_time')); $status = post('status', 'pending');
        if (!$tid || !$zid)                         $errors[] = 'Choose a chat and a safe zone.';
        if ($when === null)                         $errors[] = 'Enter a valid date and time.';
        if (!in_array($status, $statuses, true))    $errors[] = 'Invalid status.';
        if (!$errors) {
            try {
                if ($id) {
                    $pdo->prepare('UPDATE meetup_schedules SET thread_id=?, zone_id=?, scheduled_time=?, status=? WHERE id=?')->execute([$tid, $zid, $when, $status, $id]);
                } else {
                    $pdo->prepare('INSERT INTO meetup_schedules (thread_id, zone_id, scheduled_time, status) VALUES (?,?,?,?)')->execute([$tid, $zid, $when, $status]);
                    $id = (int)$pdo->lastInsertId();
                }
                audit('Saved Meetup', "Meetup #$id ($status)");
                flash('success', 'Meetup saved. Listing is now: ' . ucfirst(listing_status_of_thread($pdo, $tid)) . '.');
                redirect(url(['tab' => 'meetups']));
            } catch (Throwable $e) { $errors[] = db_error($e); }
        }
        $edit = ['id' => $id, 'thread_id' => $tid, 'zone_id' => $zid, 'scheduled_time' => $when ?? '', 'status' => $status];
    }
    if ($action === 'set_meetup_status') {
        $status = post('status');
        if (in_array($status, $statuses, true)) {
            try {
                $st = $pdo->prepare('SELECT thread_id FROM meetup_schedules WHERE id=?'); $st->execute([$id]);
                $tid = (int)$st->fetchColumn();
                $pdo->prepare('UPDATE meetup_schedules SET status=? WHERE id=?')->execute([$status, $id]);
                audit('Meetup ' . ucfirst($status), "Meetup #$id");
                flash('success', "Meetup is now $status. Listing is now: " . ucfirst(listing_status_of_thread($pdo, $tid)) . '.');
            } catch (Throwable $e) { flash('error', db_error($e)); }
        }
        redirect(url(['tab' => 'meetups']));
    }
    if ($action === 'delete_meetup') {
        try { $pdo->prepare('DELETE FROM meetup_schedules WHERE id=?')->execute([$id]); audit('Deleted Meetup', "Meetup #$id"); flash('success', 'Meetup deleted.'); }
        catch (Throwable $e) { flash('error', db_error($e)); }
        redirect(url(['tab' => 'meetups']));
    }
}

/* ---------- load meetup into form (?edit=ID) ---------- */
if (!$edit && $tab === 'meetups' && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM meetup_schedules WHERE id=?'); $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$m = $edit ?? ['id' => 0, 'thread_id' => '', 'zone_id' => '', 'scheduled_time' => '', 'status' => 'pending'];
$mWhen = $m['scheduled_time'] ? date('Y-m-d\TH:i', strtotime((string)$m['scheduled_time'])) : '';

/* ---------- READ ---------- */
$users    = $pdo->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll();
$listings = $pdo->query("SELECT id, title, status FROM listings WHERE status IN ('active','reserved') ORDER BY title")->fetchAll();
$zones    = $pdo->query('SELECT id, zone_name FROM safe_meetup_zones ORDER BY zone_name')->fetchAll();
$threads  = $pdo->query("
    SELECT t.id, l.title, bu.full_name AS buyer, se.full_name AS seller,
           (SELECT COUNT(*) FROM chat_messages cm WHERE cm.thread_id = t.id)    AS msgs,
           (SELECT COUNT(*) FROM meetup_schedules ms WHERE ms.thread_id = t.id) AS meetups
    FROM chat_threads t
    JOIN listings l ON l.id = t.listing_id
    JOIN users bu   ON bu.id = t.buyer_id
    JOIN users se   ON se.id = t.seller_id
    ORDER BY t.id DESC")->fetchAll();
$open = null; $messages = [];
if ($tab === 'threads' && isset($_GET['thread'])) {
    $st = $pdo->prepare("SELECT t.id, t.buyer_id, t.seller_id, l.title, bu.full_name AS buyer, se.full_name AS seller
                         FROM chat_threads t JOIN listings l ON l.id=t.listing_id JOIN users bu ON bu.id=t.buyer_id JOIN users se ON se.id=t.seller_id WHERE t.id=?");
    $st->execute([(int)$_GET['thread']]);
    $open = $st->fetch() ?: null;
    if ($open) {
        $st = $pdo->prepare('SELECT cm.id, cm.sender_id, cm.message_body, cm.sent_at, u.full_name FROM chat_messages cm JOIN users u ON u.id=cm.sender_id WHERE cm.thread_id=? ORDER BY cm.id');
        $st->execute([$open['id']]);
        $messages = $st->fetchAll();
    }
}
$meetups = $pdo->query("
    SELECT s.id, s.scheduled_time, s.status, z.zone_name, l.title, bu.full_name AS buyer, se.full_name AS seller, l.status AS listing_status
    FROM meetup_schedules s
    JOIN safe_meetup_zones z ON z.id = s.zone_id
    JOIN chat_threads t      ON t.id = s.thread_id
    JOIN listings l          ON l.id = t.listing_id
    JOIN users bu            ON bu.id = t.buyer_id
    JOIN users se            ON se.id = t.seller_id
    ORDER BY s.scheduled_time DESC")->fetchAll();
$threadChoices = $pdo->query("SELECT t.id, l.title, bu.full_name AS buyer FROM chat_threads t JOIN listings l ON l.id=t.listing_id JOIN users bu ON bu.id=t.buyer_id ORDER BY t.id DESC")->fetchAll();

/* ---------- JOIN REPORT: INNER JOIN ---------- */
$reportSql = "
SELECT t.id AS thread_id, l.title AS listing_title, l.status AS listing_status,
       bu.full_name AS buyer, se.full_name AS seller,
       m.message_body AS last_message, s.scheduled_time, s.status AS meetup_status, z.zone_name
FROM chat_threads t
INNER JOIN listings l          ON t.listing_id = l.id
INNER JOIN users bu            ON bu.id = t.buyer_id
INNER JOIN users se            ON se.id = t.seller_id
INNER JOIN chat_messages m     ON m.id = (SELECT MAX(cm.id) FROM chat_messages cm WHERE cm.thread_id = t.id)
INNER JOIN meetup_schedules s  ON t.id = s.thread_id
INNER JOIN safe_meetup_zones z ON z.id = s.zone_id
ORDER BY s.scheduled_time ASC";
$report = $pdo->query($reportSql)->fetchAll();

render_header('Chats & Meetups', 'chat', 'Buyers and sellers chat, then agree on a meetup at a safe zone.', 'INNER JOIN');
render_tabs($tabs, $tab);
?>
<div class="grid grid-form">
  <section class="card">
  <?php if ($tab === 'threads'): ?>
    <div class="card-head"><h2>Start a chat</h2></div>
    <?= errors_box($errors) ?>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="action" value="start_thread"><input type="hidden" name="tab" value="threads">
      <div class="field"><label>Listing</label><select name="listing_id" required><option value="">Choose…</option>
        <?php foreach ($listings as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['title']) ?> (<?= e($l['status']) ?>)</option><?php endforeach; ?></select></div>
      <div class="field"><label>Buyer <span class="hint">(the seller is taken from the listing)</span></label><select name="buyer_id" required><option value="">Choose…</option>
        <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
      <div class="form-actions"><button class="btn" type="submit">Start chat</button></div>
    </form>
  <?php else: ?>
    <div class="card-head"><h2><?= $m['id'] ? 'Edit meetup #' . (int)$m['id'] : 'Schedule a meetup' ?></h2>
      <?php if ($m['id']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url(['tab' => 'meetups'])) ?>">Cancel</a><?php endif; ?></div>
    <?= errors_box($errors) ?>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="action" value="save_meetup"><input type="hidden" name="tab" value="meetups"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <div class="field"><label>Chat</label><select name="thread_id" required><option value="">Choose…</option>
        <?php foreach ($threadChoices as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$m['thread_id'] === (int)$t['id'] ? 'selected' : '' ?>>#<?= (int)$t['id'] ?> · <?= e($t['title']) ?> · <?= e($t['buyer']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Safe zone</label><select name="zone_id" required><option value="">Choose…</option>
        <?php foreach ($zones as $z): ?><option value="<?= (int)$z['id'] ?>" <?= (int)$m['zone_id'] === (int)$z['id'] ? 'selected' : '' ?>><?= e($z['zone_name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Date &amp; time</label><input type="datetime-local" name="scheduled_time" required value="<?= e($mWhen) ?>"></div>
      <div class="field"><label>Status</label><select name="status">
        <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $m['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select></div>
      <div class="form-actions"><button class="btn" type="submit"><?= $m['id'] ? 'Save changes' : 'Schedule meetup' ?></button></div>
    </form>
  <?php endif; ?>
  </section>

  <div class="stack">
  <?php if ($tab === 'threads'): ?>
    <?php if ($open): ?>
    <section class="card">
      <div class="card-head"><div><h2><?= e($open['title']) ?></h2><span class="muted"><?= e($open['buyer']) ?> (buyer) ↔ <?= e($open['seller']) ?> (seller)</span></div>
        <a class="btn btn-sm btn-ghost" href="<?= e(url(['tab' => 'threads'])) ?>">Close</a></div>
      <div class="chat">
        <?php foreach ($messages as $msg): ?>
          <div class="bubble <?= (int)$msg['sender_id'] === (int)$open['buyer_id'] ? 'mine' : '' ?>"><?= e($msg['message_body']) ?>
            <small><?= e($msg['full_name']) ?> · <?= e(dt($msg['sent_at'])) ?> ·
              <?= delete_form('delete_message', ['id' => $msg['id'], 'thread_id' => $open['id'], 'tab' => 'threads'], 'Delete this message?') ?></small></div>
        <?php endforeach; if (!$messages) echo '<span class="muted">No messages yet. Say hi!</span>'; ?>
      </div>
      <form method="post" class="row2" style="margin-top:14px;align-items:end"><?= csrf_field() ?>
        <input type="hidden" name="action" value="send_message"><input type="hidden" name="thread_id" value="<?= (int)$open['id'] ?>"><input type="hidden" name="tab" value="threads">
        <div class="field" style="margin:0"><label>Message</label><input type="text" name="message_body" required maxlength="1000" placeholder="Type a message…"></div>
        <div style="display:flex;gap:8px"><select name="sender_id"><option value="<?= (int)$open['buyer_id'] ?>">as <?= e($open['buyer']) ?></option><option value="<?= (int)$open['seller_id'] ?>">as <?= e($open['seller']) ?></option></select><button class="btn" type="submit">Send</button></div>
      </form>
    </section>
    <?php endif; ?>
    <section class="card">
      <div class="card-head"><h2>Chats <span class="muted">(<?= count($threads) ?>)</span></h2><input class="search" type="search" placeholder="Filter chats…" data-filter="#threadTable"></div>
      <div class="table-wrap"><table id="threadTable">
        <thead><tr><th>#</th><th>Listing</th><th>Buyer</th><th>Seller</th><th>Msgs</th><th>Meetups</th><th></th></tr></thead><tbody>
        <?php foreach ($threads as $t): ?>
          <tr><td><?= (int)$t['id'] ?></td><td><strong><?= e($t['title']) ?></strong></td><td><?= e($t['buyer']) ?></td><td><?= e($t['seller']) ?></td>
              <td><?= (int)$t['msgs'] ?></td><td><?= (int)$t['meetups'] ?></td>
              <td class="actions"><a class="btn btn-sm btn-ghost" href="<?= e(url(['tab' => 'threads', 'thread' => $t['id']])) ?>">Open</a>
                <?= delete_form('delete_thread', ['id' => $t['id'], 'tab' => 'threads'], 'Delete this chat, its messages and meetups?') ?></td></tr>
        <?php endforeach; if (!$threads) echo empty_row(7, 'No chats yet.'); ?>
      </tbody></table></div>
    </section>
  <?php else: ?>
    <section class="card">
      <div class="card-head"><h2>Meetup schedules <span class="muted">(<?= count($meetups) ?>)</span></h2><input class="search" type="search" placeholder="Filter meetups…" data-filter="#meetTable"></div>
      <p class="muted" style="margin-bottom:12px">Change a status and watch the <strong>listing status</strong> follow automatically (confirmed → reserved, completed → sold, cancelled → active).</p>
      <div class="table-wrap"><table id="meetTable">
        <thead><tr><th>#</th><th>Deal</th><th>Where &amp; when</th><th>Meetup</th><th>Listing</th><th></th></tr></thead><tbody>
        <?php foreach ($meetups as $r): $s = $r['status']; ?>
          <tr><td><?= (int)$r['id'] ?></td>
              <td><strong><?= e($r['title']) ?></strong><br><span class="muted"><?= e($r['buyer']) ?> → <?= e($r['seller']) ?></span></td>
              <td><?= e($r['zone_name']) ?><br><span class="muted"><?= e(dt($r['scheduled_time'])) ?></span></td>
              <td><?= status_badge($s) ?></td><td><?= status_badge($r['listing_status']) ?></td>
              <td class="actions">
                <?php foreach (['pending' => ['confirmed' => 'Confirm', 'cancelled' => 'Cancel'], 'confirmed' => ['completed' => 'Complete', 'cancelled' => 'Cancel'], 'cancelled' => ['pending' => 'Reopen']][$s] ?? [] as $to => $label): ?>
                  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="set_meetup_status"><input type="hidden" name="tab" value="meetups"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="status" value="<?= e($to) ?>"><button class="btn btn-sm btn-ghost"><?= e($label) ?></button></form>
                <?php endforeach; ?>
                <a class="btn btn-sm btn-ghost" href="<?= e(url(['tab' => 'meetups', 'edit' => $r['id']])) ?>">Edit</a>
                <?= delete_form('delete_meetup', ['id' => $r['id'], 'tab' => 'meetups'], 'Delete this meetup?') ?></td></tr>
        <?php endforeach; if (!$meetups) echo empty_row(6, 'No meetups scheduled.'); ?>
      </tbody></table></div>
    </section>
  <?php endif; ?>

    <section class="card">
      <div class="card-head"><h2>Report: chats that led to a meetup</h2><?= badge('INNER JOIN', 'green') ?></div>
      <p class="muted" style="margin-bottom:12px">Only chats that have a listing, a last message <em>and</em> a meetup appear. A chat without a meetup is excluded.</p>
      <div class="table-wrap"><table>
        <thead><tr><th>Chat</th><th>Listing</th><th>Buyer → Seller</th><th>Last message</th><th>Meetup</th></tr></thead><tbody>
        <?php foreach ($report as $r): ?>
          <tr><td>#<?= (int)$r['thread_id'] ?></td>
              <td><strong><?= e($r['listing_title']) ?></strong><br><?= status_badge($r['listing_status']) ?></td>
              <td><?= e($r['buyer']) ?> → <?= e($r['seller']) ?></td><td class="muted"><?= e($r['last_message']) ?></td>
              <td><?= status_badge($r['meetup_status']) ?><br><span class="muted"><?= e($r['zone_name']) ?> · <?= e(dt($r['scheduled_time'])) ?></span></td></tr>
        <?php endforeach; if (!$report) echo empty_row(5); ?>
      </tbody></table></div>
      <?= sql_block($reportSql) ?>
    </section>
  </div>
</div>
<?php render_footer();
