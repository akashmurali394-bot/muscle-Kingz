<?php
declare(strict_types=1);
require_once __DIR__ . "/config.php";

// CLI Execution Handler
if (PHP_SAPI === "cli") {
    fwrite(STDOUT, "=== Muscle Kingz Gym: Initial Admin Setup ===\n");
    try {
        $connection = db();
        ensure_admin_schema($connection);
        $adminCount = (int)$connection->query("SELECT COUNT(*) FROM admins")->fetchColumn();
        if ($adminCount > 0) {
            fwrite(STDERR, "[ERROR] Setup is permanently locked. An admin account already exists.\n");
            fwrite(STDERR, "To prevent unauthorized changes, no additional accounts can be created.\n");
            exit(1);
        }

        $username = generate_unique_admin_username();
        $tempPassword = generate_secure_temporary_password(18);
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

        $statement = $connection->prepare(
            "INSERT INTO admins (username, password_hash, must_change_password) VALUES (:username, :password_hash, 1)"
        );
        $statement->execute([
            "username" => $username,
            "password_hash" => $passwordHash
        ]);

        fwrite(STDOUT, "\n[SUCCESS] Administrator account successfully created!\n");
        fwrite(STDOUT, "------------------------------------------------------\n");
        fwrite(STDOUT, "Username:           " . $username . "\n");
        fwrite(STDOUT, "Temporary Password: " . $tempPassword . "\n");
        fwrite(STDOUT, "Must Change Pass:   YES (on first login)\n");
        fwrite(STDOUT, "------------------------------------------------------\n");
        fwrite(STDOUT, "[CRITICAL SECURITY INSTRUCTIONS]\n");
        fwrite(STDOUT, "1. Copy and save these credentials in your password manager right now.\n");
        fwrite(STDOUT, "2. They are displayed ONCE and cannot be recovered.\n");
        fwrite(STDOUT, "3. Sign in at /login.php and create your new permanent password.\n");
        fwrite(STDOUT, "4. Delete or disable setup_admin.php on your server.\n\n");
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "[ERROR] Setup failed: " . $e->getMessage() . "\n");
        exit(1);
    }
}

// Web Execution Handler
$error = "";
$dbConnected = false;
$setupLocked = false;
$credentialsGenerated = false;
$generatedUsername = "";
$generatedPassword = "";

$requiredSetupKey = env_value("GYM_SETUP_KEY");

try {
    $connection = db();
    ensure_admin_schema($connection);
    $dbConnected = true;
    $adminCount = (int)$connection->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if ($adminCount > 0) {
        $setupLocked = true;
    }
} catch (RuntimeException | PDOException $exception) {
    $error = "Database connection is unavailable. Verify that GYM_DB_HOST, GYM_DB_NAME, GYM_DB_USER, and GYM_DB_PASSWORD are configured in your server environment or gym-website-secrets.php.";
}

