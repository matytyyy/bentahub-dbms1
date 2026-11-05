<?php
// DINGLASAN - Item Catalog slice: full CRUD for listings (+ images) and price-drop alerts + RIGHT JOIN report.
require_once __DIR__ . '/includes/layout.php';

$tabs     = ['listings' => 'Listings', 'alerts' => 'Price-drop alerts'];
$tab      = $_GET['tab'] ?? post('tab', 'listings');
if (!isset($tabs[$tab])) $tab = 'listings';
$statuses = ['active', 'reserved', 'sold', 'archived'];
$errors   = [];
$edit     = null;

/* ---------- CREATE / UPDATE / DELETE ---------- */
if (is_post()) {
    csrf_verify();
    $action = post('action');
    $id     = (int)post('id');

    if ($action === 'save_listing') {
        $seller = (int)post('seller_id'); $cat = (int)post('category_id'); $cond = (int)post('condition_id');
        $title  = post('title'); $desc = post('description'); $price = post('price'); $status = post('status', 'active');
        if (!$seller || !$cat || !$cond)                        $errors[] = 'Choose a seller, a category and a condition.';
        if (mb_strlen($title) < 3 || mb_strlen($title) > 150)   $errors[] = 'Title must be 3 to 150 characters.';
        if (mb_strlen($desc) > 2000)                            $errors[] = 'Description is limited to 2000 characters.';
        if (!is_numeric($price) || (float)$price <= 0 || (float)$price > 99999999) $errors[] = 'Price must be a number greater than 0.';
        if (!in_array($status, $statuses, true))                $errors[] = 'Invalid status.';
        if (!$errors) {
            try {
                $old = null;
                if ($id) { $st = $pdo->prepare('SELECT price FROM listings WHERE id=?'); $st->execute([$id]); $old = $st->fetchColumn(); }
                if ($id) {
                    $pdo->prepare('UPDATE listings SET seller_id=?, category_id=?, condition_id=?, title=?, description=?, price=?, status=? WHERE id=?')
                        ->execute([$seller, $cat, $cond, $title, $desc ?: null, $price, $status, $id]);
                } else {
                    $pdo->prepare('INSERT INTO listings (seller_id, category_id, condition_id, title, description, price, status) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$seller, $cat, $cond, $title, $desc ?: null, $price, $status]);
                    $id = (int)$pdo->lastInsertId();
                }
                audit($old === null ? 'Created Listing' : 'Updated Listing', "Listing #$id: $title");
                flash('success', $old === null ? 'Listing created. Add photos below.' : 'Listing updated.');
                if ($old !== null && (float)$price < (float)$old) {   // the DB trigger already logged "Price Dropped"
                    $st = $pdo->prepare('SELECT COUNT(*) FROM v_triggered_price_alerts WHERE listing_id=?'); $st->execute([$id]);
                    $hit = (int)$st->fetchColumn();
                    flash('info', "Price dropped. $hit alert(s) have now reached their target price.");
                }
                redirect(url(['edit' => $id]));
            } catch (Throwable $e) { $errors[] = db_error($e); }
        }
        $edit = ['id' => $id, 'seller_id' => $seller, 'category_id' => $cat, 'condition_id' => $cond,
                 'title' => $title, 'description' => $desc, 'price' => $price, 'status' => $status];
    }

    if ($action === 'delete_listing') {
        try {
            $pdo->prepare('DELETE FROM listings WHERE id=?')->execute([$id]);
            audit('Deleted Listing', "Listing #$id");
            flash('success', 'Listing deleted (its images, alerts and chats went with it).');
        } catch (Throwable $e) { flash('error', db_error($e)); }
        redirect(url());
    }

    if ($action === 'add_image') {
        $lid = (int)post('listing_id'); $imgUrl = post('image_url'); $primary = isset($_POST['is_primary']) ? 1 : 0;
        if (mb_strlen($imgUrl) < 3 || mb_strlen($imgUrl) > 255 || preg_match('/[\s<>"\']/', $imgUrl)) {
            flash('error', 'Image path must be 3 to 255 characters with no spaces or quotes.');
        } else {
            try {
                $pdo->beginTransaction();
                if ($primary) $pdo->prepare('UPDATE listing_images SET is_primary=0 WHERE listing_id=?')->execute([$lid]);
                $pdo->prepare('INSERT INTO listing_images (listing_id, image_url, is_primary) VALUES (?,?,?)')->execute([$lid, $imgUrl, $primary]);
                $pdo->commit();
                flash('success', 'Image added.');
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('error', db_error($e)); }
        }
        redirect(url(['edit' => $lid]));
    }
    if ($action === 'set_primary' || $action === 'delete_image') {
        $lid = (int)post('listing_id');
        try {
            if ($action === 'set_primary') {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE listing_images SET is_primary=0 WHERE listing_id=?')->execute([$lid]);
                $pdo->prepare('UPDATE listing_images SET is_primary=1 WHERE id=? AND listing_id=?')->execute([$id, $lid]);
                $pdo->commit();
            } else {
                $pdo->prepare('DELETE FROM listing_images WHERE id=? AND listing_id=?')->execute([$id, $lid]);
            }
            flash('success', 'Images updated.');
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('error', db_error($e)); }
        redirect(url(['edit' => $lid]));
    }

    if ($action === 'save_alert') {
        $uid = (int)post('user_id'); $lid = (int)post('listing_id'); $target = post('target_price');
        if (!$uid || !$lid) $errors[] = 'Choose a user and a listing.';
        if (!is_numeric($target) || (float)$target <= 0) $errors[] = 'Target price must be a number greater than 0.';
        if (!$errors) {
            $st = $pdo->prepare('SELECT seller_id FROM listings WHERE id=?'); $st->execute([$lid]);
            if ((int)$st->fetchColumn() === $uid) $errors[] = 'A seller cannot set a price alert on their own listing.';
        }
        if (!$errors) {
            try {
                if ($id) {
                    $pdo->prepare('UPDATE price_drop_alerts SET user_id=?, listing_id=?, target_price=? WHERE id=?')->execute([$uid, $lid, $target, $id]);
                } else {
                    $pdo->prepare('INSERT INTO price_drop_alerts (user_id, listing_id, target_price) VALUES (?,?,?)')->execute([$uid, $lid, $target]);
                    $id = (int)$pdo->lastInsertId();
                }
                audit('Saved Price Alert', "Alert #$id on listing #$lid at $target");
                flash('success', 'Price alert saved.');
                redirect(url(['tab' => 'alerts']));
            } catch (Throwable $e) {
                $errors[] = ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) ? 'This user already has an alert on that listing.' : db_error($e);
            }
        }
        $edit = ['id' => $id, 'user_id' => $uid, 'listing_id' => $lid, 'target_price' => $target];
    }
    if ($action === 'delete_alert') {
        try { $pdo->prepare('DELETE FROM price_drop_alerts WHERE id=?')->execute([$id]); audit('Deleted Price Alert', "Alert #$id"); flash('success', 'Alert deleted.'); }
        catch (Throwable $e) { flash('error', db_error($e)); }
        redirect(url(['tab' => 'alerts']));
    }
}

