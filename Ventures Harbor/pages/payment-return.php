<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
$currentUser = requirePageUserOnly();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payment Result – Ventures Harbor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43"/>
  <link rel="stylesheet" href="../assets/css/payment-return.css?v=1"/>
  <link rel="stylesheet" href="../assets/css/vh-nav.css?v=3">
</head>
<body>
<div class="toast-container" id="toastContainer"></div>
<?php
$navSolid = true;
include __DIR__ . '/../partials/header.php';
?>

<main class="pr-page">
  <div class="pr-card" id="prCard">
    <div class="pr-icon" id="prIcon"><svg class="vh-i" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 22h14M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/></svg></div>
    <h1 class="pr-title" id="prTitle">Checking your payment…</h1>
    <p class="pr-message" id="prMessage">One moment while we confirm this with the payment gateway.</p>

    <dl class="pr-details hidden" id="prDetails">
      <dt>Asset</dt><dd id="prVenture">—</dd>
      <dt>Commitment fee</dt><dd id="prAmount">—</dd>
      <dt id="prPledgeLabel">Pledged investment</dt><dd id="prPledge">—</dd>
      <dt>Transaction ID</dt><dd id="prTxn">—</dd>
    </dl>

    <p class="pr-note hidden" id="prNote"></p>

    <div class="pr-actions" id="prActions"></div>
  </div>
</main>

<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/vh-nav.js?v=1"></script>
<script src="../assets/js/payment-return.js?v=3"></script>
</body>
</html>
