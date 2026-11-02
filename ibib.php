<?php
require_once 'config/db.php';
 
$error = null;
 
// 1. CREATE: Handle form submission to add a new Safe Zone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_zone'])) {
    $zoneName = trim($_POST['zone_name'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $city     = trim($_POST['city'] ?? '');
 
    if ($zoneName === '' || $address === '' || $city === '') {
        $error = "Please fill in the zone name, address, and city.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO safe_meetup_zones (zone_name, address, city) VALUES (?, ?, ?)"
            );
            $stmt->execute([$zoneName, $address, $city]);
            header("Location: ibib.php");
            exit;
        } catch (Exception $e) {
            $error = "Error adding safe zone: " . $e->getMessage();
        }
    }
}
 
// 2. READ REPORT: LEFT JOIN - every safe zone with its scheduled meetup count
//    (zones with no meetups still appear, with a count of 0)
$sql = "
    SELECT sz.id AS zone_id,
           sz.zone_name,
           sz.address,
           sz.city,
           sz.is_geofenced,
           COUNT(ms.id) AS meetup_count
    FROM safe_meetup_zones sz
    LEFT JOIN meetup_schedules ms ON sz.id = ms.zone_id
    GROUP BY sz.id, sz.zone_name, sz.address, sz.city, sz.is_geofenced
    ORDER BY sz.id ASC
";
$zones = $pdo->query($sql)->fetchAll();
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BentaHub - Member Ib-Ib (Safe Zones)</title>
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
            --badge-green-bg: #dcfce7;
            --badge-green-text: #15803d;
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
            background: var(--badge-green-bg);
            color: var(--badge-green-text);
        }
 
        .badge.none {
            background: #f1ede4;
            color: var(--text-muted);
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
            <h1>Safe Zones</h1>
            <p>Every safe meetup zone with its number of scheduled meetups (LEFT JOIN)</p>
        </div>
 
        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
 
        <div class="layout-grid">
 
            <!-- Add safe zone form -->
            <div class="card">
                <h3>Add a safe zone</h3>
                <p class="subtitle">Create a campus or community spot for meetups.</p>
 
                <form method="POST" action="ibib.php">
                    <div class="form-group">
                        <label for="zone_name">Zone name</label>
                        <input type="text" name="zone_name" id="zone_name" maxlength="100"
                               placeholder="e.g. Library Main Entrance" required>
                    </div>
 
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" name="address" id="address" maxlength="255"
                               placeholder="Street or landmark" required>
                    </div>
 
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" name="city" id="city" maxlength="100"
                               placeholder="e.g. Cebu City" required>
                    </div>
 
                    <button type="submit" name="add_zone" class="btn">Add safe zone</button>
                </form>
            </div>
 
            <!-- Report table -->
            <div class="card">
                <h3>Safe zones and meetups</h3>
                <p class="subtitle">Zones with no meetups are still listed, with a count of 0.</p>
 
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Zone</th>
                                <th>Address</th>
                                <th>City</th>
                                <th>Geofenced</th>
                                <th>Meetups</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($zones)): ?>
                                <tr>
                                    <td colspan="6" class="empty">
                                        No safe zones yet. Import database/seed.sql or add one.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($zones as $z): ?>
                                    <tr>
                                        <td><?= (int)$z['zone_id'] ?></td>
                                        <td><?= htmlspecialchars($z['zone_name']) ?></td>
                                        <td><?= htmlspecialchars($z['address']) ?></td>
                                        <td><?= htmlspecialchars($z['city']) ?></td>
                                        <td><?= $z['is_geofenced'] ? 'Yes' : 'No' ?></td>
                                        <td>
                                            <span class="badge <?= (int)$z['meetup_count'] === 0 ? 'none' : '' ?>">
                                                <?= (int)$z['meetup_count'] ?>
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