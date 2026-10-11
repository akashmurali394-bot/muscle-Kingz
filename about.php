<?php
require_once __DIR__ . "/config.php";
$gymContact = gym_contact_details();
$pageTitle = "About Our Gym | Muscle Kingz Gym";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Discover the training environment and values behind Muscle Kingz Gym.">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, "UTF-8") ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
  <a class="logo" href="index.php" aria-label="Muscle Kingz Gym home">MUSCLE <span>KINGZ</span></a>
  <nav aria-label="Main navigation">
    <a href="index.php">Home</a>
    <a class="active" aria-current="page" href="about.php">About</a>
    <a href="plans.php">Plans</a>
    <a href="contact.php">Contact</a>
  </nav>
</header>
<main class="page-wrap">
  <section class="page-banner">
    <img src="assets/gym-interior.jpg" alt="" class="page-banner-image">
    <div class="page-banner-content">
      <p class="eyebrow">OUR STORY</p>
      <h1>ABOUT OUR <span>GYM</span></h1>
      <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="index.php">Home</a><span aria-hidden="true">/</span><span aria-current="page">About</span></nav>
    </div>
  </section>
  <section class="about-layout section">
    <div class="about-photo">
      <img src="assets/gym-hero.jpg" alt="Member training with a barbell at the gym" loading="lazy">
    </div>
    <div class="about-copy">
      <p class="eyebrow">WELCOME TO MUSCLE KINGZ GYM</p>
      <h2>BUILT AROUND YOUR <span>PROGRESS</span></h2>
      <p>Muscle Kingz Gym is a place to work toward your fitness and bodybuilding goals at your own pace. Whether you are starting out or building on years of training, every session is an opportunity to get stronger.</p>
      <p>We believe that consistency, good guidance and a welcoming training space help make the journey rewarding.</p>
      <div class="mission-card">
        <p class="eyebrow">OUR MISSION</p>
        <h3>HELP EVERY MEMBER GROW STRONGER.</h3>
        <p>Our mission is to support strength, fitness and confidence through regular training and a positive gym community.</p>
      </div>
    </div>
  </section>
  <section class="section benefit-section">
    <div class="section-heading">
      <p class="eyebrow">THE KINGZ STANDARD</p>
      <h2>MORE THAN A <span>WORKOUT</span></h2>
    </div>
    <div class="benefit-grid">
      <article class="benefit-item"><span aria-hidden="true">⌁</span><h3>Modern Equipment</h3></article>
      <article class="benefit-item"><span aria-hidden="true">✦</span><h3>Certified Trainers</h3></article>
      <article class="benefit-item"><span aria-hidden="true">♥</span><h3>Healthy Lifestyle</h3></article>
      <article class="benefit-item"><span aria-hidden="true">◎</span><h3>Supportive Community</h3></article>
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
</footer>
</body>
</html>
