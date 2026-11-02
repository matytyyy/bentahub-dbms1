<?php
require_once 'config/db.php';
//  .
// 1. CREATE: Form submission to insert user and assign 'Seller' role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $passwordHash = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $phone = trim($_POST['phone']);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, is_verified) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$fullName, $email, $passwordHash, $phone]);
        $newUserId = $pdo->lastInsertId();

        // Assign Seller Role (role_id = 2)
        $stmtRole = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, 2)");
        $stmtRole->execute([$newUserId]);

        $pdo->commit();
        header("Location: ijan.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error adding seller: " . $e->getMessage();
    }
}

// 2. READ & JOIN REPORT: Execute Member Ijan's INNER JOIN Query
$sql = "
    SELECT u.id AS user_id, u.full_name, u.email, u.phone_number, u.is_verified, r.role_name
    FROM users u
    INNER JOIN user_roles ur ON u.id = ur.user_id
    INNER JOIN roles r ON ur.role_id = r.id
    WHERE r.role_name = 'Seller' AND u.is_verified = 1
    ORDER BY u.id ASC;
";
$sellers = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BentaHub - Member Ijan (Core & Auth)</title>
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
            .layout-grid {
                grid-template-columns: 1fr;
            }
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
            color: var(--forest-green);
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 0.775rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .form-group input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.925rem;
            background-color: #faf8f5;
            transition: all 0.2s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--forest-green);
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(27, 67, 50, 0.12);
        }

        .btn-submit {
            width: 100%;
            background-color: var(--forest-green);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.1s ease;
            box-shadow: 0 4px 10px rgba(27, 67, 50, 0.2);
        }

        .btn-submit:hover {
            background-color: var(--forest-hover);
        }

        .btn-submit:active {
            transform: scale(0.99);
        }

        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        }

        .table-card-header {
            padding: 22px 26px;
            border-bottom: 1px solid var(--border-color);
            background-color: #faf7f2;
        }

        .table-card-header h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--forest-green);
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.925rem;
        }

        th {
            background-color: #faf8f5;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 14px 22px;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-dark);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr {
            transition: background-color 0.15s ease;
        }

        tbody tr:hover {
            background-color: #f7f3eb;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.775rem;
            font-weight: 700;
        }

        .badge-verified {
            background-color: var(--badge-green-bg);
            color: var(--badge-green-text);
        }

        .badge-role {
            background-color: #f0fdf4;
            color: var(--forest-green);
            border: 1px solid #bbf7d0;
        }

        .user-id {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            color: var(--text-muted);
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-banner">
            <a href="index.php" class="back-link">&larr; Back to Navigation Page</a>
            <h1>User Profiles & Roles</h1>
            <p>Member Ijan &bull; Core Authentication & Verified Seller Management</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="layout-grid">
            <div class="card">
                <h3>Add Verified Seller</h3>
                <form action="ijan.php" method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" placeholder="John Doe" required>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="john@example.com" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" placeholder="09171234567" required>
                    </div>

                    <button type="submit" name="add_user" class="btn-submit">Create Verified Seller</button>
                </form>
            </div>

            <div class="table-card">
                <div class="table-card-header">
                    <h2>Active Verified Sellers (INNER JOIN Report)</h2>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone Number</th>
                                <th>Status</th>
                                <th>Assigned Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sellers)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">No active verified sellers found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sellers as $seller): ?>
                                    <tr>
                                        <td class="user-id">#<?= htmlspecialchars($seller['user_id']) ?></td>
                                        <td style="font-weight: 600;"><?= htmlspecialchars($seller['full_name']) ?></td>
                                        <td><?= htmlspecialchars($seller['email']) ?></td>
                                        <td><?= htmlspecialchars($seller['phone_number']) ?></td>
                                        <td>
                                            <span class="badge badge-verified">
                                                <?= $seller['is_verified'] ? 'Verified' : 'Unverified' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-role">
                                                <?= htmlspecialchars($seller['role_name']) ?>
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