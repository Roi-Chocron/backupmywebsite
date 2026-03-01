<?php
// הגדרות גישה - מחזיר JSON בלבד ומוגדר לקידוד תקין
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// --- פרטי התחברות ל-InfinityFree ---
// שים לב: חובה לעדכן את 4 השורות הבאות עם הפרטים שלך מהפאנל!
$host = "sql300.infinityfree.com"; // החלף ב-MySQL Hostname
$db_name = "if0_40999873_dashboard";   // החלף בשם המסד (כולל הקידומת)
$username = "if0_40999873";        // החלף בשם המשתמש (vPanel Username)
$password = "osbW1p1DV1e4lw";

try {
    // התחברות למסד הנתונים עם הגדרת utf8mb4 החשובה לעברית
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4", $username, $password);
    // הגדרת טיפול בשגיאות
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $exception) {
    echo json_encode(["status" => "error", "message" => "שגיאת התחברות: " . $exception->getMessage()]);
    exit;
}

// משיכת הנתונים ממסד הנתונים
try {
    // משיכת סטטיסטיקות
    $stmtStats = $conn->prepare("SELECT * FROM dashboard_stats WHERE id = 1");
    $stmtStats->execute();
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // משיכת פרויקטים
    $stmtProjects = $conn->prepare("SELECT * FROM projects ORDER BY created_at DESC");
    $stmtProjects->execute();
    $projects = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);

    // טיפול במקרה שהטבלה ריקה
    $visits = $stats ? number_format($stats['total_visits']) : "0";
    $leads = $stats ? $stats['total_leads'] : 0;
    $avgTime = $stats ? $stats['avg_time'] : "0:00";

    // מבנה הנתונים שיוחזר לדף ה-HTML
    $response = [
        "status" => "success",
        "data" => [
            "stats" => [
                "visits" => $visits,
                "leads" => $leads,
                "avgTime" => $avgTime
            ],
            "projects" => $projects,
            "chart" => [120, 190, 150, 280, 220, 310, 250] // נתוני דמו זמניים לגרף
        ]
    ];

    // הדפסת ה-JSON עם תמיכה מלאה בתצוגת עברית
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch(Exception $e) {
    echo json_encode(["status" => "error", "message" => "שגיאה במשיכת הנתונים: " . $e->getMessage()]);
}
?>