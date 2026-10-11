<?php
declare(strict_types=1);
require_once __DIR__ . "/config.php";

$error = "";
$info = "";
$username = "";

// Check for notices
if (isset($_GET["expired"]) && $_GET["expired"] === "1") {
    $info = "Your session expired after 30 minutes of inactivity. Please sign in again.";
} elseif (isset($_GET["logged_out"]) && $_GET["logged_out"] === "1") {
    $info = "You have been securely signed out.";
} elseif (isset($_GET["password_reset"]) && $_GET["password_reset"] === "1") {
    $info = "Your password was successfully updated. Please sign in with your new permanent password.";
}

// IP-based & Session-based Rate Limiting helpers
function get_client_ip(): string
{
    $ip = $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : "127.0.0.1";
}

function get_ip_failed_attempts(): array
{
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "mkz_login_" . hash("sha256", get_client_ip()) . ".json";
    if (!is_file($file)) {
        return [];
    }
    $content = @file_get_contents($file);
    if ($content === false) {
        return [];
    }
    $data = @json_decode($content, true);
    if (!is_array($data)) {
        return [];
    }
    $now = time();
    return array_values(array_filter($data, fn($t) => is_int($t) && ($now - $t) < 600));
}

function record_ip_failed_attempt(): void
{
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "mkz_login_" . hash("sha256", get_client_ip()) . ".json";
    $attempts = get_ip_failed_attempts();
    $attempts[] = time();
    @file_put_contents($file, json_encode($attempts), LOCK_EX);
}

function clear_ip_failed_attempts(): void
{
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "mkz_login_" . hash("sha256", get_client_ip()) . ".json";
    if (is_file($file)) {
        @unlink($file);
    }
}

// Clean session login attempts (older than 10 minutes)
$now = time();
$sessionAttempts = array_values(array_filter(
    $_SESSION["login_attempts"] ?? [],
    fn($t) => is_int($t) && ($now - $t) < 600
));
$_SESSION["login_attempts"] = $sessionAttempts;

$ipAttempts = get_ip_failed_attempts();
$totalAttempts = max(count($sessionAttempts), count($ipAttempts));

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim(is_string($_POST["username"] ?? null) ? $_POST["username"] : "");
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";

    if ($totalAttempts >= 5) {
        $error = "Too many failed login attempts. For security, please wait 10 minutes before trying again.";
    } elseif (!csrf_is_valid()) {
        http_response_code(400);
        $error = "Your session expired. Refresh the page and try again.";
    } elseif ($username === "" || $password === "" || strlen($username) > 50) {
        $error = "Enter a valid username and password.";
    } else {
        try {
            $connection = db();
            ensure_admin_schema($connection);
            $statement = $connection->prepare(
                "SELECT id, username, password_hash, must_change_password FROM admins WHERE username = :username LIMIT 1"
            );
            $statement->execute(["username" => $username]);
            $admin = $statement->fetch();

            if ($admin && password_verify($password, $admin["password_hash"])) {
                session_regenerate_id(true);
                $_SESSION["admin_id"] = (int)$admin["id"];
                $_SESSION["admin_username"] = $admin["username"];
                $_SESSION["admin_last_active"] = time();

                clear_ip_failed_attempts();
                unset($_SESSION["csrf_token"], $_SESSION["login_attempts"]);

                if (!empty($admin["must_change_password"])) {
                    $_SESSION["must_change_password"] = true;
                    header("Location: change_password.php");
                    exit;
                }

                unset($_SESSION["must_change_password"]);
                header("Location: admin/index.php");
                exit;
            }

            // Failed login
            record_ip_failed_attempt();
            $_SESSION["login_attempts"][] = $now;
            $newTotal = $totalAttempts + 1;
            $remaining = max(0, 5 - $newTotal);

            $error = "The username or password was not recognized." . ($remaining <= 2 && $remaining > 0 ? " ($remaining attempt(s) remaining before temporary lockout)" : "");
        } catch (RuntimeException | PDOException $exception) {
            error_log("Muscle Kingz login error: " . $exception->getMessage());
            $error = "Admin sign-in is temporarily unavailable. Please check the database connection.";
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
    <p class="admin-eyebrow">SECURE ADMIN PORTAL</p>
    <h1>ADMIN LOGIN</h1>
    <p class="auth-description">Sign in to manage gym enquiries and website messages.</p>

    <?php if ($info !== ""): ?>
      <p class="admin-alert admin-alert-info" role="status"><?= h($info) ?></p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
      <p class="admin-alert admin-alert-error" role="alert"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="post" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <label for="username">Username</label>
      <input id="username" name="username" type="text" maxlength="50" autocomplete="username" value="<?= h($username) ?>" required autofocus>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      <button class="admin-button" type="submit">SIGN IN</button>
    </form>
    <a class="auth-back-link" href="index.php">← Return to website</a>
  </main>
</body>
</html>
