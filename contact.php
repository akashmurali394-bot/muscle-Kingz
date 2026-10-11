<?php
require_once __DIR__ . "/config.php";
$pageTitle = "Contact Us | Muscle Kingz Gym";
$gymContact = gym_contact_details();
$success = "";
$errors = [];
$name = $mobile = $email = $messageText = "";
$availablePlans = ["Monthly Plan", "Quarterly Plan", "Yearly Plan"];
$selectedPlan = trim(is_string($_GET["plan"] ?? null) ? $_GET["plan"] : "");
if (!in_array($selectedPlan, $availablePlans, true)) {
    $selectedPlan = "";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim(is_string($_POST["name"] ?? null) ? $_POST["name"] : "");
    $mobile = trim(is_string($_POST["mobile"] ?? null) ? $_POST["mobile"] : "");
    $email = trim(is_string($_POST["email"] ?? null) ? $_POST["email"] : "");
    $messageText = trim(is_string($_POST["message"] ?? null) ? $_POST["message"] : "");
    $selectedPlan = trim(is_string($_POST["plan"] ?? null) ? $_POST["plan"] : "");
    $trap = trim(is_string($_POST["gym_trap"] ?? null) ? $_POST["gym_trap"] : "");

    // Anti-spam validation
    if ($trap !== "") {
        $errors[] = "Bot activity detected.";
    }

    $lastSubmitTime = (int)($_SESSION["last_contact_time"] ?? 0);
    if (time() - $lastSubmitTime < 3) {
        $errors[] = "Please wait a few seconds before submitting again.";
    }

    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = "Your session expired. Refresh the page and try again.";
    } else {
        if ($name === "" || character_count($name) > 100) {
            $errors[] = "Please enter your name (up to 100 characters).";
        }
        if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $mobile)) {
            $errors[] = "Please enter a valid mobile number.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $errors[] = "Please enter a valid email address.";
        }
        if ($messageText === "" || character_count($messageText) > 2000) {
            $errors[] = "Please enter a message (up to 2,000 characters).";
        }
        if ($selectedPlan !== "" && !in_array($selectedPlan, $availablePlans, true)) {
            $errors[] = "Please choose a valid membership plan.";
        }
    }

    if (!$errors) {
        try {
            $statement = db()->prepare(
                "INSERT INTO messages (name, mobile, email, plan, message) VALUES (:name, :mobile, :email, :plan, :message)"
            );
            $statement->execute([
                "name" => $name,
                "mobile" => $mobile,
                "email" => $email,
                "plan" => $selectedPlan === "" ? null : $selectedPlan,
                "message" => $messageText
            ]);
            $messageId = (int)db()->lastInsertId();
            $_SESSION["last_contact_time"] = time();

            // Optional email notification through Gmail SMTP
            $notificationAddress = env_value("GYM_NOTIFICATION_EMAIL", $gymContact["email"]);
            if ($notificationAddress !== "" && filter_var($notificationAddress, FILTER_VALIDATE_EMAIL)) {
                $safePlan = h($selectedPlan === "" ? "Not selected" : $selectedPlan);
                $notification = "<h2>New Muscle Kingz enquiry #{$messageId}</h2>"
                    . "<p><strong>Name:</strong> " . h($name) . "</p>"
                    . "<p><strong>Mobile:</strong> " . h($mobile) . "</p>"
                    . "<p><strong>Email:</strong> " . h($email) . "</p>"
                    . "<p><strong>Membership plan:</strong> {$safePlan}</p>"
                    . "<p><strong>Message:</strong><br>" . nl2br(h($messageText)) . "</p>"
                    . "<hr><p><strong>" . h(GYM_NAME) . "</strong><br>"
                    . "Phone: " . h($gymContact["phone"]) . "<br>"
                    . "Email: " . h($gymContact["email"]) . "<br>"
                    . "Address: " . h($gymContact["address"]) . "</p>";
                send_gym_email($notificationAddress, "New gym enquiry #{$messageId}", $notification, $email);
            }

            $success = "Thank you, " . $name . "! Your enquiry has been received and saved. Our team will contact you shortly.";
            $name = $mobile = $email = $messageText = "";
            $selectedPlan = "";
        } catch (RuntimeException | PDOException $exception) {
            error_log("Muscle Kingz enquiry could not be saved: " . $exception->getMessage());
            $errors[] = "We could not save your enquiry right now. Please try again later.";
        }
    }
}

