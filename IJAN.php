<?php
// IJAN - Core & Auth slice: full CRUD for users (+ role assignment) and the INNER JOIN report.
require_once __DIR__ . '/includes/layout.php';

$errors = [];
$edit   = null;
$roles  = $pdo->query('SELECT id, role_name FROM roles ORDER BY id')->fetchAll();

/* ---------- CREATE / UPDATE / DELETE ---------- */
if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'save_user') {
        $id       = (int)post('id');
        $name     = post('full_name');
        $email    = post('email');
        $phone    = post('phone_number');
        $password = (string)($_POST['password'] ?? '');
        $verified = isset($_POST['is_verified']) ? 1 : 0;
        $roleIds  = array_map('intval', (array)($_POST['roles'] ?? []));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100)         $errors[] = 'Full name must be 2 to 100 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))              $errors[] = 'Enter a valid email address.';
        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) $errors[] = 'Phone may only contain digits, spaces, + and -.';
        if (!$id && strlen($password) < 8)                           $errors[] = 'Password must be at least 8 characters.';
        if ($id && $password !== '' && strlen($password) < 8)        $errors[] = 'New password must be at least 8 characters.';

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                if ($id) {
                    $pdo->prepare('UPDATE users SET full_name=?, email=?, phone_number=?, is_verified=? WHERE id=?')
                        ->execute([$name, $email, $phone ?: null, $verified, $id]);
                    if ($password !== '') {
                        $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                            ->execute([password_hash($password, PASSWORD_BCRYPT), $id]);
                    }
                    $uid = $id;
                } else {
                    $pdo->prepare('INSERT INTO users (full_name, email, password_hash, phone_number, is_verified) VALUES (?,?,?,?,?)')
                        ->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), $phone ?: null, $verified]);
                    $uid = (int)$pdo->lastInsertId();
                }
                $pdo->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$uid]);
                $ins = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
                foreach (array_unique($roleIds) as $rid) $ins->execute([$uid, $rid]);
                $pdo->commit();
                audit($id ? 'Updated User' : 'Created User', "User #$uid: $name");
                flash('success', $id ? 'User updated.' : 'User created.');
                redirect(url());
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = db_error($e);
            }
        }
        $edit = ['id' => $id, 'full_name' => $name, 'email' => $email, 'phone_number' => $phone,
                 'is_verified' => $verified, 'role_ids' => $roleIds];
    }

    if ($action === 'delete_user') {
        $id = (int)post('id');
        if ($id === current_user_id()) {
            flash('error', 'You are acting as this user. Switch "Acting as" to someone else first.');
        } else {
            try {
                $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
                audit('Deleted User', "User #$id");
                flash('success', 'User deleted (their listings, chats and reviews were removed too).');
            } catch (Throwable $e) { flash('error', db_error($e)); }
        }
        redirect(url());
    }
}

