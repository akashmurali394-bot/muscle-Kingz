<?php
declare(strict_types=1);
require_once __DIR__ . "/../config.php";
require_admin();
$gymContact = gym_contact_details();

$notice = "";
$error = "";
$allowedStatuses = ["New", "Read", "Replied"];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $error = "Your session expired. Refresh the page and try again.";
    } else {
        $action = is_string($_POST["action"] ?? null) ? $_POST["action"] : "";
        $messageId = filter_var($_POST["message_id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        if (!$messageId) {
            $error = "Select a valid enquiry.";
        } else {
            try {
                $connection = db();
                if ($action === "status") {
                    $status = is_string($_POST["status"] ?? null) ? $_POST["status"] : "";
                    if (!in_array($status, $allowedStatuses, true)) {
                        $error = "Choose a valid enquiry status.";
                    } else {
                        $statement = $connection->prepare("UPDATE messages SET status = :status WHERE id = :id");
                        $statement->execute(["status" => $status, "id" => $messageId]);
                        $notice = $statement->rowCount() >= 0 ? "Enquiry status updated." : "";
                    }
                } elseif ($action === "delete") {
                    $statement = $connection->prepare("DELETE FROM messages WHERE id = :id");
                    $statement->execute(["id" => $messageId]);
                    $notice = $statement->rowCount() === 1 ? "Enquiry deleted." : "That enquiry was not found.";
                } elseif ($action === "reply") {
                    $reply = trim(is_string($_POST["reply"] ?? null) ? $_POST["reply"] : "");
                    if ($reply === "" || character_count($reply) > 5000) {
                        $error = "Enter a reply of no more than 5,000 characters.";
                    } else {
                        $statement = $connection->prepare("SELECT id, name, email FROM messages WHERE id = :id LIMIT 1");
                        $statement->execute(["id" => $messageId]);
                        $message = $statement->fetch();
                        if (!$message) {
                            $error = "That enquiry was not found.";
                        } elseif (send_gym_email(
                            $message["email"],
                            "Reply from " . GYM_NAME,
                            "<p>Hello " . h($message["name"]) . ",</p><p>" . nl2br(h($reply)) . "</p>"
                            . "<p>" . h(GYM_NAME) . "<br>Phone: " . h($gymContact["phone"])
                            . "<br>Email: " . h($gymContact["email"]) . "<br>Address: " . h($gymContact["address"]) . "</p>"
                        )) {
                            $statement = $connection->prepare("UPDATE messages SET admin_reply = :reply, status = 'Replied', replied_at = NOW() WHERE id = :id");
                            $statement->execute(["reply" => $reply, "id" => $messageId]);
                            $notice = "Reply sent and enquiry marked Replied.";
                        } else {
                            $error = "The reply could not be sent. Check Gmail SMTP configuration and server logs; the enquiry was not marked Replied.";
                        }
                    }
                } else {
                    $error = "The requested action is not supported.";
                }
            } catch (RuntimeException | PDOException $exception) {
                error_log("Muscle Kingz enquiry action failed: " . $exception->getMessage());
                $error = "The requested action could not be completed. Please try again.";
            }
        }
    }
}

