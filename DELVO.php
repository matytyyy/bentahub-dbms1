<?php
require_once 'config/db.php';
 
$error = null;
 
// 1. CREATE: Log a new Audit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_log'])) {
    $userId = (int)$_POST['user_id'];
    $action = trim($_POST['action']);
 
    if ($userId <= 0 || $action === '') {
        $error = "Please choose a user and enter an action.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
            $stmt->execute([$userId, $action]);
            header("Location: delvo.php");
            exit;
        } catch (Exception $e) {
            $error = "Error adding audit log: " . $e->getMessage();
        }
    }
}
 
// 2. READ REPORT: FULL OUTER JOIN emulation (LEFT JOIN UNION RIGHT JOIN)
$sql = "
    SELECT ts.seller_id AS score_user_id, ts.rating_score, ts.trust_tier,
           al.id AS log_id, al.action
    FROM seller_trust_scores ts
    LEFT JOIN audit_logs al ON ts.seller_id = al.user_id
    UNION
    SELECT ts.seller_id AS score_user_id, ts.rating_score, ts.trust_tier,
           al.id AS log_id, al.action
    FROM seller_trust_scores ts
    RIGHT JOIN audit_logs al ON ts.seller_id = al.user_id
";
$logs = $pdo->query($sql)->fetchAll();
 
// Users list for the dropdown (so the foreign key never fails)
$users = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BentaHub - Member Delvo (Trust & Audit)</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap');
 
        :root {
            --beige-bg: #f5f0e6;
            --forest-green: #1b4332;
            --forest-hover: #2d6a4f;
            --card-bg: #ffffff;
            --text-dark: #1c2518;
            --text-muted: #5a6551;
            --border-color: #e2dacd;
        }
 
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
 
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--beige-bg);
            color: var(--text-dark);
            padding: 40px 20px;
            line-height: 1.5;
            min-height: 100vh;
        }
 
        .container {
            max-width: 1100px;
            margin: 0 auto;
        }
 
        .header-banner {
            background: linear-gradient(135deg, rgba(27, 67, 50, 0.92), rgba(45, 106, 79, 0.88)),
                        url('881387876_1602854481033124_4271610473624714199_n.png');
            background-size: cover;
            background-position: center;
            border-radius: 16px;
            padding: 32px 36px;
            color: #ffffff;
            margin-bottom: 32px;
            box-shadow: 0 10px 25px rgba(27, 67, 50, 0.18);
        }
 
        .back-link {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            color: #e2dacd;
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 16px;
            transition: color 0.2s ease;
        }
 
        .back-link:hover {
            color: #ffffff;
        }
 
        .header-banner h1 {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
 
        .header-banner p {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-top: 4px;
            font-weight: 500;
        }
 
        .alert-error {
            background-color: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            font-weight: 500;
        }
 
        .layout-grid {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 28px;
            align-items: start;
        }
 
        @media (max-width: 900px) {
            .layout-grid { grid-template-columns: 1fr; }
        }
 
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 26px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        }
 
        .card h2 {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
 
        .card .subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 20px;
        }
 
        .form-group {
            margin-bottom: 16px;
        }
 
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 6px;
        }
 
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            background: #fffdf8;
            color: var(--text-dark);
        }
 
        .form-group select:focus,
        .form-group input:focus {
            outline: 2px solid var(--forest-hover);
            outline-offset: 1px;
        }
 
        .btn {
            width: 100%;
            padding: 11px 16px;
            background: var(--forest-green);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }
 
        .btn:hover {
            background: var(--forest-hover);
        }
 
        .table-wrap {
            overflow-x: auto;
        }
 
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
 
        th {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 2px solid var(--border-color);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.8rem;
        }
 
        td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }
 
        tr:last-child td {
            border-bottom: none;
        }
 
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #e6efe9;
            color: var(--forest-green);
        }
 
        .muted {
            color: var(--text-muted);
            font-style: italic;
        }
 
        .empty {
            text-align: center;
            padding: 28px 12px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="container">
 
        <div class="header-banner">
            <a href="index.php" class="back-link">&larr; Back to dashboard</a>
            <h1>Trust &amp; Audit Engine</h1>
            <p>Seller trust tiers compared with audit log activity (FULL OUTER JOIN)</p>
        </div>
 
        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
 
        <div class="layout-grid">
 
            <!-- Add audit log form -->
            <div class="card">
                <h2>Log an action</h2>
                <p class="subtitle">Record an admin or user action in the audit log.</p>
 
                <form method="POST" action="delvo.php">
                    <div class="form-group">
                        <label for="user_id">User</label>
                        <select name="user_id" id="user_id" required>
                            <option value="">Select a user</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>">
                                    <?= htmlspecialchars($u['full_name']) ?> (ID <?= (int)$u['id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
 
                    <div class="form-group">
                        <label for="action">Action</label>
                        <input type="text" name="action" id="action" maxlength="100"
                               placeholder="e.g. Verified seller account" required>
                    </div>
 
                    <button type="submit" name="add_log" class="btn">Add log</button>
                </form>
            </div>
 
            <!-- Report table -->
            <div class="card">
                <h2>Trust scores and audit logs</h2>
                <p class="subtitle">Sellers without logs and logs without a trust score both appear.</p>
 
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Seller ID</th>
                                <th>Rating</th>
                                <th>Tier</th>
                                <th>Log ID</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="empty">
                                        No data yet. Import database/seed.sql or add a log.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $row): ?>
                                    <tr>
                                        <td>
                                            <?php if ($row['score_user_id'] !== null): ?>
                                                <?= (int)$row['score_user_id'] ?>
                                            <?php else: ?>
                                                <span class="muted">No score</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $row['rating_score'] !== null
                                                ? htmlspecialchars($row['rating_score'])
                                                : '<span class="muted">-</span>' ?>
                                        </td>
                                        <td>
                                            <?php if ($row['trust_tier'] !== null): ?>
                                                <span class="badge"><?= htmlspecialchars($row['trust_tier']) ?></span>
                                            <?php else: ?>
                                                <span class="muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $row['log_id'] !== null
                                                ? (int)$row['log_id']
                                                : '<span class="muted">No log</span>' ?>
                                        </td>
                                        <td>
                                            <?= $row['action'] !== null
                                                ? htmlspecialchars($row['action'])
                                                : '<span class="muted">-</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
 
        </div>
    </div>
</body>
</html>