$cleanPhone = preg_replace('/[^0-9]/', '', $gymContact["phone"]);
$internationalPhone = "+91" . (str_starts_with($cleanPhone, "91") ? substr($cleanPhone, 2) : $cleanPhone);
$whatsappUrl = "https://wa.me/" . (str_starts_with($cleanPhone, "91") ? $cleanPhone : "91" . $cleanPhone) . "?text=" . urlencode("Hello Muscle Kingz Gym, I would like to know about your membership plans.");
$mapsUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode("Muscle Kingz Gym, Mappadhu");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Contact Muscle Kingz Gym. Call, WhatsApp, email, or send us a message to get started on your fitness journey.">
  <title><?= h($pageTitle) ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
  <a class="logo" href="index.php" aria-label="Muscle Kingz Gym home">MUSCLE <span>KINGZ</span></a>
  <nav aria-label="Main navigation">
    <a href="index.php">Home</a>
    <a href="about.php">About</a>
    <a href="plans.php">Plans</a>
    <a class="active" aria-current="page" href="contact.php">Contact</a>
  </nav>
</header>
<main class="page-wrap">
  <section class="page-banner page-banner-contact">
    <img src="assets/gym-interior.jpg" alt="Muscle Kingz Gym Interior Banner" class="page-banner-image">
    <div class="page-banner-content">
      <p class="eyebrow">WE'RE HERE TO HELP</p>
      <h1>CONTACT <span>US</span></h1>
      <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="index.php">Home</a><span aria-hidden="true">/</span><span aria-current="page">Contact</span></nav>
    </div>
  </section>

  <!-- Prominent Quick Action Contact Bar -->
  <section class="quick-contact-section" aria-label="Quick contact actions">
    <div class="quick-contact-grid">
      <a class="quick-contact-card card-call" href="tel:<?= h($internationalPhone) ?>">
        <div class="card-icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
        </div>
        <div class="card-text">
          <span class="card-label">Call Now</span>
          <span class="card-sub"><?= h($internationalPhone) ?></span>
        </div>
      </a>

      <a class="quick-contact-card card-whatsapp" href="<?= h($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">
        <div class="card-icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm0 18.15c-1.49 0-2.95-.4-4.22-1.16l-.3-.18-3.12.82.83-3.04-.2-.31a8.18 8.18 0 0 1-1.26-4.37c0-4.51 3.67-8.18 8.18-8.18 2.19 0 4.24.85 5.79 2.4 1.54 1.55 2.39 3.61 2.39 5.8 0 4.51-3.67 8.18-8.18 8.18zm4.49-6.13c-.25-.12-1.47-.72-1.7-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.39-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.15.17-.25.25-.42.08-.17.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.84-.86 2.05 0 1.21.88 2.38 1 2.55.12.17 1.74 2.65 4.21 3.72.59.25 1.05.41 1.41.52.59.19 1.13.16 1.56.1.47-.07 1.47-.6 1.68-1.18.21-.58.21-1.08.15-1.18-.06-.1-.23-.17-.48-.29z"/></svg>
        </div>
        <div class="card-text">
          <span class="card-label">WhatsApp</span>
          <span class="card-sub">Instant Gym Chat</span>
        </div>
      </a>

      <a class="quick-contact-card card-email" href="mailto:<?= h($gymContact["email"]) ?>">
        <div class="card-icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
        </div>
        <div class="card-text">
          <span class="card-label">Email Us</span>
          <span class="card-sub"><?= h($gymContact["email"]) ?></span>
        </div>
      </a>

      <a class="quick-contact-card card-map" href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener noreferrer">
        <div class="card-icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
        </div>
        <div class="card-text">
          <span class="card-label">Get Directions</span>
          <span class="card-sub"><?= h($gymContact["address"]) ?></span>
        </div>
      </a>
    </div>
  </section>

  <!-- Two Column Layout: Left Contact Info / Right Contact Form -->
  <section class="contact-layout">
    <div class="contact-info">
      <p class="eyebrow">GET IN TOUCH</p>
      <h2>LET'S TALK <span>FITNESS</span></h2>
      <p>Have a question about memberships, personal training, or workout schedules? Contact Muscle Kingz Gym directly or send us a message below.</p>
      
      <div class="contact-details">
        <div>
          <span aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
          </span>
          <p>
            <strong>Phone (Direct Call)</strong><br>
            <a href="tel:<?= h($internationalPhone) ?>"><?= h($internationalPhone) ?></a>
          </p>
        </div>

        <div>
          <span class="icon-whatsapp" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm0 18.15c-1.49 0-2.95-.4-4.22-1.16l-.3-.18-3.12.82.83-3.04-.2-.31a8.18 8.18 0 0 1-1.26-4.37c0-4.51 3.67-8.18 8.18-8.18 2.19 0 4.24.85 5.79 2.4 1.54 1.55 2.39 3.61 2.39 5.8 0 4.51-3.67 8.18-8.18 8.18zm4.49-6.13c-.25-.12-1.47-.72-1.7-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.39-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.15.17-.25.25-.42.08-.17.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.84-.86 2.05 0 1.21.88 2.38 1 2.55.12.17 1.74 2.65 4.21 3.72.59.25 1.05.41 1.41.52.59.19 1.13.16 1.56.1.47-.07 1.47-.6 1.68-1.18.21-.58.21-1.08.15-1.18-.06-.1-.23-.17-.48-.29z"/></svg>
          </span>
          <p>
            <strong>WhatsApp Message</strong><br>
            <a href="<?= h($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">Chat on WhatsApp (<?= h($internationalPhone) ?>)</a>
          </p>
        </div>

        <div>
          <span aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
          </span>
          <p>
            <strong>Email Address</strong><br>
            <a href="mailto:<?= h($gymContact["email"]) ?>"><?= h($gymContact["email"]) ?></a>
          </p>
        </div>

        <div>
          <span aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
          </span>
          <p>
            <strong>Location &amp; Address</strong><br>
            <a href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener noreferrer"><?= h($gymContact["address"]) ?> (Google Maps)</a>
          </p>
        </div>
      </div>

      <div class="whatsapp-cta-block">
        <a class="btn btn-whatsapp" href="<?= h($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm0 18.15c-1.49 0-2.95-.4-4.22-1.16l-.3-.18-3.12.82.83-3.04-.2-.31a8.18 8.18 0 0 1-1.26-4.37c0-4.51 3.67-8.18 8.18-8.18 2.19 0 4.24.85 5.79 2.4 1.54 1.55 2.39 3.61 2.39 5.8 0 4.51-3.67 8.18-8.18 8.18zm4.49-6.13c-.25-.12-1.47-.72-1.7-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.39-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.15.17-.25.25-.42.08-.17.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.84-.86 2.05 0 1.21.88 2.38 1 2.55.12.17 1.74 2.65 4.21 3.72.59.25 1.05.41 1.41.52.59.19 1.13.16 1.56.1.47-.07 1.47-.6 1.68-1.18.21-.58.21-1.08.15-1.18-.06-.1-.23-.17-.48-.29z"/></svg>
          CHAT ON WHATSAPP <span aria-hidden="true">→</span>
        </a>
      </div>

      <p class="form-note">For your privacy and security, never send passwords or payment information via web forms.</p>
    </div>

    <!-- Contact Form -->
    <form class="contact-form" method="post" action="contact.php">
      <h2>SEND US A <span>MESSAGE</span></h2>

      <?php if ($success !== ""): ?>
        <p class="success" role="status"><?= h($success) ?></p>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="error-box" role="alert">
          <strong>Please check the following:</strong>
          <ul>
            <?php foreach ($errors as $error): ?>
              <li><?= h($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <!-- Anti-spam Honeypot -->
      <div style="display:none !important;" aria-hidden="true">
        <label for="gym_trap">Leave this blank</label>
        <input type="text" id="gym_trap" name="gym_trap" value="" tabindex="-1" autocomplete="off">
      </div>

      <label for="name">Your Name</label>
      <input id="name" type="text" name="name" maxlength="100" placeholder="Enter your full name" value="<?= h($name) ?>" autocomplete="name" required>

      <label for="mobile">Mobile Number</label>
      <input id="mobile" type="tel" name="mobile" maxlength="20" placeholder="Enter your mobile number" value="<?= h($mobile) ?>" autocomplete="tel" required>

      <label for="email">Email Address</label>
      <input id="email" type="email" name="email" maxlength="254" placeholder="you@example.com" value="<?= h($email) ?>" autocomplete="email" required>

      <label for="plan">Membership Plan (optional)</label>
      <select id="plan" name="plan">
        <option value="">Choose a plan</option>
        <?php foreach ($availablePlans as $planOption): ?>
          <option value="<?= h($planOption) ?>" <?= $selectedPlan === $planOption ? "selected" : "" ?>><?= h($planOption) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="message">Your Message</label>
      <textarea id="message" name="message" rows="5" maxlength="2000" placeholder="How can we help? Tell us about your fitness goals..." required><?= h($messageText) ?></textarea>

      <button class="btn" type="submit">SEND MESSAGE <span aria-hidden="true">→</span></button>
      <p class="form-note">Your enquiry is saved securely and sent directly to Muscle Kingz Gym staff.</p>
    </form>
  </section>
</main>

<footer class="site-footer">
  <a class="logo" href="index.php">MUSCLE <span>KINGZ</span></a>
  <p>© <?= date("Y") ?> Muscle Kingz Gym. All rights reserved.</p>
  <div class="footer-contact" aria-label="Gym contact details">
    <a href="tel:<?= h($internationalPhone) ?>"><?= h($internationalPhone) ?></a>
    <a href="mailto:<?= h($gymContact["email"]) ?>"><?= h($gymContact["email"]) ?></a>
    <a href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener noreferrer"><?= h($gymContact["address"]) ?></a>
  </div>
  <p class="footer-note">Train smart. Stay consistent.</p>
</footer>
</body>
</html>
