<?php
require_once 'config/db.php';
 
$error = null;
 
// 1. CREATE: Schedule a new Meetup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_meetup'])) {
    $threadId      = (int)($_POST['thread_id'] ?? 0);
    $zoneId        = (int)($_POST['zone_id'] ?? 0);
    $scheduledTime = str_replace('T', ' ', trim($_POST['scheduled_time'] ?? ''));
 
    if ($threadId <= 0 || $zoneId <= 0 || $scheduledTime === '') {
        $error = "Please choose a chat, a safe zone, and a date and time.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO meetup_schedules (thread_id, zone_id, scheduled_time, status)
                 VALUES (?, ?, ?, 'pending')"
            );
            $stmt->execute([$threadId, $zoneId, $scheduledTime]);
            header("Location: espiritu.php");
            exit;
        } catch (Exception $e) {
            $error = "Error scheduling meetup: " . $e->getMessage();
        }
    }
}
 
// 2. READ REPORT: INNER JOIN of meetups with their chat threads and safe zones
$sql = "
    SELECT ms.id AS meetup_id,
           ct.id AS thread_id,
           l.title AS listing_title,
           sz.zone_name,
           ms.scheduled_time,
           ms.status
    FROM meetup_schedules ms
    INNER JOIN chat_threads ct ON ms.thread_id = ct.id
    INNER JOIN listings l ON ct.listing_id = l.id
    INNER JOIN safe_meetup_zones sz ON ms.zone_id = sz.id
    ORDER BY ms.id ASC
";
$meetups = $pdo->query($sql)->fetchAll();
 
// Dropdown data (so the foreign keys never fail)
$threads = $pdo->query("
    SELECT ct.id, l.title
    FROM chat_threads ct
    INNER JOIN listings l ON ct.listing_id = l.id
    ORDER BY ct.id
")->fetchAll();
 
$zones = $pdo->query("SELECT id, zone_name FROM safe_meetup_zones ORDER BY zone_name")->fetchAll();
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BentaHub - Member Espiritu (Chats & Meetups)</title>
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
 
        .card h3 {
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
 
        .badge.pending   { background: #fdf3d8; color: #8a6100; }
        .badge.cancelled { background: #fde8e8; color: #b91c1c; }
 
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
            <h1>Chats &amp; Meetups</h1>
            <p>Scheduled meetups linked to their chat threads and safe zones (INNER JOIN)</p>
        </div>
 
        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
 
        <div class="layout-grid">
 
            <!-- Schedule meetup form -->
            <div class="card">
                <h3>Schedule a meetup</h3>
                <p class="subtitle">Pick a chat thread, a safe zone, and a time.</p>
 
                <form method="POST" action="espiritu.php">
                    <div class="form-group">
                        <label for="thread_id">Chat thread</label>
                        <select name="thread_id" id="thread_id" required>
                            <option value="">Select a chat</option>
                            <?php foreach ($threads as $t): ?>
                                <option value="<?= (int)$t['id'] ?>">
                                    #<?= (int)$t['id'] ?> - <?= htmlspecialchars($t['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
 
                    <div class="form-group">
                        <label for="zone_id">Safe zone</label>
                        <select name="zone_id" id="zone_id" required>
                            <option value="">Select a safe zone</option>
                            <?php foreach ($zones as $z): ?>
                                <option value="<?= (int)$z['id'] ?>">
                                    <?= htmlspecialchars($z['zone_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
 
                    <div class="form-group">
                        <label for="scheduled_time">Date and time</label>
                        <input type="datetime-local" name="scheduled_time" id="scheduled_time" required>
                    </div>
 
                    <button type="submit" name="add_meetup" class="btn">Schedule meetup</button>
                </form>
            </div>
 
            <!-- Report table -->
            <div class="card">
                <h3>Scheduled meetups</h3>
                <p class="subtitle">Only meetups that have a valid chat thread and safe zone appear here.</p>
 
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Meetup</th>
                                <th>Chat</th>
                                <th>Listing</th>
                                <th>Safe zone</th>
                                <th>When</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($meetups)): ?>
                                <tr>
                                    <td colspan="6" class="empty">
                                        No meetups yet. Import database/seed.sql or schedule one.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($meetups as $m): ?>
                                    <tr>
                                        <td>#<?= (int)$m['meetup_id'] ?></td>
                                        <td>#<?= (int)$m['thread_id'] ?></td>
                                        <td><?= htmlspecialchars($m['listing_title']) ?></td>
                                        <td><?= htmlspecialchars($m['zone_name']) ?></td>
                                        <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($m['scheduled_time']))) ?></td>
                                        <td>
                                            <span class="badge <?= htmlspecialchars($m['status']) ?>">
                                                <?= htmlspecialchars(ucfirst($m['status'])) ?>
                                            </span>
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