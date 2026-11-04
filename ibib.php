<?php
// IB-IB - Master Data slice: full CRUD for safe meetup zones, categories and item conditions + LEFT JOIN report.
require_once __DIR__ . '/includes/layout.php';

$tabs   = ['zones' => 'Safe meetup zones', 'categories' => 'Categories', 'conditions' => 'Item conditions'];
$tab    = $_GET['tab'] ?? post('tab', 'zones');
if (!isset($tabs[$tab])) $tab = 'zones';
// Whitelist: table/column names are never taken from user input.
$lookup = [
    'categories' => ['table' => 'categories',      'col' => 'category_name',  'fk' => 'category_id',  'max' => 100, 'label' => 'Category'],
    'conditions' => ['table' => 'item_conditions', 'col' => 'condition_name', 'fk' => 'condition_id', 'max' => 50,  'label' => 'Condition'],
];
$errors = [];
$edit   = null;

/* ---------- CREATE / UPDATE / DELETE ---------- */
if (is_post()) {
    csrf_verify();
    $action = post('action');
    $id     = (int)post('id');

    if ($action === 'save_zone') {
        $name = post('zone_name'); $addr = post('address'); $city = post('city');
        $geo  = isset($_POST['is_geofenced']) ? 1 : 0;
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Zone name must be 2 to 100 characters.';
        if (mb_strlen($addr) < 3 || mb_strlen($addr) > 255) $errors[] = 'Address must be 3 to 255 characters.';
        if (mb_strlen($city) < 2 || mb_strlen($city) > 100) $errors[] = 'City must be 2 to 100 characters.';
        if (!$errors) {
            try {
                if ($id) {
                    $pdo->prepare('UPDATE safe_meetup_zones SET zone_name=?, address=?, city=?, is_geofenced=? WHERE id=?')->execute([$name, $addr, $city, $geo, $id]);
                } else {
                    $pdo->prepare('INSERT INTO safe_meetup_zones (zone_name, address, city, is_geofenced) VALUES (?,?,?,?)')->execute([$name, $addr, $city, $geo]);
                    $id = (int)$pdo->lastInsertId();
                }
                audit('Saved Safe Zone', "Zone #$id: $name");
                flash('success', 'Safe zone saved.');
                redirect(url(['tab' => 'zones']));
            } catch (Throwable $e) { $errors[] = db_error($e); }
        }
        $edit = ['id' => $id, 'zone_name' => $name, 'address' => $addr, 'city' => $city, 'is_geofenced' => $geo];
    }

    if ($action === 'delete_zone') {
        try {
            $pdo->prepare('DELETE FROM safe_meetup_zones WHERE id=?')->execute([$id]);
            audit('Deleted Safe Zone', "Zone #$id");
            flash('success', 'Safe zone deleted.');
        } catch (Throwable $e) { flash('error', db_error($e) . ' (A zone with scheduled meetups cannot be removed.)'); }
        redirect(url(['tab' => 'zones']));
    }

    if (($action === 'save_lookup' || $action === 'delete_lookup') && isset($lookup[$tab])) {
        $cfg = $lookup[$tab];
        if ($action === 'save_lookup') {
            $name = post('name'); $desc = post('description');
            if (mb_strlen($name) < 2 || mb_strlen($name) > $cfg['max']) $errors[] = $cfg['label'] . ' name must be 2 to ' . $cfg['max'] . ' characters.';
            if (mb_strlen($desc) > 255) $errors[] = 'Description is limited to 255 characters.';
            if (!$errors) {
                try {
                    if ($id) {
                        $pdo->prepare("UPDATE {$cfg['table']} SET {$cfg['col']}=?, description=? WHERE id=?")->execute([$name, $desc ?: null, $id]);
                    } else {
                        $pdo->prepare("INSERT INTO {$cfg['table']} ({$cfg['col']}, description) VALUES (?,?)")->execute([$name, $desc ?: null]);
                        $id = (int)$pdo->lastInsertId();
                    }
                    audit('Saved ' . $cfg['label'], "#$id: $name");
                    flash('success', $cfg['label'] . ' saved.');
                    redirect(url(['tab' => $tab]));
                } catch (Throwable $e) { $errors[] = db_error($e); }
            }
            $edit = ['id' => $id, 'name' => $name, 'description' => $desc];
        } else {
            try {
                $pdo->prepare("DELETE FROM {$cfg['table']} WHERE id=?")->execute([$id]);
                audit('Deleted ' . $cfg['label'], "#$id");
                flash('success', $cfg['label'] . ' deleted.');
            } catch (Throwable $e) { flash('error', db_error($e) . ' (It is still used by listings.)'); }
            redirect(url(['tab' => $tab]));
        }
    }
}