/* ---------- load row into form (?edit=ID) ---------- */
if (!$edit && isset($_GET['edit'])) {
    $st = $pdo->prepare($tab === 'alerts' ? 'SELECT * FROM price_drop_alerts WHERE id=?' : 'SELECT * FROM listings WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$f = ($tab === 'listings' && $edit) ? $edit : ['id' => 0, 'seller_id' => '', 'category_id' => '', 'condition_id' => '', 'title' => '', 'description' => '', 'price' => '', 'status' => 'active'];
$a = ($tab === 'alerts' && $edit) ? $edit : ['id' => 0, 'user_id' => '', 'listing_id' => '', 'target_price' => ''];

/* ---------- READ ---------- */
$users      = $pdo->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll();
$categories = $pdo->query('SELECT id, category_name FROM categories ORDER BY category_name')->fetchAll();
$conditions = $pdo->query('SELECT id, condition_name FROM item_conditions ORDER BY id')->fetchAll();
$allListing = $pdo->query('SELECT id, title, price FROM listings ORDER BY title')->fetchAll();
$listings   = $pdo->query("
    SELECT l.id, l.title, l.price, l.status, c.category_name, ic.condition_name, u.full_name AS seller,
           (SELECT COUNT(*) FROM listing_images i WHERE i.listing_id = l.id) AS images
    FROM listings l
    JOIN categories c        ON c.id  = l.category_id
    JOIN item_conditions ic  ON ic.id = l.condition_id
    JOIN users u             ON u.id  = l.seller_id
    ORDER BY l.id DESC")->fetchAll();
$images = [];
if ($tab === 'listings' && $f['id']) {
    $st = $pdo->prepare('SELECT * FROM listing_images WHERE listing_id=? ORDER BY is_primary DESC, id');
    $st->execute([$f['id']]);
    $images = $st->fetchAll();
}
$alerts = $pdo->query("
    SELECT p.id, u.full_name AS watcher, l.title, l.price, l.status, p.target_price
    FROM price_drop_alerts p
    JOIN users u    ON u.id = p.user_id
    JOIN listings l ON l.id = p.listing_id
    ORDER BY p.id")->fetchAll();
$triggered = $pdo->query('SELECT * FROM v_triggered_price_alerts ORDER BY alert_id')->fetchAll();

/* ---------- JOIN REPORT: RIGHT JOIN ---------- */
$reportSql = "
SELECT c.category_name, l.title AS item_title, l.price AS current_price,
       p.target_price, u.full_name AS watcher
FROM price_drop_alerts p
RIGHT JOIN listings l   ON p.listing_id = l.id
RIGHT JOIN categories c ON l.category_id = c.id
LEFT JOIN  users u      ON u.id = p.user_id
ORDER BY c.category_name, l.title";
$report = $pdo->query($reportSql)->fetchAll();

render_header('Item Catalog', 'catalog', 'Listings, photos and price-drop alerts.', 'RIGHT JOIN');
render_tabs($tabs, $tab);
?>
<div class="grid grid-form">
  <section class="card">
  <?php if ($tab === 'listings'): ?>
    <div class="card-head"><h2><?= $f['id'] ? 'Edit listing #' . (int)$f['id'] : 'New listing' ?></h2>
      <?php if ($f['id']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url()) ?>">Cancel</a><?php endif; ?></div>
    <?= errors_box($errors) ?>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="action" value="save_listing"><input type="hidden" name="tab" value="listings"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <div class="field"><label>Title</label><input type="text" name="title" required maxlength="150" value="<?= e($f['title']) ?>"></div>
      <div class="field"><label>Seller</label><select name="seller_id" required><option value="">Choose…</option>
        <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)$f['seller_id'] === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
      <div class="row2">
        <div class="field"><label>Category</label><select name="category_id" required><option value="">Choose…</option>
          <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$f['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Condition</label><select name="condition_id" required><option value="">Choose…</option>
          <?php foreach ($conditions as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$f['condition_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['condition_name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="row2">
        <div class="field"><label>Price (₱)</label><input type="number" name="price" required min="0.01" step="0.01" value="<?= e($f['price']) ?>"></div>
        <div class="field"><label>Status <span class="hint">(auto-managed by meetups)</span></label><select name="status">
          <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label>Description</label><textarea name="description" maxlength="2000"><?= e($f['description']) ?></textarea></div>
      <div class="form-actions"><button class="btn" type="submit"><?= $f['id'] ? 'Save changes' : 'Create listing' ?></button></div>
    </form>
  <?php else: ?>
    <div class="card-head"><h2><?= $a['id'] ? 'Edit alert #' . (int)$a['id'] : 'New price alert' ?></h2>
      <?php if ($a['id']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url(['tab' => 'alerts'])) ?>">Cancel</a><?php endif; ?></div>
    <?= errors_box($errors) ?>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="action" value="save_alert"><input type="hidden" name="tab" value="alerts"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
      <div class="field"><label>Watcher</label><select name="user_id" required><option value="">Choose…</option>
        <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)$a['user_id'] === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Listing</label><select name="listing_id" required><option value="">Choose…</option>
        <?php foreach ($allListing as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$a['listing_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['title']) ?> (<?= e(money($l['price'])) ?>)</option><?php endforeach; ?></select></div>
      <div class="field"><label>Alert me at or below (₱)</label><input type="number" name="target_price" required min="0.01" step="0.01" value="<?= e($a['target_price']) ?>"></div>
      <div class="form-actions"><button class="btn" type="submit"><?= $a['id'] ? 'Save changes' : 'Create alert' ?></button></div>
    </form>
  <?php endif; ?>
  </section>

  <div class="stack">
  <?php if ($tab === 'listings'): ?>
    <section class="card">
      <div class="card-head"><h2>Listings <span class="muted">(<?= count($listings) ?>)</span></h2>
        <input class="search" type="search" placeholder="Filter listings…" data-filter="#listTable"></div>
      <div class="table-wrap"><table id="listTable">
        <thead><tr><th>#</th><th>Item</th><th>Seller</th><th>Price</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($listings as $r): ?>
          <tr><td><?= (int)$r['id'] ?></td>
              <td><strong><?= e($r['title']) ?></strong><br><span class="muted"><?= e($r['category_name']) ?> · <?= e($r['condition_name']) ?> · <?= (int)$r['images'] ?> photo(s)</span></td>
              <td><?= e($r['seller']) ?></td><td><?= e(money($r['price'])) ?></td><td><?= status_badge($r['status']) ?></td>
              <td class="actions"><a class="btn btn-sm btn-ghost" href="<?= e(url(['edit' => $r['id']])) ?>">Edit</a>
                <?= delete_form('delete_listing', ['id' => $r['id']], 'Delete "' . $r['title'] . '"? Its chats, meetups and alerts will be removed too.') ?></td></tr>
        <?php endforeach; if (!$listings) echo empty_row(6, 'No listings yet.'); ?>
      </tbody></table></div>
    </section>
    <?php if ($f['id']): ?>
    <section class="card">
      <div class="card-head"><h2>Photos for listing #<?= (int)$f['id'] ?></h2></div>
      <div class="thumbs">
        <?php foreach ($images as $im): ?>
          <div class="thumb"><span><?= e($im['image_url']) ?></span><?= $im['is_primary'] ? badge('Primary', 'green') : '' ?>
            <?php if (!$im['is_primary']): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="set_primary"><input type="hidden" name="id" value="<?= (int)$im['id'] ?>"><input type="hidden" name="listing_id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="tab" value="listings"><button class="btn btn-sm btn-ghost">Make primary</button></form><?php endif; ?>
            <?= delete_form('delete_image', ['id' => $im['id'], 'listing_id' => $f['id'], 'tab' => 'listings'], 'Remove this image?') ?></div>
        <?php endforeach; if (!$images) echo '<span class="muted">No photos yet.</span>'; ?>
      </div>
      <form method="post" class="row2" style="margin-top:14px;align-items:end"><?= csrf_field() ?>
        <input type="hidden" name="action" value="add_image"><input type="hidden" name="listing_id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="tab" value="listings">
        <div class="field" style="margin:0"><label>Image path or URL</label><input type="text" name="image_url" required maxlength="255" placeholder="images/laptop.jpg"></div>
        <div><label class="check"><input type="checkbox" name="is_primary"> Set as primary</label><button class="btn btn-sm" type="submit">Add image</button></div>
      </form>
    </section>
    <?php endif; ?>
  <?php else: ?>
    <section class="card">
      <div class="card-head"><h2>Alerts that have reached their target <?= badge((string)count($triggered), count($triggered) ? 'amber' : 'gray') ?></h2></div>
      <p class="muted" style="margin-bottom:12px">Live from the view <code>v_triggered_price_alerts</code>: an active listing priced at or below the watcher's target.</p>
      <div class="table-wrap"><table>
        <thead><tr><th>Watcher</th><th>Item</th><th>Now</th><th>Target</th><th>Below target by</th></tr></thead><tbody>
        <?php foreach ($triggered as $t): ?>
          <tr><td><?= e($t['watcher']) ?></td><td><?= e($t['title']) ?></td><td><?= e(money($t['current_price'])) ?></td><td><?= e(money($t['target_price'])) ?></td><td><?= badge(money($t['below_target_by']), 'green') ?></td></tr>
        <?php endforeach; if (!$triggered) echo empty_row(5, 'No alert has been triggered yet. Lower a listing price to see one fire.'); ?>
      </tbody></table></div>
    </section>
    <section class="card">
      <div class="card-head"><h2>All price alerts <span class="muted">(<?= count($alerts) ?>)</span></h2></div>
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>Watcher</th><th>Item</th><th>Now</th><th>Target</th><th></th></tr></thead><tbody>
        <?php foreach ($alerts as $r): ?>
          <tr><td><?= (int)$r['id'] ?></td><td><?= e($r['watcher']) ?></td><td><?= e($r['title']) ?> <?= status_badge($r['status']) ?></td>
              <td><?= e(money($r['price'])) ?></td><td><?= e(money($r['target_price'])) ?></td>
              <td class="actions"><a class="btn btn-sm btn-ghost" href="<?= e(url(['tab' => 'alerts', 'edit' => $r['id']])) ?>">Edit</a>
                <?= delete_form('delete_alert', ['id' => $r['id'], 'tab' => 'alerts'], 'Delete this alert?') ?></td></tr>
        <?php endforeach; if (!$alerts) echo empty_row(6); ?>
      </tbody></table></div>
    </section>
  <?php endif; ?>

    <section class="card">
      <div class="card-head"><h2>Report: every category with its items and price alerts</h2><?= badge('RIGHT JOIN', 'green') ?></div>
      <p class="muted" style="margin-bottom:12px"><code>categories</code> is the right-most table, so <strong>all</strong> categories survive, even ones with no listings (shown as “no listings”).</p>
      <div class="table-wrap"><table>
        <thead><tr><th>Category</th><th>Item</th><th>Current price</th><th>Alert target</th><th>Watcher</th></tr></thead><tbody>
        <?php foreach ($report as $r): ?>
          <tr><td><strong><?= e($r['category_name']) ?></strong></td>
              <td><?= $r['item_title'] !== null ? e($r['item_title']) : '<span class="null">no listings</span>' ?></td>
              <td><?= $r['current_price'] !== null ? e(money($r['current_price'])) : '<span class="null">—</span>' ?></td>
              <td><?= $r['target_price'] !== null ? e(money($r['target_price'])) : '<span class="null">no alert</span>' ?></td>
              <td><?= e($r['watcher'] ?? '—') ?></td></tr>
        <?php endforeach; if (!$report) echo empty_row(5); ?>
      </tbody></table></div>
      <?= sql_block($reportSql) ?>
    </section>
  </div>
</div>
<?php render_footer();
