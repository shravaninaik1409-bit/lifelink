<?php
/**
 * LifeLink - 1-Click Database Installer & Setup Wizard
 * Ideal for student project evaluations & fresh XAMPP installations.
 */
require_once __DIR__ . '/config/database.php';

$message = '';
$status = '';
$installedTables = [];

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    try {
        $rootPdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $sqlPath = __DIR__ . '/database/lifelink_db.sql';
        if (!file_exists($sqlPath)) {
            throw new Exception("SQL schema file not found at " . $sqlPath);
        }

        $sql = file_get_contents($sqlPath);
        $rootPdo->exec($sql);

        // Verify tables created
        $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $stmt = $pdo->query("SHOW TABLES");
        $installedTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $status = 'success';
        $message = "Database 'lifelink_db' and " . count($installedTables) . " tables successfully initialized with seed data!";
    } catch (Exception $e) {
        $status = 'error';
        $message = "Installation failed: " . $e->getMessage();
    }
} else {
    // Check current state
    try {
        $conn = getDBConnection();
        if ($conn) {
            $stmt = $conn->query("SHOW TABLES");
            $installedTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($installedTables)) {
                $status = 'info';
                $message = "Database is already installed with " . count($installedTables) . " tables ready.";
            }
        }
    } catch (Exception $e) {
        $status = 'warning';
        $message = "Database not initialized yet. Click the button below to install.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup & Installation — LifeLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-red: #DC2626;
            --primary-dark: #0B192C;
            --accent-cyan: #0284C7;
            --bg-light: #F8FAFC;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0B192C 0%, #1E3A5F 50%, #0B192C 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
            color: #1e293b;
        }
        .setup-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            max-width: 680px;
            width: 100%;
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, #0B192C, #1E3A5F);
            color: #ffffff;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        .setup-header .badge-pill {
            background: #DC2626;
            color: #fff;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .btn-install {
            background: #DC2626;
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-install:hover {
            background: #B91C1C;
            color: #fff;
            transform: translateY(-1px);
        }
        .demo-box {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 16px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="setup-card">
    <div class="setup-header">
        <div class="mb-2"><i class="fa-solid fa-droplet text-danger fa-3x"></i></div>
        <h2 class="fw-bold mb-1">LifeLink System Setup</h2>
        <p class="text-white-50 mb-2">Online Blood Donor Finder and Management System</p>
        <span class="badge-pill"><i class="fa-solid fa-server me-1"></i> XAMPP MySQL Auto-Setup</span>
    </div>
    <div class="p-4 p-md-5">
        <?php if ($message): ?>
            <div class="alert alert-<?= $status === 'error' ? 'danger' : ($status === 'success' ? 'success' : 'info') ?> d-flex align-items-center mb-4">
                <i class="fa-solid fa-circle-info fa-lg me-3"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
        <?php endif; ?>

        <div class="mb-4">
            <h5 class="fw-bold text-dark"><i class="fa-solid fa-database text-primary me-2"></i>Database Status</h5>
            <p class="text-muted small">
                Target database: <strong>lifelink_db</strong> on <strong><?= DB_HOST ?>:<?= DB_PORT ?></strong> (User: <code><?= DB_USER ?></code>).
            </p>
            <?php if (!empty($installedTables)): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach ($installedTables as $tbl): ?>
                        <span class="badge bg-light text-dark border"><i class="fa-solid fa-table me-1 text-danger"></i><?= htmlspecialchars($tbl) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" class="mb-4">
            <input type="hidden" name="action" value="install">
            <button type="submit" class="btn btn-install w-100 py-3 shadow-sm">
                <i class="fa-solid fa-rocket me-2"></i> <?= empty($installedTables) ? 'Install & Seed Database Now' : 'Re-install / Reset Database' ?>
            </button>
        </form>

        <div class="demo-box mb-4">
            <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-key text-warning me-2"></i>Pre-configured Demo Accounts:</h6>
            <div class="row g-2">
                <div class="col-sm-4">
                    <div class="p-2 border rounded bg-white">
                        <strong class="text-danger d-block">Administrator</strong>
                        <small class="text-muted">admin@lifelink.org</small><br>
                        <code>Admin@123</code>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="p-2 border rounded bg-white">
                        <strong class="text-primary d-block">Donor (O-)</strong>
                        <small class="text-muted">marcus.vance@lifelink.org</small><br>
                        <code>Password@123</code>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="p-2 border rounded bg-white">
                        <strong class="text-success d-block">Requester</strong>
                        <small class="text-muted">sarah.requester@lifelink.org</small><br>
                        <code>Password@123</code>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-between">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-house me-1"></i> Go to Homepage
            </a>
            <a href="login.php" class="btn btn-primary">
                <i class="fa-solid fa-right-to-bracket me-1"></i> Go to Login
            </a>
        </div>
    </div>
</div>
</body>
</html>
