<?php
require_once __DIR__ . "/config.php";
$gymContact = gym_contact_details();
$pageTitle = "Muscle Kingz Gym | Build Your Stronger Self";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Train with purpose at Muscle Kingz Gym. Find your strength, build consistency and start your fitness journey.">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, "UTF-8") ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
  <a class="logo" href="index.php" aria-label="Muscle Kingz Gym home">MUSCLE <span>KINGZ</span></a>
  <nav aria-label="Main navigation">
    <a class="active" aria-current="page" href="index.php">Home</a>
    <a href="about.php">About</a>
    <a href="plans.php">Plans</a>
    <a href="contact.php">Contact</a>
  </nav>
</header>
<main>
  <section class="hero">
    <img class="hero-image" src="assets/gym-hero.jpg" alt="">
    <div class="hero-content">
      <p class="eyebrow">YOUR FITNESS. YOUR POWER.</p>
      <h1>BUILD YOUR<br><span>STRONGER</span> SELF</h1>
      <p class="hero-copy">Push your limits. Build your strength. Become the best version of yourself.</p>
      <a href="plans.php" class="btn">JOIN NOW <span aria-hidden="true">→</span></a>
    </div>
  </section>
  <section class="section features">
    <div class="section-heading">
      <p class="eyebrow">WHY MUSCLE KINGZ</p>
      <h2>BUILT FOR YOUR <span>GOALS</span></h2>
      <p>Everything you need to make every workout count.</p>
    </div>
    <div class="feature-container">
      <article class="feature">
        <div class="feature-icon" aria-hidden="true">⌁</div>
        <h3>Modern Equipment</h3>
        <p>Train with quality equipment for strength, conditioning and fitness.</p>
      </article>
      <article class="feature">
        <div class="feature-icon" aria-hidden="true">✦</div>
        <h3>Expert Trainers</h3>
        <p>Get helpful guidance and encouragement as you work toward your goals.</p>
      </article>
      <article class="feature">
        <div class="feature-icon" aria-hidden="true">↗</div>
        <h3>Best Environment</h3>
        <p>Find the focus and positive energy to keep showing up and improving.</p>
      </article>
    </div>
  </section>
  <section class="cta">
    <img class="cta-image" src="assets/gym-training.jpg" alt="">
    <div class="cta-content">
      <p class="eyebrow">YOUR NEXT CHAPTER STARTS HERE</p>
      <h2>READY TO START YOUR<br>FITNESS <span>JOURNEY?</span></h2>
      <p>Take the first step today. Your stronger self starts with one workout.</p>
      <a href="contact.php" class="btn">CONTACT US <span aria-hidden="true">→</span></a>
    </div>
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
  <p class="footer-note">Train smart. Stay consistent.</p>
</footer>
</body>
</html>
