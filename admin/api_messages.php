<?php
declare(strict_types=1);
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once __DIR__ . "/../config.php";

// Admin access check
if (!isset($_SESSION["admin_id"], $_SESSION["admin_username"]) || !empty($_SESSION["must_change_password"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$sinceId = filter_var($_GET["since_id"] ?? 0, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]) ?: 0;

try {
    $connection = db();
    
    // Check for newly inserted enquiries
    $statement = $connection->prepare(
        "SELECT id, name, mobile, email, plan, message, status, created_at 
         FROM messages 
         WHERE id > :since_id 
         ORDER BY id DESC 
         LIMIT 20"
    );
    $statement->execute(["since_id" => $sinceId]);
    $newMessages = $statement->fetchAll();

    // Current unread count
    $unreadCount = (int)$connection->query("SELECT COUNT(*) FROM messages WHERE status = 'New'")->fetchColumn();
    $totalCount = (int)$connection->query("SELECT COUNT(*) FROM messages")->fetchColumn();

    echo json_encode([
        "success" => true,
        "unread_count" => $unreadCount,
        "total_count" => $totalCount,
        "new_messages" => $newMessages,
        "timestamp" => time()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database check failed"
    ]);
}

