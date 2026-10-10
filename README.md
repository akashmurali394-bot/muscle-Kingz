# Muscle Kingz Gym

PHP 8.1+ gym website with MySQL enquiry storage, a password-protected admin panel, editable business contact settings, and Gmail SMTP notifications through PHPMailer.

The default public business details are **Muscle Kingz Gym**, **9940185140**, **akashmurali394@gmail.com**, and **Muscle Kingz Gym, Mappadhu**. Phone and email are linked for direct calls and messages. An administrator can update the phone, public email, and address from the dashboard; the public email is also the default enquiry-notification recipient unless `GYM_NOTIFICATION_EMAIL` is explicitly set.

## XAMPP setup

1. Extract this project into `C:\xampp\htdocs\gym-website`.
2. In the XAMPP Control Panel, start **Apache** and **MySQL**.
3. Confirm the PHP extensions `pdo_mysql` and `openssl` are enabled in the PHP version used by Apache. Restart Apache after changing `php.ini`.
4. Open `http://localhost/phpmyadmin/`, create a database named `muscle_kingz`, select it, then import `database.sql`. It creates the `admins`, `messages`, and `gym_settings` tables and inserts the initial business contact details without overwriting existing settings.
5. Configure the database environment variables for the Apache process:

   - `GYM_DB_HOST` (usually `127.0.0.1` for XAMPP)
   - `GYM_DB_NAME` (`muscle_kingz` for the database created above)
   - `GYM_DB_USER` (required; use `root` for a standard fresh XAMPP install)
   - `GYM_DB_PASSWORD` (set this if your MySQL account has a password; blank is accepted for a default local XAMPP install)

   On Windows, you can set variables for future processes with PowerShell, for example:

   ```powershell
   setx GYM_DB_HOST "127.0.0.1"
   setx GYM_DB_NAME "muscle_kingz"
   setx GYM_DB_USER "root"
   setx GYM_DB_PASSWORD "your-local-mysql-password"
   ```

   Close and reopen the XAMPP Control Panel after using `setx`, then start Apache again. Never put production credentials in PHP files inside the public web directory or public source control.

6. Install Composer if it is not already installed. From a terminal in `C:\xampp\htdocs\gym-website`, run:

   ```powershell
   composer install --no-dev --prefer-dist
   ```

   This installs PHPMailer into the generated `vendor` directory. The project requires PHP 8.1 or later and PHP's `openssl` extension for Gmail SMTP.

7. Open `http://localhost/gym-website/setup_admin.php`, create your first admin account using a unique username and a strong password of at least 12 characters, then **delete or disable `setup_admin.php` immediately**. The setup page also disables account creation after the first admin exists.
8. Open `http://localhost/gym-website/login.php` and sign in. The dashboard is at `http://localhost/gym-website/admin/`.

## Optional Gmail notifications and email replies

Use a Gmail account with 2-Step Verification enabled and create a **Google App Password** for this website. Use that generated app password only; never use the account's normal Gmail password. Set these environment variables for the Apache process and restart Apache:

- `GYM_GMAIL_USERNAME` — the sending Gmail address
- `GYM_GMAIL_APP_PASSWORD` — the Google-generated App Password
- `GYM_NOTIFICATION_EMAIL` — the gym inbox that receives new-enquiry notifications
- `GYM_SMTP_HOST` — optional; defaults to `smtp.gmail.com`
- `GYM_GMAIL_FROM` — optional; defaults to `GYM_GMAIL_USERNAME`
- `GYM_GMAIL_FROM_NAME` — optional; defaults to `Muscle Kingz Gym`

PHPMailer connects to Gmail using authenticated STARTTLS on port 587. The default notification recipient is `akashmurali394@gmail.com` (or the public email currently saved in admin settings); set `GYM_NOTIFICATION_EMAIL` only if notifications should go to a different inbox. Enquiry data is committed to MySQL before notification is attempted, so a mail delivery failure does not discard the visitor's enquiry or prevent the saved-enquiry confirmation. Delivery errors are written to the PHP server error log, not exposed to visitors. The contact form sets the visitor's address as the notification's Reply-To address. Admin email replies are sent by PHPMailer from the configured gym account.

