<?php
/**
 * Layout pembuka. Variabel yang diharapkan sebelum include:
 *   $user (array), $pageTitle (string), $activeMenu (string)
 */
$pageTitle  = $pageTitle  ?? 'Dashboard';
$activeMenu = $activeMenu ?? '';
$roleLabel  = $user['role'] === 'admin' ? 'Admin/Laboran' : ucfirst($user['user_type']);
$initials   = strtoupper(implode('', array_map(fn($w) => $w[0] ?? '', array_slice(explode(' ', preg_replace('/^(Dr\.|Prof\.)\s*/', '', $user['full_name'])), 0, 2))));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="app">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <div class="sidebar-backdrop" data-sidebar-close></div>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-toggle" type="button" aria-label="Buka menu" data-sidebar-open>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <div class="topbar-title">
        <p class="eyebrow"><?= e($roleLabel) ?></p>
        <h1><?= e($pageTitle) ?></h1>
      </div>
      <div class="topbar-user">
        <span class="avatar"><?= e($initials) ?></span>
        <span class="user-meta">
          <strong><?= e($user['full_name']) ?></strong>
          <small>@<?= e($user['username']) ?></small>
        </span>
      </div>
    </header>

    <main class="content">
      <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
