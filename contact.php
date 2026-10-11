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

    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = "Your session expired. Refresh the page and try again.";
    } else {
        if ($name === "" || character_count($name) > 100) $errors[] = "Please enter your name (up to 100 characters).";
        if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $mobile)) $errors[] = "Please enter a valid mobile number.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) $errors[] = "Please enter a valid email address.";
        if ($messageText === "" || character_count($messageText) > 2000) $errors[] = "Please enter a message (up to 2,000 characters).";
        if ($selectedPlan !== "" && !in_array($selectedPlan, $availablePlans, true)) $errors[] = "Please choose a valid membership plan.";
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
            } else {
                error_log("Muscle Kingz email notification not sent: GYM_NOTIFICATION_EMAIL is not configured.");
            }
            $success = "Thank you, " . $name . "! Your enquiry has been saved. The gym will follow up using the contact details you provided.";
            $name = $mobile = $email = $messageText = "";
            $selectedPlan = "";
        } catch (RuntimeException | PDOException $exception) {
            error_log("Muscle Kingz enquiry could not be saved: " . $exception->getMessage());
            $errors[] = "We could not save your enquiry right now. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Contact Muscle Kingz Gym to ask about memberships and training.">
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
    <img src="assets/gym-interior.jpg" alt="" class="page-banner-image">
    <div class="page-banner-content">
      <p class="eyebrow">WE'RE HERE TO HELP</p>
      <h1>CONTACT <span>US</span></h1>
      <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="index.php">Home</a><span aria-hidden="true">/</span><span aria-current="page">Contact</span></nav>
    </div>
  </section>
  <section class="contact-layout">
    <div class="contact-info">
      <p class="eyebrow">GET IN TOUCH</p>
      <h2>LET'S TALK <span>FITNESS</span></h2>
      <p>Have a question about memberships or getting started? Contact Muscle Kingz Gym or send us a message using the form.</p>
      <div class="contact-details">
        <div><span aria-hidden="true">☎</span><p><strong>Phone</strong><br><a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $gymContact["phone"])) ?>"><?= h($gymContact["phone"]) ?></a></p></div>
        <div><span aria-hidden="true">✉</span><p><strong>Email</strong><br><a href="mailto:<?= h($gymContact["email"]) ?>"><?= h($gymContact["email"]) ?></a></p></div>
        <div><span aria-hidden="true">⌖</span><p><strong>Address</strong><br><?= h($gymContact["address"]) ?></p></div>
      </div>
      <p class="form-note">For your safety, never send passwords or payment card details through this form.</p>
    </div>
    <form class="contact-form" method="post" action="contact.php">
      <h2>SEND US A MESSAGE</h2>
      <?php if ($success): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
      <?php if ($errors): ?><div class="error-box" role="alert"><strong>Please check the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <label for="name">Your Name</label>
      <input id="name" type="text" name="name" maxlength="100" placeholder="Enter your full name" value="<?= h($name) ?>" autocomplete="name" required>
      <label for="mobile">Mobile Number</label>
      <input id="mobile" type="tel" name="mobile" maxlength="20" placeholder="Enter your mobile number" value="<?= h($mobile) ?>" autocomplete="tel" required>
      <label for="email">Email Address</label>
      <input id="email" type="email" name="email" maxlength="254" placeholder="you@example.com" value="<?= h($email) ?>" autocomplete="email" required>
      <label for="plan">Membership Plan (optional)</label>
      <select id="plan" name="plan">
        <option value="">Choose a plan</option>
        <?php foreach ($availablePlans as $plan): ?><option value="<?= h($plan) ?>" <?= $selectedPlan === $plan ? "selected" : "" ?>><?= h($plan) ?></option><?php endforeach; ?>
      </select>
      <label for="message">Your Message</label>
      <textarea id="message" name="message" rows="5" maxlength="2000" placeholder="How can we help?" required><?= h($messageText) ?></textarea>
      <button class="btn" type="submit">SEND MESSAGE <span aria-hidden="true">→</span></button>
      <p class="form-note">Enquiries are saved securely when the database is configured. Email notification requires Gmail SMTP setup.</p>
    </form>
  </section>
</main>
<footer class="site-footer">
  <a class="logo" href="index.php">MUSCLE <span>KINGZ</span></a>
  <p>© <?= date("Y") ?> Muscle Kingz Gym. All rights reserved.</p>
  <div class="footer-contact" aria-label="Gym contact details">
    <a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $gymContact["phone"])) ?>"><?= h($gymContact["phone"]) ?></a>
    <a href="mailto:<?= h($gymContact["email"]) ?>"><?= h($gymContact["email"]) ?></a>
    <span><?= h($gymContact["address"]) ?></span>
  </div>
</footer>
</body>
</html>
