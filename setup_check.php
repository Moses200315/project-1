<?php
/**
 * MealKit – Setup Verification Script
 * =====================================
 * Visit: http://localhost/mealkit/setup_check.php
 * Remove this file before deploying to production.
 *
 * Checks:
 *  - PHP version
 *  - Required extensions
 *  - Directory permissions
 *  - Database connectivity
 *  - Configuration values
 */

$allowedIPs = ["127.0.0.1", "::1", "::ffff:127.0.0.1"];
if (!in_array($_SERVER["REMOTE_ADDR"] ?? "", $allowedIPs, true)) {
    http_response_code(403);
    die("Access denied.");
}

define("ROOT_PATH", __DIR__);
define("DS", DIRECTORY_SEPARATOR);
// APP_VERSION is defined inside config/config.php — do NOT define it here.

// Minimal config load for checks
$configFile = ROOT_PATH . DS . "config" . DS . "config.php";
$checks = [];

function chk(string $label, bool $pass, string $info = ""): array
{
    return ["label" => $label, "pass" => $pass, "info" => $info];
}

// PHP Version
$checks[] = chk(
    "PHP Version ≥ 8.0",
    PHP_VERSION_ID >= 80000,
    "Current: " . PHP_VERSION,
);

// Extensions
foreach (
    ["pdo", "pdo_mysql", "json", "mbstring", "fileinfo", "openssl"]
    as $ext
) {
    $checks[] = chk("PHP ext: {$ext}", extension_loaded($ext));
}

// Writable directories
foreach (["uploads/recipes", "uploads/profiles", "uploads/pdfs"] as $dir) {
    $path = ROOT_PATH . DS . str_replace("/", DS, $dir);
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $checks[] = chk("Writable: {$dir}", is_writable($path));
}

// Config file
$checks[] = chk("config/config.php exists", file_exists($configFile));

// Database check
$dbPass = false;
$dbInfo = "";
if (file_exists($configFile)) {
    try {
        require_once $configFile;
        $pdo = new PDO(
            sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                DB_HOST,
                DB_PORT,
                DB_NAME,
            ),
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $dbPass = count($tables) >= 10;
        $dbInfo = count($tables) . " tables found in " . DB_NAME;
    } catch (PDOException $e) {
        $dbInfo = "Connection failed: " . $e->getMessage();
    }
    $checks[] = chk("Database connected & seeded", $dbPass, $dbInfo);
}

$allPass = array_reduce($checks, fn($c, $i) => $c && $i["pass"], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>MealKit – Setup Check</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container" style="max-width:680px;padding:2rem 1rem;">
  <div class="text-center mb-4">
    <div style="font-size:3rem;">🍽️</div>
    <h2 class="fw-bold">MealKit Setup Check</h2>
    <p class="text-muted">v<?= APP_VERSION ?> · <?= date("d M Y H:i") ?></p>
  </div>

  <?php if ($allPass): ?>
  <div class="alert alert-success fw-semibold text-center">
    ✅ All checks passed! MealKit is ready.
    <div class="mt-2"><a href="<?= defined("APP_URL")
        ? APP_URL
        : "/mealkit/" ?>" class="btn btn-success btn-sm me-2">Open App</a>
    <a href="<?= defined("APP_URL")
        ? APP_URL . "/auth/login"
        : "/mealkit/auth/login" ?>" class="btn btn-outline-success btn-sm">Login</a></div>
  </div>
  <?php else: ?>
  <div class="alert alert-warning fw-semibold text-center">⚠️ Some checks failed – see details below.</div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm">
    <table class="table mb-0">
      <thead class="table-dark"><tr><th>Check</th><th>Result</th><th>Info</th></tr></thead>
      <tbody>
        <?php foreach ($checks as $c): ?>
        <tr>
          <td class="small fw-semibold"><?= htmlspecialchars(
              $c["label"],
          ) ?></td>
          <td><?= $c["pass"]
              ? '<span class="badge bg-success">✓ Pass</span>'
              : '<span class="badge bg-danger">✗ Fail</span>' ?></td>
          <td class="small text-muted"><?= htmlspecialchars($c["info"]) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (!$dbPass): ?>
  <div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
      <h6 class="fw-bold">Database Setup Steps</h6>
      <ol class="small mb-0">
        <li>Start Apache &amp; MySQL in XAMPP Control Panel</li>
        <li>Open <a href="http://localhost/phpmyadmin" target="_blank">phpMyAdmin</a></li>
        <li>Create a database named <code><?= defined("DB_NAME")
            ? DB_NAME
            : "mealkit_db" ?></code></li>
        <li>Select the database → click <strong>Import</strong> → choose <code>database/mealkit.sql</code></li>
        <li>Visit <a href="http://localhost/mealkit/database/seed.php" target="_blank">seed.php</a> to load demo data</li>
        <li>Return here and refresh to verify</li>
      </ol>
    </div>
  </div>
  <?php endif; ?>

  <p class="text-center text-muted small mt-4">
    ⚠️ <strong>Delete <code>setup_check.php</code></strong> before going to production.
  </p>
</div>
</body></html>
