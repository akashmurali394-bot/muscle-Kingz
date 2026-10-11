<?php
declare(strict_types=1);
require_once __DIR__ . "/../config.php";
require_admin();

$stats = ["total" => 0, "new" => 0, "today" => 0];
$recentMessages = [];
$gymContact = GYM_CONTACT_DEFAULTS;
$settingsNotice = "";
$settingsError = "";
$loadError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save_gym_settings") {
    $phone = trim(is_string($_POST["phone"] ?? null) ? $_POST["phone"] : "");
    $email = trim(is_string($_POST["email"] ?? null) ? $_POST["email"] : "");
    $address = trim(is_string($_POST["address"] ?? null) ? $_POST["address"] : "");
    if (!csrf_is_valid()) {
        http_response_code(400);
        $settingsError = "Your session expired. Refresh the page and try again.";
    } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $settingsError = "Enter a valid gym phone number.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $settingsError = "Enter a valid gym email address.";
    } elseif ($address === "" || character_count($address) > 255) {
        $settingsError = "Enter a gym address of up to 255 characters.";
    } else {
        try {
            $statement = db()->prepare(
                "INSERT INTO gym_settings (id, contact_phone, contact_email, address)
                 VALUES (1, :phone, :email, :address)
                 ON DUPLICATE KEY UPDATE contact_phone = VALUES(contact_phone),
                    contact_email = VALUES(contact_email), address = VALUES(address)"
            );
            $statement->execute(["phone" => $phone, "email" => $email, "address" => $address]);
            $gymContact = ["phone" => $phone, "email" => $email, "address" => $address];
            $settingsNotice = "Business contact details saved.";
        } catch (RuntimeException | PDOException $exception) {
            error_log("Muscle Kingz business contact settings could not be saved: " . $exception->getMessage());
            $settingsError = "The business contact details could not be saved. Check the database connection.";
        }
    }
}

