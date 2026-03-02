<?php
session_start();

// --- Configuration ---
$host = "sql300.infinityfree.com";
$db_name = "if0_40999873_dashboard";
$username = "if0_40999873";
$password = "osbW1p1DV1e4lw";
$admin_pass = "roi1234"; // Admin dashboard password
$worker_secret = "bot_worker_secret_key_2024"; // Shared secret for the local Python worker

// Allow cross-origin requests (needed for the local worker)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: X-Worker-Secret, Content-Type");
header("Content-Type: application/json; charset=UTF-8");

try {
    $conn = new PDO("mysql:host={$host};dbname={$db_name};charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Connection failed: " . $e->getMessage()]);
    exit;
}

// --- Auto-initialize Table on every request (safe, only creates if not exists) ---
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS bot_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bot_name VARCHAR(255) NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        output LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Silently fail - table may already exist, which is fine
}

// --- Request Handling ---
$action = $_GET['action'] ?? '';

// Check if it's an authenticated worker request (via secret key)
$is_worker = (($_SERVER['HTTP_X_WORKER_SECRET'] ?? '') === $worker_secret) ||
    (($_POST['worker_secret'] ?? '') === $worker_secret);

// Authentication: worker actions need worker secret, admin actions need session
$worker_actions = ['worker_poll', 'worker_update'];
$public_actions = ['login'];

if (in_array($action, $worker_actions)) {
    if (!$is_worker) {
        echo json_encode(["status" => "error", "message" => "Unauthorized worker request"]);
        exit;
    }
} elseif (!in_array($action, $public_actions)) {
    if (!isset($_SESSION['admin_logged_in'])) {
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit;
    }
}

switch ($action) {
    case 'login':
        $pass = $_POST['password'] ?? '';
        if ($pass === $admin_pass) {
            $_SESSION['admin_logged_in'] = true;
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid password"]);
        }
        break;

    case 'logout':
        session_destroy();
        echo json_encode(["status" => "success"]);
        break;

    case 'list_jobs':
        $stmt = $conn->prepare("SELECT * FROM bot_jobs ORDER BY created_at DESC LIMIT 30");
        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "jobs" => $jobs], JSON_UNESCAPED_UNICODE);
        break;

    case 'trigger_bot':
        $botName = $_POST['bot_name'] ?? '';
        if (empty($botName)) {
            echo json_encode(["status" => "error", "message" => "Bot name required"]);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO bot_jobs (bot_name, status) VALUES (?, 'pending')");
        $stmt->execute([$botName]);
        echo json_encode(["status" => "success", "job_id" => $conn->lastInsertId()]);
        break;

    case 'get_status':
        $jobId = $_GET['job_id'] ?? 0;
        $stmt = $conn->prepare("SELECT * FROM bot_jobs WHERE id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "job" => $job], JSON_UNESCAPED_UNICODE);
        break;

    case 'worker_poll':
        // Worker fetches next pending job
        $stmt = $conn->prepare("SELECT * FROM bot_jobs WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1");
        $stmt->execute();
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($job) {
            // Mark as running immediately to prevent double pickup
            $upd = $conn->prepare("UPDATE bot_jobs SET status = 'running' WHERE id = ?");
            $upd->execute([$job['id']]);
            echo json_encode(["status" => "success", "job" => $job], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(["status" => "empty"]);
        }
        break;

    case 'worker_update':
        $jobId = $_POST['job_id'] ?? 0;
        $status = $_POST['status'] ?? 'completed';
        $output = $_POST['output'] ?? '';
        $stmt = $conn->prepare("UPDATE bot_jobs SET status = ?, output = ? WHERE id = ?");
        $stmt->execute([$status, $output, $jobId]);
        echo json_encode(["status" => "success"]);
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Unknown action: {$action}"]);
        break;
}
?>