$search = trim(is_string($_GET["search"] ?? null) ? $_GET["search"] : "");
$filterStatus = is_string($_GET["status"] ?? null) ? $_GET["status"] : "";
if (!in_array($filterStatus, ["", ...$allowedStatuses], true)) {
    $filterStatus = "";
}
$messages = [];
$loadError = "";
try {
    $sql = "SELECT id, name, mobile, email, plan, message, status, admin_reply, created_at, replied_at FROM messages WHERE 1 = 1";
    $parameters = [];
    if ($filterStatus !== "") {
        $sql .= " AND status = :status";
        $parameters["status"] = $filterStatus;
    }
    if ($search !== "") {
        $sql .= " AND (name LIKE :search_name OR email LIKE :search_email OR mobile LIKE :search_mobile OR CAST(id AS CHAR) = :search_id)";
        $needle = "%" . str_replace(["%", "_"], ["\\%", "\\_"], $search) . "%";
        $parameters["search_name"] = $needle;
        $parameters["search_email"] = $needle;
        $parameters["search_mobile"] = $needle;
        $parameters["search_id"] = $search;
    }
    $sql .= " ORDER BY created_at DESC, id DESC LIMIT 100";
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    $messages = $statement->fetchAll();
} catch (RuntimeException | PDOException $exception) {
    error_log("Muscle Kingz enquiries could not be loaded: " . $exception->getMessage());
    $loadError = "Enquiries are temporarily unavailable. Check the database connection.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Enquiries | Muscle Kingz Admin</title>
  <link rel="stylesheet" href="../admin.css">
</head>
<body class="dashboard-body">
<header class="admin-topbar">
  <a class="admin-brand" href="../index.php">MUSCLE <span>KINGZ</span></a>
  <nav class="admin-nav" aria-label="Admin navigation">
    <a href="index.php">Dashboard</a>
    <a class="active" href="messages.php">Enquiries</a>
  </nav>
  <div class="admin-user"><span><?= h((string)$_SESSION["admin_username"]) ?></span><form method="post" action="../logout.php"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button class="logout-button" type="submit">LOG OUT</button></form></div>
</header>
<main class="admin-main">
  <div class="admin-page-heading"><div><p class="admin-eyebrow">MEMBER CONTACTS</p><h1>ENQUIRIES</h1><p>Read, organize and reply to contact form submissions.</p></div><a class="text-link" href="index.php">← Dashboard</a></div>
  <?php if ($notice !== ""): ?><p class="admin-alert admin-alert-success" role="status"><?= h($notice) ?></p><?php endif; ?>
  <?php if ($error !== ""): ?><p class="admin-alert admin-alert-error" role="alert"><?= h($error) ?></p><?php endif; ?>
  <?php if ($loadError !== ""): ?><p class="admin-alert admin-alert-error" role="alert"><?= h($loadError) ?></p><?php endif; ?>
  <form class="filter-bar" method="get" action="messages.php">
    <label for="search">Search enquiries</label>
    <input id="search" type="search" name="search" value="<?= h($search) ?>" placeholder="Name, email, mobile or ID">
    <label for="status-filter">Status</label>
    <select id="status-filter" name="status">
      <option value="">All statuses</option>
      <?php foreach ($allowedStatuses as $status): ?><option value="<?= h($status) ?>" <?= $filterStatus === $status ? "selected" : "" ?>><?= h($status) ?></option><?php endforeach; ?>
    </select>
    <button class="admin-button" type="submit">FILTER</button>
  </form>
  <?php if (!$messages && $loadError === ""): ?>
    <section class="admin-panel empty-state"><strong>No enquiries found</strong><p>Try changing the filters or wait for a new contact form submission.</p></section>
  <?php endif; ?>
  <div class="message-list">
    <?php foreach ($messages as $message): ?>
      <article class="message-card">
        <header class="message-card-heading">
          <div><p class="admin-eyebrow">ENQUIRY #<?= (int)$message["id"] ?> · <?= h($message["created_at"]) ?></p><h2><?= h($message["name"]) ?></h2></div>
          <span class="status-badge status-<?= strtolower(h($message["status"])) ?>"><?= h($message["status"]) ?></span>
        </header>
        <div class="message-contact">
          <a href="mailto:<?= h($message["email"]) ?>"><?= h($message["email"]) ?></a>
          <a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $message["mobile"])) ?>"><?= h($message["mobile"]) ?></a>
          <span>Plan: <?= h($message["plan"] ?: "Not selected") ?></span>
        </div>
        <div class="visitor-message"><?= nl2br(h($message["message"])) ?></div>
        <?php if ($message["admin_reply"]): ?><details class="previous-reply"><summary>Previous reply (<?= h($message["replied_at"] ?? "") ?>)</summary><p><?= nl2br(h($message["admin_reply"])) ?></p></details><?php endif; ?>
        <div class="message-actions">
          <form method="post" action="messages.php" class="status-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="message_id" value="<?= (int)$message["id"] ?>">
            <label for="status-<?= (int)$message["id"] ?>">Status</label>
            <select id="status-<?= (int)$message["id"] ?>" name="status"><?php foreach ($allowedStatuses as $status): ?><option value="<?= h($status) ?>" <?= $message["status"] === $status ? "selected" : "" ?>><?= h($status) ?></option><?php endforeach; ?></select>
            <button class="admin-button admin-button-secondary" type="submit">UPDATE</button>
          </form>
          <form method="post" action="messages.php" class="reply-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="message_id" value="<?= (int)$message["id"] ?>">
            <label for="reply-<?= (int)$message["id"] ?>">Reply by email</label>
            <textarea id="reply-<?= (int)$message["id"] ?>" name="reply" rows="3" maxlength="5000" placeholder="Write a reply to <?= h($message["name"]) ?>" required></textarea>
            <button class="admin-button" type="submit">SEND EMAIL REPLY</button>
          </form>
          <form method="post" action="messages.php" class="delete-form" onsubmit="return confirm('Delete this enquiry permanently?');">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="message_id" value="<?= (int)$message["id"] ?>">
            <button class="admin-button admin-button-danger" type="submit">DELETE</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if (count($messages) === 100): ?><p class="admin-hint">Showing the latest 100 matching enquiries.</p><?php endif; ?>
</main>
</body>
</html>
