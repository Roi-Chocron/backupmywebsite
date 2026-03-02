<?php
// הגדרות גישה 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// פרטי התחברות ל-InfinityFree 
$host = "sql300.infinityfree.com";
$db_name = "if0_40999873_dashboard";
$username = "if0_40999873";
$password = "osbW1p1DV1e4lw";

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $exception) {
    echo json_encode(["status" => "error", "message" => "Connection failed: " . $exception->getMessage()]);
    exit;
}

// Ensure tables exist
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS articles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        image_url VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        views INT DEFAULT 0,
        interested_count INT DEFAULT 0,
        not_interested_count INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $conn->exec("CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        article_id INT NOT NULL,
        author_name VARCHAR(100) NOT NULL,
        content TEXT NOT NULL,
        likes INT DEFAULT 0,
        dislikes INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Table creation failed: " . $e->getMessage()]);
    exit;
}

// Helper to get POST JSON data
$input = json_decode(file_get_contents('php://input'), true);

$action = $_GET['action'] ?? $input['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && $action === 'get_articles') {
    // Auto sync from FTP uploaded json
    $sync_file = 'sync_articles.json';
    if (file_exists($sync_file)) {
        $json_data = file_get_contents($sync_file);
        $articles_to_sync = json_decode($json_data, true);
        if (is_array($articles_to_sync)) {
            foreach ($articles_to_sync as $art) {
                $stmtCheck = $conn->prepare("SELECT id FROM articles WHERE title = :title");
                $stmtCheck->execute(['title' => $art['title']]);
                if (!$stmtCheck->fetch()) {
                    $stmtIns = $conn->prepare("INSERT INTO articles (title, content, image_url) VALUES (:title, :content, :image_url)");
                    $stmtIns->execute([
                        'title' => $art['title'],
                        'content' => $art['content'],
                        'image_url' => $art['image_url'] ?? ''
                    ]);
                }
            }
        }
        @unlink($sync_file);
    }

    $stmt = $conn->prepare("SELECT id, title, left(content, 200) as excerpt, image_url, created_at, views, interested_count, not_interested_count FROM articles ORDER BY created_at DESC");
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["status" => "success", "articles" => $articles], JSON_UNESCAPED_UNICODE);

} elseif ($method === 'GET' && $action === 'get_article') {
    $id = $_GET['id'] ?? 0;

    $stmt = $conn->prepare("SELECT * FROM articles WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($article) {
        $stmtComments = $conn->prepare("SELECT * FROM comments WHERE article_id = :id ORDER BY created_at DESC");
        $stmtComments->execute(['id' => $id]);
        $comments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["status" => "success", "article" => $article, "comments" => $comments], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(["status" => "error", "message" => "Article not found"]);
    }

} elseif ($method === 'POST' && $action === 'add_article') {
    $title = $input['title'] ?? '';
    $content = $input['content'] ?? '';
    $image_url = $input['image_url'] ?? '';

    if ($title && $content) {
        $stmt = $conn->prepare("INSERT INTO articles (title, content, image_url) VALUES (:title, :content, :image_url)");
        $stmt->execute(['title' => $title, 'content' => $content, 'image_url' => $image_url]);
        echo json_encode(["status" => "success", "id" => $conn->lastInsertId()]);
    } else {
        echo json_encode(["status" => "error", "message" => "Missing title or content"]);
    }

} elseif ($method === 'POST' && $action === 'add_comment') {
    $article_id = $input['article_id'] ?? 0;
    $author_name = $input['author_name'] ?? 'אנונימי';
    $content = $input['content'] ?? '';

    if ($article_id && $content) {
        $stmt = $conn->prepare("INSERT INTO comments (article_id, author_name, content) VALUES (:article_id, :author_name, :content)");
        $stmt->execute(['article_id' => $article_id, 'author_name' => $author_name, 'content' => $content]);
        echo json_encode(["status" => "success", "id" => $conn->lastInsertId()]);
    } else {
        echo json_encode(["status" => "error", "message" => "Missing data"]);
    }

} elseif ($method === 'POST' && $action === 'vote_article') {
    $article_id = $input['article_id'] ?? 0;
    $vote_type = $input['vote_type'] ?? ''; // 'interested' or 'not_interested'

    if ($article_id && ($vote_type === 'interested' || $vote_type === 'not_interested')) {
        $column = $vote_type === 'interested' ? 'interested_count' : 'not_interested_count';
        $stmt = $conn->prepare("UPDATE articles SET $column = $column + 1 WHERE id = :id");
        $stmt->execute(['id' => $article_id]);
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid data"]);
    }

} elseif ($method === 'POST' && $action === 'vote_comment') {
    $comment_id = $input['comment_id'] ?? 0;
    $vote_type = $input['vote_type'] ?? ''; // 'like' or 'dislike'

    if ($comment_id && ($vote_type === 'like' || $vote_type === 'dislike')) {
        $column = $vote_type === 'like' ? 'likes' : 'dislikes';
        $stmt = $conn->prepare("UPDATE comments SET $column = $column + 1 WHERE id = :id");
        $stmt->execute(['id' => $comment_id]);
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid data"]);
    }
} elseif ($method === 'POST' && $action === 'increment_view') {
    $article_id = $input['article_id'] ?? 0;
    if ($article_id) {
        $stmt = $conn->prepare("UPDATE articles SET views = views + 1 WHERE id = :id");
        $stmt->execute(['id' => $article_id]);
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid article ID"]);
    }
} elseif ($method === 'POST' && $action === 'update_article_image') {
    $article_id = $input['article_id'] ?? 0;
    $image_url = $input['image_url'] ?? '';
    if ($article_id && $image_url) {
        $stmt = $conn->prepare("UPDATE articles SET image_url = :image_url WHERE id = :id");
        $stmt->execute(['id' => $article_id, 'image_url' => $image_url]);
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid data"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid action or method"]);
}
?>