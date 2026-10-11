<?php
declare(strict_types=1);
require_once __DIR__ . "/config.php";

// Verify session is active
$inactivityLimit = 1800; // 30 minutes
if (isset($_SESSION["admin_last_active"]) && (time() - (int)$_SESSION["admin_last_active"] > $inactivityLimit)) {
    unset($_SESSION["admin_id"], $_SESSION["admin_username"], $_SESSION["admin_last_active"], $_SESSION["must_change_password"]);
    session_destroy();
    header("Location: " . APP_BASE_PATH . "/login.php?expired=1");
    exit;
}
$_SESSION["admin_last_active"] = time();

if (!isset($_SESSION["admin_id"], $_SESSION["admin_username"])) {
    header("Location: " . APP_BASE_PATH . "/login.php");
    exit;
}

$adminId = (int)$_SESSION["admin_id"];
$adminUsername = (string)$_SESSION["admin_username"];
$isFirstTime = !empty($_SESSION["must_change_password"]);

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $currentPassword = is_string($_POST["current_password"] ?? null) ? $_POST["current_password"] : "";
    $newPassword = is_string($_POST["new_password"] ?? null) ? $_POST["new_password"] : "";
    $confirmPassword = is_string($_POST["confirm_password"] ?? null) ? $_POST["confirm_password"] : "";

    if (!csrf_is_valid()) {
        http_response_code(400);
        $error = "Your session expired. Refresh the page and try again.";
    } elseif ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
        $error = "Please fill in all required password fields.";
    } elseif (strlen($newPassword) < 12 || strlen($newPassword) > 200) {
        $error = "New password must be between 12 and 200 characters.";
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $error = "New password must include at least one uppercase letter (A-Z).";
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $error = "New password must include at least one lowercase letter (a-z).";
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $error = "New password must include at least one number (0-9).";
    } elseif (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
        $error = "New password must include at least one special symbol (!@#$%^&*...).";
    } elseif (!hash_equals($newPassword, $confirmPassword)) {
        $error = "Password confirmation does not match.";
    } else {
        try {
            $connection = db();
            $statement = $connection->prepare("SELECT id, password_hash FROM admins WHERE id = :id LIMIT 1");
            $statement->execute(["id" => $adminId]);
            $admin = $statement->fetch();

            if (!$admin || !password_verify($currentPassword, $admin["password_hash"])) {
                $error = "Your current temporary password is incorrect.";
            } elseif (password_verify($newPassword, $admin["password_hash"])) {
                $error = "Your new permanent password must be different from your temporary password.";
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $connection->prepare(
                    "UPDATE admins SET password_hash = :hash, must_change_password = 0, password_changed_at = NOW() WHERE id = :id"
                );
                $update->execute([
                    "hash" => $newHash,
                    "id" => $adminId
                ]);

                // Clear one-time password restriction
                unset($_SESSION["must_change_password"]);
                session_regenerate_id(true);

                header("Location: admin/index.php?password_changed=1");
                exit;
            }
        } catch (RuntimeException | PDOException $exception) {
            error_log("Muscle Kingz change password error: " . $exception->getMessage());
            $error = "Could not update password. Please check the database connection.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isFirstTime ? "Set Permanent Password" : "Change Password" ?> | Muscle Kingz Gym</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-auth-body">
  <main class="auth-card" style="width: min(100%, 480px);">
    <a class="admin-brand" href="index.php">MUSCLE <span>KINGZ</span></a>
    
    <?php if ($isFirstTime): ?>
      <p class="admin-eyebrow">FIRST-TIME SECURITY REQUIREMENT</p>
      <h1>SET PERMANENT PASSWORD</h1>
      <p class="auth-description">
        Welcome, <strong><?= h($adminUsername) ?></strong>! For security, you must replace your temporary one-time password with a strong permanent password before accessing the admin dashboard.
      </p>
      <div class="admin-alert admin-alert-info">
        🔒 You will not be able to access enquiries or gym settings until your permanent password is set.
      </div>
    <?php else: ?>
      <p class="admin-eyebrow">ACCOUNT SECURITY</p>
      <h1>CHANGE PASSWORD</h1>
      <p class="auth-description">
        Update your administrator credentials for account: <strong><?= h($adminUsername) ?></strong>.
      </p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
      <p class="admin-alert admin-alert-error" role="alert"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="post" action="change_password.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

      <label for="current_password"><?= $isFirstTime ? "Current Temporary Password" : "Current Password" ?></label>
      <input id="current_password" name="current_password" type="password" autocomplete="current-password" required autofocus>

      <label for="new_password">New Permanent Password</label>
      <input id="new_password" name="new_password" type="password" minlength="12" maxlength="200" autocomplete="new-password" required>

      <label for="confirm_password">Confirm New Password</label>
      <input id="confirm_password" name="confirm_password" type="password" minlength="12" maxlength="200" autocomplete="new-password" required>

      <div style="background: #181818; border: 1px solid #333; padding: 12px 16px; margin: 16px 0; font-size: 11px; color: #aaa;">
        <strong style="color: #ddd;">Password Requirements:</strong>
        <ul style="margin: 6px 0 0; padding-left: 18px; line-height: 1.6;">
          <li>At least 12 characters</li>
          <li>At least one uppercase letter (A-Z)</li>
          <li>At least one lowercase letter (a-z)</li>
          <li>At least one number (0-9)</li>
          <li>At least one special character (!@#$%^&*...)</li>
          <li>Must differ from temporary password</li>
        </ul>
      </div>

      <button class="admin-button" type="submit">SAVE PERMANENT PASSWORD</button>
    </form>

    <?php if (!$isFirstTime): ?>
      <a class="auth-back-link" href="admin/index.php">← Back to Dashboard</a>
    <?php else: ?>
      <form method="post" action="logout.php" style="margin-top: 15px;">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <button type="submit" class="auth-back-link" style="background: none; border: none; padding: 0; cursor: pointer; color: var(--muted);">
          Sign out and return later
        </button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>

