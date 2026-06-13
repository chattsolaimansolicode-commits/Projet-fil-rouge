<?php
// includes/layout_teacher.php
$user = currentUser();
$initials = getInitials($user['name']);
$firstName = explode(' ', $user['name'])[0];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'PFE Manager') ?> — PFE Manager</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="app-wrapper">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
      <div>
        <div class="logo-text">PFE Manager</div>
        <div class="logo-sub">Espace Enseignant</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Navigation</div>
      <a href="<?= APP_URL ?>/teacher/dashboard.php" class="nav-link <?= ($activeNav==='dashboard') ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fa-solid fa-house"></i></span> Tableau de bord
      </a>
      <a href="<?= APP_URL ?>/teacher/projects.php" class="nav-link <?= ($activeNav==='projects') ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fa-solid fa-diagram-project"></i></span> Projets
      </a>
      <a href="<?= APP_URL ?>/teacher/requests.php" class="nav-link <?= ($activeNav==='requests') ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fa-solid fa-inbox"></i></span> Demandes
      </a>
      <a href="<?= APP_URL ?>/teacher/documents.php" class="nav-link <?= ($activeNav==='documents') ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fa-solid fa-file-lines"></i></span> Documents
      </a>
      <a href="<?= APP_URL ?>/teacher/grades.php" class="nav-link <?= ($activeNav==='grades') ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fa-solid fa-star-half-stroke"></i></span> Notes
      </a>
      <a href="<?= APP_URL ?>/teacher/defense.php" class="nav-link <?= ($activeNav==='defense') ? 'active' : '' ?>">
        <span class="nav-icon"><i class="fa-solid fa-calendar-check"></i></span> Soutenances
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-card">
        <div class="avatar" style="background:linear-gradient(135deg,#10B981,#059669)"><?= e($initials) ?></div>
        <div class="user-info">
          <div class="user-name"><?= e($firstName) ?></div>
          <div class="user-role">Enseignant</div>
        </div>
        <a href="<?= APP_URL ?>/logout.php" class="logout-btn">
          <i class="fa-solid fa-right-from-bracket"></i>
        </a>
      </div>
    </div>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <button class="hamburger" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
      <span class="topbar-title"><?= e($pageTitle ?? '') ?></span>
      <div class="topbar-search">
        <span class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
        <input type="text" placeholder="Rechercher...">
      </div>
    </header>

    <main class="page-content">
