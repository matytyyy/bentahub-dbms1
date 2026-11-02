<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BentaHub DBMS 1 Application</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap');

        :root {
            --olive-bg: #3d4a34;
            --forest-green: #1b4332;
            --forest-hover: #2d6a4f;
            --card-bg: rgba(255, 255, 255, 0.92);
            --text-dark: #1f2937;
            --text-muted: #4b5563;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--olive-bg);
            /* Front page image overlay */
            background-image: linear-gradient(to bottom, rgba(61, 74, 52, 0.4), rgba(61, 74, 52, 0.85)), 
                              url('877735046_2627859480988562_1181721907676972821_n (1).png');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: top center;
            background-attachment: fixed;
            color: var(--text-dark);
            min-height: 100vh;
            padding: 60px 20px;
        }

        .container {
            max-width: 850px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 1.05rem;
            font-weight: 500;
            opacity: 0.9;
        }

        .nav-grid {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .nav-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 14px;
            padding: 22px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            color: var(--text-dark);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .nav-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.25);
            background: #ffffff;
        }

        .nav-info h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--forest-green);
        }

        .nav-info p {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 500;
        }

        .btn-launch {
            background-color: var(--forest-green);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 10px 18px;
            border-radius: 8px;
            transition: background-color 0.2s ease, transform 0.1s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .nav-card:hover .btn-launch {
            background-color: var(--forest-hover);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BentaHub DBMS 1 Application</h1>
            <p>Select a group member screen to inspect live database joins and CRUD modules.</p>
        </div>

        <div class="nav-grid">
            <a href="ijan.php" class="nav-card">
                <div class="nav-info">
                    <h3>Member Ijan</h3>
                    <p>INNER JOIN: Active Verified Sellers & User Management</p>
                </div>
                <span class="btn-launch">View Screen &rarr;</span>
            </a>

            <a href="ibib.php" class="nav-card">
                <div class="nav-info">
                    <h3>Member Ib-Ib</h3>
                    <p>LEFT JOIN: Safe Zones & Meetup Locations</p>
                </div>
                <span class="btn-launch">View Screen &rarr;</span>
            </a>

            <a href="dinglasan.php" class="nav-card">
                <div class="nav-info">
                    <h3>Member Dinglasan</h3>
                    <p>RIGHT JOIN: Categories & Price Alerts</p>
                </div>
                <span class="btn-launch">View Screen &rarr;</span>
            </a>

            <a href="espiritu.php" class="nav-card">
                <div class="nav-info">
                    <h3>Member Espiritu</h3>
                    <p>INNER JOIN: Active Chats & Meetups</p>
                </div>
                <span class="btn-launch">View Screen &rarr;</span>
            </a>

            <a href="delvo.php" class="nav-card">
                <div class="nav-info">
                    <h3>Member Delvo</h3>
                    <p>FULL OUTER JOIN: Trust Scores & Audit Logs</p>
                </div>
                <span class="btn-launch">View Screen &rarr;</span>
            </a>
        </div>
    </div>
</body>
</html>