<?php
require_once 'config/db.php';

// 1. CREATE: Log a new Audit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_log'])) {
    $userId = (int)$_POST['user_id'];
    $action = trim($_POST['action']);

    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_performed) VALUES (?, ?)");
        $stmt->execute([$userId, $action]);
        header("Location: delvo.php");
        exit;
    } catch (Exception $e) {
        $error = "Error adding audit log: " . $e->getMessage();
    }
}

// 2. READ REPORT: Member Delvo's FULL OUTER JOIN Emulation Query
$sql = "
    SELECT ts.user_id AS score_user_id, ts.trust_score, al.id AS log_id, al.action_performed
    FROM trust_scores ts
    LEFT JOIN audit_logs al ON ts.user_id = al.user_id
    UNION
    SELECT ts.user_id AS score_user_id, ts.trust_score, al.id AS log_id, al.action_performed
    FROM trust_scores ts
    RIGHT JOIN audit_logs al ON ts.user_id = al.user_id;
";
$logs = $pdo->query($sql)->fetchAll();
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