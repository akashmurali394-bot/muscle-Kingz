<?php
require_once __DIR__ . "/config.php";
$gymContact = gym_contact_details();
$pageTitle = "Membership Plans | Muscle Kingz Gym";
$plans = [
    ["Monthly Plan", "₹1,200", "1 Month Membership", ["Gym Access", "Basic Training"]],
    ["Quarterly Plan", "₹2,999", "3 Months Membership", ["Gym Access", "Trainer Guidance"]],
    ["Yearly Plan", "₹7,499", "12 Months Membership", ["Full Gym Access", "Personal Guidance"]]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Explore monthly, quarterly and yearly membership plans at Muscle Kingz Gym.">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, "UTF-8") ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
  <a class="logo" href="index.php" aria-label="Muscle Kingz Gym home">MUSCLE <span>KINGZ</span></a>
  <nav aria-label="Main navigation">
    <a href="index.php">Home</a>
    <a href="about.php">About</a>
    <a class="active" aria-current="page" href="plans.php">Plans</a>
    <a href="contact.php">Contact</a>
  </nav>
</header>
<main class="page-wrap">
  <section class="page-banner page-banner-plans">
    <img src="assets/gym-training.jpg" alt="" class="page-banner-image">
    <div class="page-banner-content">
      <p class="eyebrow">CHOOSE YOUR COMMITMENT</p>
      <h1>MEMBERSHIP <span>PLANS</span></h1>
      <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="index.php">Home</a><span aria-hidden="true">/</span><span aria-current="page">Plans</span></nav>
    </div>
  </section>
  <section class="plans" aria-label="Membership options">
    <?php foreach ($plans as $index => $plan): ?>
      <article class="plan <?= $index === 1 ? "featured-plan" : "" ?>">
        <?php if ($index === 1): ?><span class="plan-tag">MOST POPULAR</span><?php endif; ?>
        <p class="plan-duration"><?= htmlspecialchars($plan[2], ENT_QUOTES, "UTF-8") ?></p>
        <h2><?= htmlspecialchars($plan[0], ENT_QUOTES, "UTF-8") ?></h2>
        <p class="plan-price"><?= htmlspecialchars($plan[1], ENT_QUOTES, "UTF-8") ?></p>
        <p class="muted">per membership period</p>
        <ul><?php foreach ($plan[3] as $benefit): ?><li><?= htmlspecialchars($benefit, ENT_QUOTES, "UTF-8") ?></li><?php endforeach; ?></ul>
        <a class="btn" href="contact.php?plan=<?= urlencode($plan[0]) ?>">JOIN NOW <span aria-hidden="true">→</span></a>
      </article>
    <?php endforeach; ?>
  </section>
  <p class="disclaimer">Contact Muscle Kingz Gym with questions about membership plan availability.</p>
  <section class="feature-strip" aria-label="Membership benefits">
    <div><span aria-hidden="true">◷</span><p>Flexible Timings</p></div>
    <div><span aria-hidden="true">✦</span><p>Safe &amp; Clean</p></div>
    <div><span aria-hidden="true">♥</span><p>Your Fitness, Our Priority</p></div>
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