try {
    $connection = db();
    if ($settingsNotice === "") {
        $gymContact = gym_contact_details($connection);
    }
    $stats["total"] = (int)$connection->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    $stats["new"] = (int)$connection->query("SELECT COUNT(*) FROM messages WHERE status = 'New'")->fetchColumn();
    $stats["today"] = (int)$connection->query("SELECT COUNT(*) FROM messages WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY")->fetchColumn();
    $recentMessages = $connection->query("SELECT id, name, email, plan, status, created_at FROM messages ORDER BY created_at DESC, id DESC LIMIT 8")->fetchAll();
} catch (RuntimeException | PDOException $exception) {
    error_log("Muscle Kingz dashboard data load failed: " . $exception->getMessage());
    $loadError = "Dashboard data is temporarily unavailable. Check the database connection.";
}
if ($settingsError !== "" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $gymContact = [
        "phone" => trim(is_string($_POST["phone"] ?? null) ? $_POST["phone"] : $gymContact["phone"]),
        "email" => trim(is_string($_POST["email"] ?? null) ? $_POST["email"] : $gymContact["email"]),
        "address" => trim(is_string($_POST["address"] ?? null) ? $_POST["address"] : $gymContact["address"])
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | Muscle Kingz Admin</title>
  <link rel="stylesheet" href="../admin.css">
</head>
<body class="dashboard-body">
<header class="admin-topbar">
  <a class="admin-brand" href="../index.php">MUSCLE <span>KINGZ</span></a>
  <nav class="admin-nav" aria-label="Admin navigation">
    <a class="active" href="index.php">Dashboard</a>
    <a href="messages.php">Enquiries</a>
    <a href="#business-settings">Business details</a>
  </nav>
  <div class="admin-user">
    <a href="../change_password.php" title="Change Password" style="color:#aaa;text-decoration:none;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:5px;">
      <span>🔑</span> <?= h((string)$_SESSION["admin_username"]) ?>
    </a>
    <form method="post" action="../logout.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <button class="logout-button" type="submit">LOG OUT</button>
    </form>
  </div>
</header>
<main class="admin-main">
  <?php if (isset($_GET["password_changed"]) && $_GET["password_changed"] === "1"): ?>
    <div class="admin-alert admin-alert-success" style="margin-bottom: 24px;">
      ✓ Permanent password set successfully! Your admin account is now fully secured.
    </div>
  <?php endif; ?>
  <div class="admin-page-heading">
    <div>
      <div style="display:flex;align-items:center;gap:15px;">
        <p class="admin-eyebrow" style="margin:0;">MUSCLE KINGZ GYM</p>
        <span class="live-indicator"><span class="live-dot"></span> Live Real-Time</span>
      </div>
      <h1 style="margin:6px 0 0;">OVERVIEW</h1>
      <p>Review recent member enquiries and follow up with prospects.</p>
    </div>
    <a class="admin-button admin-button-link" href="messages.php">MANAGE ENQUIRIES <span aria-hidden="true">→</span></a>
  </div>
  <div id="new-messages-alert" class="new-enquiry-banner" onclick="window.location.reload();">
    <span>🔔 <strong id="new-messages-count">1</strong> new customer enquiry received!</span>
    <span style="text-decoration:underline;">Refresh Overview ↻</span>
  </div>
  <?php if ($loadError !== ""): ?><p class="admin-alert admin-alert-error" role="alert"><?= h($loadError) ?></p><?php endif; ?>
  <section class="stat-grid" aria-label="Enquiry statistics">
    <article class="stat-card"><span>TOTAL ENQUIRIES</span><strong><?= $stats["total"] ?></strong><small>All saved submissions</small></article>
    <article class="stat-card"><span>NEW ENQUIRIES</span><strong><?= $stats["new"] ?></strong><small>Waiting for review</small></article>
    <article class="stat-card"><span>RECEIVED TODAY</span><strong><?= $stats["today"] ?></strong><small>Since today's midnight</small></article>
  </section>
  <section class="admin-panel">
    <div class="panel-heading"><div><p class="admin-eyebrow">LATEST SUBMISSIONS</p><h2>RECENT ENQUIRIES</h2></div><a class="text-link" href="messages.php">View all enquiries →</a></div>
    <?php if (!$recentMessages && $loadError === ""): ?>
      <div class="empty-state"><strong>No enquiries yet</strong><p>New contact form submissions will appear here.</p></div>
    <?php elseif ($recentMessages): ?>
      <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>ENQUIRY</th><th>CONTACT</th><th>PLAN</th><th>STATUS</th><th>RECEIVED</th></tr></thead>
        <tbody><?php foreach ($recentMessages as $message): ?>
          <tr>
            <td><a class="message-link" href="messages.php?search=<?= urlencode((string)$message["id"]) ?>">#<?= (int)$message["id"] ?> · <?= h($message["name"]) ?></a></td>
            <td><a href="mailto:<?= h($message["email"]) ?>"><?= h($message["email"]) ?></a></td>
            <td><?= h($message["plan"] ?: "Not selected") ?></td>
            <td><span class="status-badge status-<?= strtolower(h($message["status"])) ?>"><?= h($message["status"]) ?></span></td>
            <td><?= h($message["created_at"]) ?></td>
          </tr>
        <?php endforeach; ?></tbody>
      </table></div>
    <?php endif; ?>
  </section>
  <section class="admin-panel" id="business-settings">
    <div class="panel-heading"><div><p class="admin-eyebrow">PUBLIC BUSINESS INFORMATION</p><h2>GYM CONTACT SETTINGS</h2></div></div>
    <?php if ($settingsNotice !== ""): ?><p class="admin-alert admin-alert-success" role="status"><?= h($settingsNotice) ?></p><?php endif; ?>
    <?php if ($settingsError !== ""): ?><p class="admin-alert admin-alert-error" role="alert"><?= h($settingsError) ?></p><?php endif; ?>
    <p class="business-contact-preview">
      <strong><?= h(GYM_NAME) ?></strong> ·
      <a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $gymContact["phone"])) ?>"><?= h($gymContact["phone"]) ?></a> ·
      <a href="mailto:<?= h($gymContact["email"]) ?>"><?= h($gymContact["email"]) ?></a> ·
      <?= h($gymContact["address"]) ?>
    </p>
    <form class="business-settings-form" method="post" action="index.php#business-settings">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_gym_settings">
      <div><label for="gym-phone">Public phone</label><input id="gym-phone" name="phone" type="tel" maxlength="20" value="<?= h($gymContact["phone"]) ?>" required></div>
      <div><label for="gym-email">Public email / enquiry recipient</label><input id="gym-email" name="email" type="email" maxlength="254" value="<?= h($gymContact["email"]) ?>" required></div>
      <div><label for="gym-address">Gym address</label><input id="gym-address" name="address" type="text" maxlength="255" value="<?= h($gymContact["address"]) ?>" required></div>
      <button class="admin-button" type="submit">SAVE BUSINESS DETAILS</button>
    </form>
    <p class="admin-hint">These details appear on the public site and in enquiry notifications. An explicitly configured GYM_NOTIFICATION_EMAIL environment variable overrides the notification recipient.</p>
  </section>
</main>
<script>
  let latestId = <?= !empty($recentMessages) ? (int)$recentMessages[0]["id"] : 0 ?>;
  function checkOverviewNewMessages() {
    fetch('api_messages.php?since_id=' + latestId)
      .then(res => {
        if (!res.ok) throw new Error('Status ' + res.status);
        return res.json();
      })
      .then(data => {
        if (data.success && data.new_messages && data.new_messages.length > 0) {
          const banner = document.getElementById('new-messages-alert');
          const count = document.getElementById('new-messages-count');
          if (banner && count) {
            count.textContent = data.new_messages.length;
            banner.style.display = 'flex';
          }
        }
      })
      .catch(err => console.debug('Polling check:', err));
  }
  // Check for new customer enquiries every 8 seconds
  setInterval(checkOverviewNewMessages, 8000);
</script>
</body>
</html>