Example PowerShell commands (replace the example values; do not commit real values):

```powershell
setx GYM_GMAIL_USERNAME "akashmurali394@gmail.com"
setx GYM_GMAIL_APP_PASSWORD "your-google-app-password"
setx GYM_NOTIFICATION_EMAIL "akashmurali394@gmail.com"
setx GYM_GMAIL_FROM "akashmurali394@gmail.com"
```

If `setx` is used, reopen the XAMPP Control Panel so Apache inherits the new environment. On a production server, configure these variables in the web-server/service environment or a protected secrets manager. If the host does not expose environment variables to PHP, `config.php` can read `gym-website-secrets.php` one directory above the web document root. Keep that file outside the served directory and out of source control.

## Test the setup

1. Submit the contact form at `http://localhost/gym-website/contact.php`.
2. Confirm the saved enquiry appears under **Enquiries** in the admin panel.
3. When Gmail variables and PHPMailer are configured, confirm the gym inbox receives the notification and that replying to it targets the visitor.
4. Use the admin enquiry actions to set New, Read, or Replied, send an email reply, and delete a test enquiry.

If the database is unavailable, form submissions show a safe error and are not reported as saved. The public site displays the configured gym phone, email, and address; no unprovided social profiles, opening hours, postal code, or map location are shown.

## Production hosting checklist

- Use paid PHP/MySQL hosting for a public enquiry site. Confirm the selected plan runs PHP 8.1+, enables `pdo_mysql` and `openssl`, allows Composer or uploading `vendor/`, and permits outbound SMTP on port 587. External SMTP rules vary by provider and plan.
- Create the production database and user in the hosting panel. The included `database.sql` intentionally does not create a database: select the new database in phpMyAdmin before importing, then set `GYM_DB_NAME` to the exact provider-assigned name (it may include an account prefix).
- For an existing production installation, import the updated SQL to add `gym_settings`; the `INSERT IGNORE` seed preserves any saved contact settings. Back up the database first, and do not use a schema import as a substitute for future migrations.
- Set `GYM_DB_HOST`, `GYM_DB_NAME`, `GYM_DB_USER`, and `GYM_DB_PASSWORD` in the hosting environment. If unavailable, create `gym-website-secrets.php` one directory above the web document root. It must return an array with those keys and, optionally, `GYM_GMAIL_USERNAME`, `GYM_GMAIL_APP_PASSWORD`, `GYM_NOTIFICATION_EMAIL`, `GYM_GMAIL_FROM`, and `GYM_GMAIL_FROM_NAME` keys. Environment variables take precedence. Never place this file inside a public directory or source control.

  ```php
  <?php
  return [
      "GYM_DB_HOST" => "database-host-from-provider",
      "GYM_DB_NAME" => "provider-assigned-database",
      "GYM_DB_USER" => "provider-assigned-user",
      "GYM_DB_PASSWORD" => "your-unique-database-password",
      "GYM_GMAIL_USERNAME" => "akashmurali394@gmail.com",
      "GYM_GMAIL_APP_PASSWORD" => "your-google-app-password",
      "GYM_NOTIFICATION_EMAIL" => "akashmurali394@gmail.com"
  ];
  ```

- The root `.htaccess` disables directory listing, denies direct requests for configuration/schema/Composer files and `vendor/`, and redirects HTTP to HTTPS. Enable the hosting SSL certificate first. If the provider handles HTTPS redirects itself or uses a reverse proxy, follow its HTTPS setting and disable or adjust the `.htaccess` redirect if you see a redirect loop.
- In the hosting PHP settings, set `display_errors=Off` and `log_errors=On`; review server logs privately during troubleshooting. Do not show SQL, SMTP, or stack-trace details to visitors.
- Import `database.sql` for initial setup only. It uses `CREATE TABLE IF NOT EXISTS`, but do not re-import it as a substitute for schema migrations; back up the database before schema changes.
- Run `composer install --no-dev --prefer-dist` locally and upload `vendor/`, or run Composer on the server. Keep `composer.lock` and deploy its matching `vendor/` dependencies. Delete `setup_admin.php` immediately after creating the first admin.