if (!$setupLocked && $dbConnected && $_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $error = "Your session expired. Refresh the page and try again.";
    } elseif ($requiredSetupKey !== "" && !hash_equals($requiredSetupKey, (string)($_POST["setup_key"] ?? ""))) {
        $error = "The setup authorization key is invalid.";
    } else {
        $connection = db();
        $lock = $connection->query("SELECT GET_LOCK('muscle_kingz_admin_setup', 10)")->fetchColumn();
        if ((int)$lock !== 1) {
            $error = "Setup is currently busy. Please try again.";
        } else {
            try {
                $recheckCount = (int)$connection->query("SELECT COUNT(*) FROM admins")->fetchColumn();
                if ($recheckCount > 0) {
                    $setupLocked = true;
                } else {
                    $generatedUsername = generate_unique_admin_username();
                    $generatedPassword = generate_secure_temporary_password(18);
                    $passwordHash = password_hash($generatedPassword, PASSWORD_DEFAULT);

                    $statement = $connection->prepare(
                        "INSERT INTO admins (username, password_hash, must_change_password) VALUES (:username, :password_hash, 1)"
                    );
                    $statement->execute([
                        "username" => $generatedUsername,
                        "password_hash" => $passwordHash
                    ]);

                    $credentialsGenerated = true;
                }
            } catch (PDOException $exception) {
                error_log("Muscle Kingz admin setup failed: " . $exception->getMessage());
                $error = "The admin account could not be initialized. Please check server logs.";
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
  <title>One-Time Admin Setup | Muscle Kingz Gym</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-auth-body">
  <main class="auth-card" style="<?= $credentialsGenerated ? 'width: min(100%, 540px);' : '' ?>">
    <a class="admin-brand" href="index.php">MUSCLE <span>KINGZ</span></a>

    <?php if ($setupLocked): ?>
      <p class="admin-eyebrow">SETUP COMPLETED &amp; DISABLED</p>
      <h1>SETUP LOCKED</h1>
      <p class="admin-alert admin-alert-info">
        An administrator account is already configured. For security reasons, the initial account creation process has been permanently disabled.
      </p>
      <p class="auth-description">
        If you are the website administrator, please proceed to the secure login page with your credentials.
      </p>
      <a class="admin-button admin-button-link" href="login.php">GO TO ADMIN LOGIN</a>
      <p class="admin-hint" style="margin-top: 18px;">
        Security best practice: You can now safely delete or remove <code>setup_admin.php</code> from your web server.
      </p>

    <?php elseif ($credentialsGenerated): ?>
      <p class="admin-eyebrow">ONE-TIME INITIAL CREDENTIALS</p>
      <h1>SAVE YOUR LOGIN</h1>
      
      <div class="admin-alert admin-alert-error" style="border-left: 4px solid var(--red); background: #260d10; color: #ffccd0;">
        <strong>⚠️ CRITICAL ONE-TIME DISPLAY:</strong><br>
        Save these credentials securely right now. For your security, this page will <strong>NEVER</strong> display them again. Once you reload or leave this page, setup is permanently locked.
      </div>

      <div class="credential-display-group">
        <label for="display-username" style="font-weight: 800; color: #bbb; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Admin Username</label>
        <div class="credential-row">
          <input id="display-username" type="text" readonly value="<?= h($generatedUsername) ?>" class="credential-input">
          <button type="button" class="admin-button admin-button-secondary copy-btn" onclick="copyValue('display-username', this)">COPY</button>
        </div>

        <label for="display-password" style="font-weight: 800; color: #bbb; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Temporary Password</label>
        <div class="credential-row">
          <input id="display-password" type="text" readonly value="<?= h($generatedPassword) ?>" class="credential-input credential-input-password">
          <button type="button" class="admin-button admin-button-secondary copy-btn" onclick="copyValue('display-password', this)">COPY</button>
        </div>
      </div>

      <div class="setup-instructions-box">
        <strong style="color: #fff; text-transform: uppercase; letter-spacing: .5px;">Next Steps:</strong>
        <ol>
          <li>Copy and paste your username and password into a secure password manager.</li>
          <li>Click the button below to open the Admin Login.</li>
          <li>Sign in using these temporary credentials.</li>
          <li>You will be immediately prompted to create your new permanent password.</li>
          <li>Delete or disable <code>setup_admin.php</code> from your server.</li>
        </ol>
      </div>

      <a class="admin-button" href="login.php" style="width: 100%; text-align: center; text-decoration: none;">PROCEED TO ADMIN LOGIN →</a>

    <?php else: ?>
      <p class="admin-eyebrow">FIRST-TIME WEBSITE SETUP</p>
      <h1>INITIALIZE ADMIN</h1>
      <p class="auth-description">
        Generate your secure administrator account. The system will automatically create a unique username and a strong temporary password.
      </p>

      <?php if ($error !== ""): ?>
        <p class="admin-alert admin-alert-error" role="alert"><?= h($error) ?></p>
      <?php endif; ?>

      <?php if ($dbConnected): ?>
        <form method="post" action="setup_admin.php">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          
          <?php if ($requiredSetupKey !== ""): ?>
            <label for="setup_key">Setup Authorization Key</label>
            <input id="setup_key" name="setup_key" type="password" autocomplete="off" placeholder="Enter GYM_SETUP_KEY" required>
            <p class="admin-hint">This deployment requires a master setup authorization key configured in server environment variables or gym-website-secrets.php.</p>
          <?php endif; ?>

          <div style="background: #181818; border: 1px solid #333; padding: 14px; margin: 18px 0; font-size: 12px; color: #ccc;">
            <strong style="color: #fff;">Important:</strong>
            <ul style="margin: 6px 0 0; padding-left: 18px; line-height: 1.6;">
              <li>Credentials will be generated automatically and displayed <strong>once</strong>.</li>
              <li>You must change your temporary password on your first login.</li>
              <li>Setup will be permanently locked after creation.</li>
            </ul>
          </div>

          <button class="admin-button" type="submit">GENERATE ONE-TIME ADMIN CREDENTIALS</button>
        </form>
      <?php else: ?>
        <p class="admin-hint">Configure your database connection to proceed with setup.</p>
      <?php endif; ?>

    <?php endif; ?>

    <a class="auth-back-link" href="index.php">← Return to website</a>
  </main>

  <script>
    function copyValue(elementId, btn) {
      const input = document.getElementById(elementId);
      if (!input) return;
      input.select();
      input.setSelectionRange(0, 99999);
      navigator.clipboard.writeText(input.value).then(() => {
        const originalText = btn.textContent;
        btn.textContent = "COPIED! ✓";
        btn.style.borderColor = "#25D366";
        btn.style.color = "#25D366";
        setTimeout(() => {
          btn.textContent = originalText;
          btn.style.borderColor = "";
          btn.style.color = "";
        }, 2000);
      }).catch(err => {
        console.error("Copy failed", err);
      });
    }
  </script>
</body>
</html>
