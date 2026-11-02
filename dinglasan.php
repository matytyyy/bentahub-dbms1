<?php
require_once 'config/db.php';
 
$error = null;
 
// 1. CREATE: Add a new Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $categoryName = trim($_POST['category_name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
 
    if ($categoryName === '') {
        $error = "Please enter a category name.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO categories (category_name, description) VALUES (?, ?)"
            );
            $stmt->execute([$categoryName, $description !== '' ? $description : null]);
            header("Location: dinglasan.php");
            exit;
        } catch (PDOException $e) {
            // 1062 = duplicate entry (category_name is UNIQUE)
            if ($e->errorInfo[1] == 1062) {
                $error = "That category already exists.";
            } else {
                $error = "Error adding category: " . $e->getMessage();
            }
        } catch (Exception $e) {
            $error = "Error adding category: " . $e->getMessage();
        }
    }
}
 
// 2. READ REPORT: RIGHT JOIN - every price drop alert with its listing's category.
//    price_drop_alerts has no category_id, so the category is reached through listings:
//    price_drop_alerts -> listings -> categories
$sql = "
    SELECT c.category_name,
           l.title AS listing_title,
           pda.id AS alert_id,
           pda.target_price,
           pda.user_id
    FROM categories c
    RIGHT JOIN listings l ON c.id = l.category_id
    RIGHT JOIN price_drop_alerts pda ON l.id = pda.listing_id
    ORDER BY pda.id ASC
";
$alerts = $pdo->query($sql)->fetchAll();
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BentaHub - Member Dinglasan (Categories & Alerts)</title>
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
            color: var(--forest-green);
            margin-bottom: 18px;
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
            <h1>Categories &amp; Price Drop Alerts</h1>
            <p>Price drop alerts matched to their listing's category (RIGHT JOIN)</p>
        </div>
 
        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
 
        <div class="layout-grid">
 
            <!-- Add category form -->
            <div class="card">
                <h3>Add a category</h3>
 
                <form method="POST" action="dinglasan.php">
                    <div class="form-group">
                        <label for="category_name">Category name</label>
                        <input type="text" name="category_name" id="category_name" maxlength="100"
                               placeholder="e.g. Snacks" required>
                    </div>
 
                    <div class="form-group">
                        <label for="description">Description (optional)</label>
                        <input type="text" name="description" id="description" maxlength="255"
                               placeholder="What belongs in this category">
                    </div>
 
                    <button type="submit" name="add_category" class="btn">Add category</button>
                </form>
            </div>
 
            <!-- Report table -->
            <div class="card">
                <h3>Price drop alerts by category</h3>
 
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Alert</th>
                                <th>Category</th>
                                <th>Listing</th>
                                <th>Target price</th>
                                <th>User ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($alerts)): ?>
                                <tr>
                                    <td colspan="5" class="empty">
                                        No price drop alerts yet. Import database/seed.sql to see data.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($alerts as $a): ?>
                                    <tr>
                                        <td>#<?= (int)$a['alert_id'] ?></td>
                                        <td>
                                            <?= $a['category_name'] !== null
                                                ? htmlspecialchars($a['category_name'])
                                                : '<span class="muted">No category</span>' ?>
                                        </td>
                                        <td>
                                            <?= $a['listing_title'] !== null
                                                ? htmlspecialchars($a['listing_title'])
                                                : '<span class="muted">-</span>' ?>
                                        </td>
                                        <td>PHP <?= number_format((float)$a['target_price'], 2) ?></td>
                                        <td><?= (int)$a['user_id'] ?></td>
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