/* ---------- load a user into the form (?edit=ID) ---------- */
if (!$edit && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT id, full_name, email, phone_number, is_verified FROM users WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
    if ($edit) {
        $st = $pdo->prepare('SELECT role_id FROM user_roles WHERE user_id=?');
        $st->execute([$edit['id']]);
        $edit['role_ids'] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}
$f = $edit ?? ['id' => 0, 'full_name' => '', 'email' => '', 'phone_number' => '', 'is_verified' => 1, 'role_ids' => [3]];

/* ---------- READ ---------- */
$users = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.phone_number, u.is_verified, u.created_at,
           GROUP_CONCAT(r.role_name ORDER BY r.id SEPARATOR ',') AS roles
    FROM users u
    LEFT JOIN user_roles ur ON ur.user_id = u.id
    LEFT JOIN roles r       ON r.id = ur.role_id
    GROUP BY u.id ORDER BY u.id")->fetchAll();

/* ---------- JOIN REPORT: INNER JOIN ---------- */
$reportSql = "
SELECT u.id AS user_id, u.full_name, u.email, u.phone_number, u.is_verified, r.role_name
FROM users u
INNER JOIN user_roles ur ON u.id = ur.user_id
INNER JOIN roles r       ON ur.role_id = r.id
WHERE r.role_name = 'Seller' AND u.is_verified = 1
ORDER BY u.id ASC";
$sellers = $pdo->query($reportSql)->fetchAll();

render_header('Users & Roles', 'ijan', 'Core & Auth slice: manage accounts, passwords (bcrypt) and role assignment.', 'INNER JOIN');
?>
<div class="grid grid-form">
  <section class="card">
    <div class="card-head"><h2><?= $f['id'] ? 'Edit user #' . (int)$f['id'] : 'Add user' ?></h2>
      <?php if ($f['id']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url()) ?>">Cancel</a><?php endif; ?></div>
    <?= errors_box($errors) ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_user">
      <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <div class="field"><label>Full name</label><input type="text" name="full_name" required maxlength="100" value="<?= e($f['full_name']) ?>"></div>
      <div class="field"><label>Email</label><input type="email" name="email" required maxlength="120" value="<?= e($f['email']) ?>"></div>
      <div class="field"><label>Phone <span class="hint">(optional)</span></label><input type="text" name="phone_number" maxlength="20" value="<?= e($f['phone_number']) ?>"></div>
      <div class="field"><label>Password <?php if ($f['id']): ?><span class="hint">(leave blank to keep the current one)</span><?php endif; ?></label>
        <input type="password" name="password" minlength="8" <?= $f['id'] ? '' : 'required' ?> autocomplete="new-password"></div>
      <div class="field"><label>Roles</label><div class="checks">
        <?php foreach ($roles as $r): ?>
          <label class="check"><input type="checkbox" name="roles[]" value="<?= (int)$r['id'] ?>" <?= in_array((int)$r['id'], $f['role_ids'], true) ? 'checked' : '' ?>> <?= e($r['role_name']) ?></label>
        <?php endforeach; ?></div></div>
      <label class="check"><input type="checkbox" name="is_verified" <?= $f['is_verified'] ? 'checked' : '' ?>> Verified account</label>
      <div class="form-actions"><button class="btn" type="submit"><?= $f['id'] ? 'Save changes' : 'Create user' ?></button></div>
    </form>
  </section>

  <div class="stack">
    <section class="card">
      <div class="card-head"><h2>All users <span class="muted">(<?= count($users) ?>)</span></h2>
        <input class="search" type="search" placeholder="Filter users…" data-filter="#usersTable"></div>
      <div class="table-wrap"><table id="usersTable">
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Roles</th><th>Verified</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><strong><?= e($u['full_name']) ?></strong><br><span class="muted"><?= e($u['phone_number'] ?: '—') ?></span></td>
            <td><?= e($u['email']) ?></td>
            <td><?php foreach (array_filter(explode(',', (string)$u['roles'])) as $rn) echo badge($rn, $rn === 'Admin' ? 'red' : ($rn === 'Seller' ? 'green' : 'blue')) . ' '; ?></td>
            <td><?= $u['is_verified'] ? badge('Verified', 'green') : badge('Unverified', 'amber') ?></td>
            <td class="actions"><a class="btn btn-sm btn-ghost" href="<?= e(url(['edit' => $u['id']])) ?>">Edit</a>
              <?= delete_form('delete_user', ['id' => $u['id']], 'Delete ' . $u['full_name'] . '? This also removes their listings, chats and reviews.') ?></td>
          </tr>
        <?php endforeach; if (!$users) echo empty_row(6, 'No users yet.'); ?>
        </tbody></table></div>
    </section>

    <section class="card">
      <div class="card-head"><h2>Report: active verified sellers with their roles</h2><?= badge('INNER JOIN', 'green') ?></div>
      <p class="muted" style="margin-bottom:12px">Only users that have a matching row in <code>user_roles</code> and <code>roles</code> appear. A user with no role is dropped.</p>
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th></tr></thead>
        <tbody>
        <?php foreach ($sellers as $s): ?>
          <tr><td><?= (int)$s['user_id'] ?></td><td><strong><?= e($s['full_name']) ?></strong></td><td><?= e($s['email']) ?></td>
              <td><?= e($s['phone_number'] ?: '—') ?></td><td><?= badge($s['role_name'], 'green') ?></td></tr>
        <?php endforeach; if (!$sellers) echo empty_row(5, 'No verified sellers yet.'); ?>
        </tbody></table></div>
      <?= sql_block($reportSql) ?>
    </section>
  </div>
</div>
<?php render_footer();
