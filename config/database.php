<?php
/**
 * LifeLink - Database Configuration & Connection Handler
 * Supports automatic database initialization if lifelink_db is missing in XAMPP.
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lifelink_db');
define('DB_PORT', '3306');

// Base URL helper for absolute assets and links
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
// Find base path of /lifeLink
$uriParts = explode('/', trim($_SERVER['SCRIPT_NAME'] ?? '', '/'));
$baseFolder = (isset($uriParts[0]) && strtolower($uriParts[0]) === 'lifelink') ? '/lifeLink' : '';
if (!defined('BASE_URL')) {
    define('BASE_URL', $protocol . $host . $baseFolder);
}

/**
 * Returns a PDO database connection instance.
 * Automatically attempts to create the database if missing.
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        // Auto-heal schema for screening, verification notes, and consent columns if tables exist
        try {
            $pdo->exec("ALTER TABLE `donors` ADD COLUMN IF NOT EXISTS `screening_completed` TINYINT(1) DEFAULT 0");
            $pdo->exec("ALTER TABLE `donors` ADD COLUMN IF NOT EXISTS `screening_answers` TEXT NULL");
            $pdo->exec("ALTER TABLE `donors` ADD COLUMN IF NOT EXISTS `screening_date` TIMESTAMP NULL");
            $pdo->exec("ALTER TABLE `donors` ADD COLUMN IF NOT EXISTS `verification_notes` TEXT NULL");
            $pdo->exec("ALTER TABLE `request_responses` ADD COLUMN IF NOT EXISTS `consent_reviewed` TINYINT(1) DEFAULT 1");
            $pdo->exec("ALTER TABLE `request_responses` ADD COLUMN IF NOT EXISTS `consent_interest` TINYINT(1) DEFAULT 1");
            $pdo->exec("ALTER TABLE `request_responses` ADD COLUMN IF NOT EXISTS `consent_medical_confirmation` TINYINT(1) DEFAULT 1");
            $pdo->exec("ALTER TABLE `request_responses` ADD COLUMN IF NOT EXISTS `consent_contact_sharing` TINYINT(1) DEFAULT 1");
            $pdo->exec("ALTER TABLE `request_responses` ADD COLUMN IF NOT EXISTS `consent_date` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
            $pdo->exec("ALTER TABLE `blood_requests` ADD COLUMN IF NOT EXISTS `hospital_confirmed` TINYINT(1) DEFAULT 0");
            $pdo->exec("ALTER TABLE `blood_requests` ADD COLUMN IF NOT EXISTS `hospital_confirmed_at` TIMESTAMP NULL");
            $pdo->exec("ALTER TABLE `blood_requests` ADD COLUMN IF NOT EXISTS `hospital_notes` TEXT NULL");

            // Password resets table for secure token-based workflow (Phase 1)
            $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `token_hash` VARCHAR(64) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `used_at` DATETIME NULL,
                INDEX `idx_token_hash` (`token_hash`),
                CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $schemaEx) {}

        return $pdo;
    } catch (PDOException $e) {
        // If database does not exist (Error 1049), attempt auto-creation
        if ($e->getCode() == 1049) {
            try {
                $rootPdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS);
                $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

                // Execute SQL script
                $sqlPath = __DIR__ . '/../database/lifelink_db.sql';
                if (file_exists($sqlPath)) {
                    $sql = file_get_contents($sqlPath);
                    $rootPdo->exec($sql);
                }

                $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                return $pdo;
            } catch (Exception $initEx) {
                die("LifeLink Database Initialization Error: " . htmlspecialchars($initEx->getMessage()) . "<br>Please ensure MySQL is running in XAMPP.");
            }
        } else {
            // Cannot connect to MySQL at all
            return null;
        }
    }
}

// Global PDO instance shortcut
$db = getDBConnection();
