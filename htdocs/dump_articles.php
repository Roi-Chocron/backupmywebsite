<?php
// הגדרות גישה 
header("Content-Type: application/json; charset=UTF-8");

// פרטי התחברות ל-InfinityFree 
$host = "sql300.infinityfree.com";
$db_name = "if0_40999873_dashboard";
$username = "if0_40999873";
$password = "osbW1p1DV1e4lw";

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("SELECT id, title, left(content, 200) as excerpt, image_url, created_at, views, interested_count, not_interested_count FROM articles ORDER BY created_at DESC");
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    file_put_contents('articles_dump.json', json_encode(["status" => "success", "articles" => $articles], JSON_UNESCAPED_UNICODE));
    echo "SUCCESS";
} catch (PDOException $exception) {
    echo "ERROR: " . $exception->getMessage();
}
?>