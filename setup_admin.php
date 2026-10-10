<?php
declare(strict_types=1);
require_once __DIR__ . "/config.php";

$error = "";
$complete = false;
$username = "";
try {
    $complete = (int)db()->query("SELECT COUNT(*) FROM admins")->fetchColumn() > 0;
} catch (RuntimeException | PDOException $exception) {
    $error = "Database connection is unavailable. Check the server configuration.";
}

if (!$complete && $error === "" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim(is_string($_POST["username"] ?? null) ? $_POST["username"] : "");
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";
    $confirmPassword = is_string($_POST["confirm_password"] ?? null) ? $_POST["confirm_password"] : "";

    if (!csrf_is_valid()) {
        http_response_code(400);
        $error = "Your session expired. Refresh the page and try again.";
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $error = "Use 3–50 letters, numbers, dots, underscores or hyphens for the username.";
    } elseif (strlen($password) < 12 || strlen($password) > 200) {
        $error = "Use a password between 12 and 200 characters.";
    } elseif (!hash_equals($password, $confirmPassword)) {
        $error = "The password confirmation does not match.";
    } else {
        $connection = db();
        $lock = $connection->query("SELECT GET_LOCK('muscle_kingz_admin_setup', 10)")->fetchColumn();
        if ((int)$lock !== 1) {
            $error = "Admin setup is busy. Please try again.";
        } else {
            try {
                if ((int)$connection->query("SELECT COUNT(*) FROM admins")->fetchColumn() > 0) {
                    $complete = true;
                } else {
                    $statement = $connection->prepare("INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)");
                    $statement->execute([
                        "username" => $username,
                        "password_hash" => password_hash($password, PASSWORD_DEFAULT)
                    ]);
                    $complete = true;
                }
            } catch (PDOException $exception) {
                error_log("Muscle Kingz admin setup failed: " . $exception->getMessage());
                $error = "The admin account could not be created. Check the username and try again.";
            } finally {
                $connection->query("SELECT RELEASE_LOCK('muscle_kingz_admin_setup')");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>First-Time Admin Setup | Muscle Kingz Gym</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-auth-body">
  <main class="auth-card">
    <a class="admin-brand" href="index.php">MUSCLE <span>KINGZ</span></a>
    <p class="admin-eyebrow">ONE-TIME ACCOUNT SETUP</p>
    <h1>CREATE ADMIN</h1>
    <?php if ($complete): ?>
      <p class="admin-alert admin-alert-success">An admin account exists, or setup completed. Disable or remove <code>setup_admin.php</code> now.</p>
      <a class="admin-button admin-button-link" href="login.php">GO TO ADMIN LOGIN</a>
    <?php else: ?>
      <p class="auth-description">Choose a strong password with at least 12 characters.</p>
      <?php if ($error !== ""): ?><p class="admin-alert admin-alert-error" role="alert"><?= h($error) ?></p><?php endif; ?>
      <form method="post" action="setup_admin.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" autocomplete="username" value="<?= h($username) ?>" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" minlength="12" maxlength="200" autocomplete="new-password" required>
        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="12" maxlength="200" autocomplete="new-password" required>
        <button class="admin-button" type="submit">CREATE FIRST ADMIN</button>
      </form>
    <?php endif; ?>
    <a class="auth-back-link" href="index.php">← Return to website</a>
  </main>
</body>
</html>
