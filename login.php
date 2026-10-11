<?php
declare(strict_types=1);
require_once __DIR__ . "/config.php";

$error = "";
$username = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim(is_string($_POST["username"] ?? null) ? $_POST["username"] : "");
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";

    if (!csrf_is_valid()) {
        http_response_code(400);
        $error = "Your session expired. Refresh the page and try again.";
    } elseif ($username === "" || $password === "" || strlen($username) > 50) {
        $error = "Enter a valid username and password.";
    } else {
        try {
            $statement = db()->prepare("SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1");
            $statement->execute(["username" => $username]);
            $admin = $statement->fetch();
            if ($admin && password_verify($password, $admin["password_hash"])) {
                session_regenerate_id(true);
                $_SESSION["admin_id"] = (int)$admin["id"];
                $_SESSION["admin_username"] = $admin["username"];
                unset($_SESSION["csrf_token"]);
                header("Location: admin/index.php");
                exit;
            }
            $error = "The username or password was not recognized.";
        } catch (RuntimeException | PDOException $exception) {
            $error = "Admin sign-in is temporarily unavailable. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Muscle Kingz Gym</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-auth-body">
  <main class="auth-card">
    <a class="admin-brand" href="index.php">MUSCLE <span>KINGZ</span></a>
    <p class="admin-eyebrow">SECURE ADMIN AREA</p>
    <h1>ADMIN LOGIN</h1>
    <p class="auth-description">Sign in to manage gym enquiries.</p>
    <?php if ($error !== ""): ?><p class="admin-alert admin-alert-error" role="alert"><?= h($error) ?></p><?php endif; ?>
    <form method="post" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <label for="username">Username</label>
      <input id="username" name="username" type="text" maxlength="50" autocomplete="username" value="<?= h($username) ?>" required>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      <button class="admin-button" type="submit">SIGN IN</button>
    </form>
    <a class="auth-back-link" href="index.php">← Return to website</a>
  </main>
</body>
</html>
