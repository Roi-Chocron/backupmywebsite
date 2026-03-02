<?php
// הגדרות גישה 
header("Content-Type: application/json; charset=UTF-8");

$host = "sql300.infinityfree.com";
$db_name = "if0_40999873_dashboard";
$username = "if0_40999873";
$password = "osbW1p1DV1e4lw";

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sync_file = 'sync_fixes.json';
    if (file_exists($sync_file)) {
        $data = json_decode(file_get_contents($sync_file), true);
        if ($data && isset($data['updates'])) {
            foreach ($data['updates'] as $update) {
                $stmt = $conn->prepare("UPDATE articles SET image_url = :image_url WHERE id = :id");
                $stmt->execute(['id' => $update['article_id'], 'image_url' => $update['image_url']]);
            }
            unlink($sync_file);
            echo "SUCCESS: Processed " . count($data['updates']) . " updates.";
        } else {
            echo "ERROR: Invalid JSON or no updates found.";
        }
    } else {
        echo "ERROR: File sync_fixes.json not found.";
    }
} catch (PDOException $exception) {
    echo "ERROR: " . $exception->getMessage();
}
?>