/* ---------- load a row into the form (?edit=ID) ---------- */
if (!$edit && isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    if ($tab === 'zones') {
        $st = $pdo->prepare('SELECT * FROM safe_meetup_zones WHERE id=?');
    } else {
        $c = $lookup[$tab];
        $st = $pdo->prepare("SELECT id, {$c['col']} AS name, description FROM {$c['table']} WHERE id=?");
    }
    $st->execute([$eid]);
    $edit = $st->fetch() ?: null;
}
$z = $edit ?? ['id' => 0, 'zone_name' => '', 'address' => '', 'city' => 'Dumaguete City', 'is_geofenced' => 1];
$l = $edit ?? ['id' => 0, 'name' => '', 'description' => ''];

/* ---------- READ ---------- */
$zones = $pdo->query('SELECT * FROM safe_meetup_zones ORDER BY id')->fetchAll();
$rows  = [];
if (isset($lookup[$tab])) {
    $c = $lookup[$tab];
    $rows = $pdo->query("SELECT t.id, t.{$c['col']} AS name, t.description, COUNT(l.id) AS uses
                         FROM {$c['table']} t LEFT JOIN listings l ON l.{$c['fk']} = t.id
                         GROUP BY t.id ORDER BY t.id")->fetchAll();
}

/* ---------- JOIN REPORT: LEFT JOIN ---------- */
$reportSql = "
SELECT z.id AS zone_id, z.zone_name, z.address, z.city,
       COUNT(m.id) AS total_scheduled_meetups,
       MAX(m.scheduled_time) AS latest_meetup
FROM safe_meetup_zones z
LEFT JOIN meetup_schedules m ON z.id = m.zone_id
GROUP BY z.id, z.zone_name, z.address, z.city
ORDER BY total_scheduled_meetups DESC, z.zone_name";
$report = $pdo->query($reportSql)->fetchAll();

render_header('Master Data', 'ibib', 'Reference tables every listing and meetup depends on.', 'LEFT JOIN');
render_tabs($tabs, $tab);
?>
<div class="grid grid-form">
  <section class="card">
  <?php if ($tab === 'zones'): ?>
    <div class="card-head"><h2><?= $z['id'] ? 'Edit zone #' . (int)$z['id'] : 'Add safe zone' ?></h2>
      <?php if ($z['id']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url(['tab' => 'zones'])) ?>">Cancel</a><?php endif; ?></div>
    <?= errors_box($errors) ?>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="action" value="save_zone"><input type="hidden" name="tab" value="zones"><input type="hidden" name="id" value="<?= (int)$z['id'] ?>">
      <div class="field"><label>Zone name</label><input type="text" name="zone_name" required maxlength="100" value="<?= e($z['zone_name']) ?>"></div>
      <div class="field"><label>Address</label><input type="text" name="address" required maxlength="255" value="<?= e($z['address']) ?>"></div>
      <div class="field"><label>City</label><input type="text" name="city" required maxlength="100" value="<?= e($z['city']) ?>"></div>
      <label class="check"><input type="checkbox" name="is_geofenced" <?= $z['is_geofenced'] ? 'checked' : '' ?>> Geofenced (meetup must happen inside this zone)</label>
      <div class="form-actions"><button class="btn" type="submit"><?= $z['id'] ? 'Save changes' : 'Add zone' ?></button></div>
    </form>
  <?php else: $cfg = $lookup[$tab]; ?>
    <div class="card-head"><h2><?= $l['id'] ? 'Edit ' . e(strtolower($cfg['label'])) . ' #' . (int)$l['id'] : 'Add ' . e(strtolower($cfg['label'])) ?></h2>
      <?php if ($l['id']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url(['tab' => $tab])) ?>">Cancel</a><?php endif; ?></div>
    <?= errors_box($errors) ?>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="action" value="save_lookup"><input type="hidden" name="tab" value="<?= e($tab) ?>"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
      <div class="field"><label><?= e($cfg['label']) ?> name</label><input type="text" name="name" required maxlength="<?= (int)$cfg['max'] ?>" value="<?= e($l['name']) ?>"></div>
      <div class="field"><label>Description <span class="hint">(optional)</span></label><textarea name="description" maxlength="255"><?= e($l['description']) ?></textarea></div>
      <div class="form-actions"><button class="btn" type="submit"><?= $l['id'] ? 'Save changes' : 'Add ' . e(strtolower($cfg['label'])) ?></button></div>
    </form>
  <?php endif; ?>
  </section>

  <div class="stack">
    <section class="card">
      <div class="card-head"><h2><?= e($tabs[$tab]) ?></h2></div>
      <div class="table-wrap"><table>
      <?php if ($tab === 'zones'): ?>
        <thead><tr><th>#</th><th>Zone</th><th>Address</th><th>City</th><th>Geofenced</th><th></th></tr></thead><tbody>
        <?php foreach ($zones as $r): ?>
          <tr><td><?= (int)$r['id'] ?></td><td><strong><?= e($r['zone_name']) ?></strong></td><td><?= e($r['address']) ?></td><td><?= e($r['city']) ?></td>
              <td><?= $r['is_geofenced'] ? badge('Yes', 'green') : badge('No', 'gray') ?></td>
              <td class="actions"><a class="btn btn-sm btn-ghost" href="<?= e(url(['tab' => 'zones', 'edit' => $r['id']])) ?>">Edit</a>
                <?= delete_form('delete_zone', ['id' => $r['id'], 'tab' => 'zones'], 'Delete zone "' . $r['zone_name'] . '"?') ?></td></tr>
        <?php endforeach; if (!$zones) echo empty_row(6, 'No safe zones yet.'); ?>
      <?php else: ?>
        <thead><tr><th>#</th><th>Name</th><th>Description</th><th>Used by</th><th></th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td><?= (int)$r['id'] ?></td><td><strong><?= e($r['name']) ?></strong></td><td><?= e($r['description'] ?: '—') ?></td>
              <td><?= badge($r['uses'] . ' listing' . ($r['uses'] == 1 ? '' : 's'), $r['uses'] ? 'green' : 'gray') ?></td>
              <td class="actions"><a class="btn btn-sm btn-ghost" href="<?= e(url(['tab' => $tab, 'edit' => $r['id']])) ?>">Edit</a>
                <?= delete_form('delete_lookup', ['id' => $r['id'], 'tab' => $tab], 'Delete "' . $r['name'] . '"?') ?></td></tr>
        <?php endforeach; if (!$rows) echo empty_row(5); ?>
      <?php endif; ?>
      </tbody></table></div>
    </section>

    <section class="card">
      <div class="card-head"><h2>Report: safe zones with scheduled meetup counts</h2><?= badge('LEFT JOIN', 'green') ?></div>
      <p class="muted" style="margin-bottom:12px">Every zone is listed, even those with <strong>zero</strong> meetups (the right-hand side is NULL → count 0).</p>
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>Zone</th><th>City</th><th>Meetups</th><th>Latest meetup</th></tr></thead><tbody>
        <?php foreach ($report as $r): ?>
          <tr><td><?= (int)$r['zone_id'] ?></td><td><strong><?= e($r['zone_name']) ?></strong><br><span class="muted"><?= e($r['address']) ?></span></td><td><?= e($r['city']) ?></td>
              <td><?= badge((string)$r['total_scheduled_meetups'], $r['total_scheduled_meetups'] > 0 ? 'green' : 'amber') ?></td>
              <td><?= $r['latest_meetup'] ? e(dt($r['latest_meetup'])) : '<span class="null">never used</span>' ?></td></tr>
        <?php endforeach; if (!$report) echo empty_row(5); ?>
      </tbody></table></div>
      <?= sql_block($reportSql) ?>
    </section>
  </div>
</div>
<?php render_footer();
