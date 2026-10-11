<?php
declare(strict_types=1);

$https = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";
ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
session_set_cookie_params([
    "httponly" => true,
    "secure" => $https,
    "samesite" => "Lax"
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$scriptPath = str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "/");
$lastSlash = strrpos($scriptPath, "/");
$scriptDirectory = $lastSlash === false ? "" : substr($scriptPath, 0, $lastSlash);
if (basename($scriptDirectory) === "admin") {
    $lastSlash = strrpos($scriptDirectory, "/");
    $scriptDirectory = $lastSlash === false ? "" : substr($scriptDirectory, 0, $lastSlash);
}
define("APP_BASE_PATH", $scriptDirectory);
define("GYM_NAME", "Muscle Kingz Gym");
define("GYM_CONTACT_DEFAULTS", [
    "phone" => "9940185140",
    "email" => "akashmurali394@gmail.com",
    "address" => "Muscle Kingz Gym, Mappadhu"
]);

$privateSettings = [];
$documentRoot = realpath(is_string($_SERVER["DOCUMENT_ROOT"] ?? null) ? $_SERVER["DOCUMENT_ROOT"] : "");
if ($documentRoot !== false) {
    $privateConfigFile = dirname($documentRoot) . DIRECTORY_SEPARATOR . "gym-website-secrets.php";
    if (is_file($privateConfigFile)) {
        $loadedSettings = require $privateConfigFile;
        if (!is_array($loadedSettings)) {
            error_log("Muscle Kingz private configuration must return an array.");
            throw new RuntimeException("Private configuration is invalid.");
        }
        foreach ($loadedSettings as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                error_log("Muscle Kingz private configuration contains invalid values.");
                throw new RuntimeException("Private configuration is invalid.");
            }
        }
        $privateSettings = $loadedSettings;
    }
}
$GLOBALS["gym_private_settings"] = $privateSettings;

function env_value(string $name, string $default = ""): string
{
    $value = getenv($name);
    if ($value !== false) {
        return trim($value);
    }
    $settings = $GLOBALS["gym_private_settings"] ?? [];
    $privateValue = $settings[$name] ?? $default;
    return is_string($privateValue) ? trim($privateValue) : $default;
}

function gym_contact_details(?PDO $connection = null): array
{
    $details = GYM_CONTACT_DEFAULTS;
    if (env_value("GYM_DB_HOST") === "" || env_value("GYM_DB_NAME") === "" || env_value("GYM_DB_USER") === "") {
        return $details;
    }

    try {
        $connection = $connection ?? db();
        $storedDetails = $connection->query(
            "SELECT contact_phone, contact_email, address FROM gym_settings WHERE id = 1"
        )->fetch();
        if ($storedDetails) {
            foreach (["phone" => "contact_phone", "email" => "contact_email", "address" => "address"] as $key => $column) {
                if (is_string($storedDetails[$column] ?? null) && $storedDetails[$column] !== "") {
                    $details[$key] = $storedDetails[$column];
                }
            }
        }
    } catch (RuntimeException | PDOException $exception) {
        error_log("Muscle Kingz business contact settings could not be loaded: " . $exception->getMessage());
    }
    return $details;
}

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = env_value("GYM_DB_HOST");
    $name = env_value("GYM_DB_NAME");
    $user = env_value("GYM_DB_USER");
    $password = env_value("GYM_DB_PASSWORD");
    if ($host === "" || $name === "" || $user === "") {
        throw new RuntimeException("Database host, name, and user must be configured.");
    }
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

    try {
        $connection = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (PDOException $exception) {
        error_log("Muscle Kingz database connection failed: " . $exception->getMessage());
        throw new RuntimeException("The database is temporarily unavailable.", 0, $exception);
    }
    return $connection;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function character_count(string $value): int
{
    $count = preg_match_all('/./us', $value);
    return $count === false ? strlen($value) : $count;
}

function csrf_token(): string
{
    if (!isset($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrf_is_valid(): bool
{
    $provided = $_POST["csrf_token"] ?? "";
    return is_string($provided)
        && isset($_SESSION["csrf_token"])
        && is_string($_SESSION["csrf_token"])
        && hash_equals($_SESSION["csrf_token"], $provided);
}

function require_admin(): void
{
    if (!isset($_SESSION["admin_id"], $_SESSION["admin_username"])) {
        header("Location: " . APP_BASE_PATH . "/login.php");
        exit;
    }
}

function send_gym_email(string $recipient, string $subject, string $htmlBody, ?string $replyTo = null): bool
{
    $autoload = __DIR__ . "/vendor/autoload.php";
    if (!is_file($autoload)) {
        error_log("Muscle Kingz email not sent: PHPMailer is not installed.");
        return false;
    }
    $host = env_value("GYM_SMTP_HOST", "smtp.gmail.com");
    $username = env_value("GYM_GMAIL_USERNAME");
    $appPassword = str_replace(" ", "", env_value("GYM_GMAIL_APP_PASSWORD"));
    $fromAddress = env_value("GYM_GMAIL_FROM", $username);
    $fromName = env_value("GYM_GMAIL_FROM_NAME", "Muscle Kingz Gym");

    if ($host === "" || $username === "" || $appPassword === "" || $fromAddress === "") {
        error_log("Muscle Kingz email not sent: SMTP environment configuration is incomplete.");
        return false;
    }

    try {
        require_once $autoload;
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $appPassword;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = "UTF-8";
        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($recipient);
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = trim(html_entity_decode(strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $htmlBody)), ENT_QUOTES, "UTF-8"));
        $mail->send();
        return true;
    } catch (Throwable $exception) {
        error_log("Muscle Kingz email delivery failed (" . get_class($exception) . ").");
        return false;
    }
}
