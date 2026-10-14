<?php
require __DIR__ . '/db.php';
startLoginSession();
header('Cache-Control: no-store');
if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
$_SESSION['logout_token'] ??= bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#092c25"><title>Madezur Poker — Permainan</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="lobby.css"><link rel="stylesheet" href="widgets.css"><script src="icons.js" defer></script><script src="lobby-widgets.js" defer></script></head>
<body class="lobby">
  <header class="lobby-header">
    <a class="brand" href="dashboard.php"><span class="brand-mark">♠</span> MADEZUR<span class="brand-light">POKER</span></a>
    <div class="account-menu"><span><?= htmlspecialchars($_SESSION['user'], ENT_QUOTES, 'UTF-8') ?></span><button class="logout-button" type="button" data-points-open data-icon="trophy">Poin</button><form action="logout.php" method="post"><input type="hidden" name="token" value="<?= $_SESSION['logout_token'] ?>"><button class="logout-button" type="submit">Keluar</button></form></div>
  </header>
  <main class="lobby-main">
    <div class="eyebrow"><span></span> SELAMAT DATANG DI LOBI</div>
    <h1>Meja berikutnya,<br><em>pilihan Anda.</em></h1>
    <p class="lobby-intro">Selamat datang, <strong><?= htmlspecialchars($_SESSION['user'], ENT_QUOTES, 'UTF-8') ?></strong><?= ($_SESSION['role'] ?? 'player') === 'admin' ? ' <span class="game-status">Administrator</span>' : '' ?>. Temukan permainan Anda di bawah ini.</p>
    <section class="game-grid" aria-label="Daftar permainan">
      <article class="game-card poker-card">
        <div class="game-art" aria-hidden="true"><div class="mini-card mini-red">A<span>♥</span></div><div class="mini-card mini-black">A<span>♠</span></div></div>
        <div class="game-details"><span class="game-number">01 / PERMAINAN KARTU</span><h2>Poker</h2><p>Strategi di setiap kartu. Main bersama 4–20 pemain dengan 1–10 deck.</p><a class="logout-button" href="poker.php">Main Poker →</a></div>
      </article>
      <article class="game-card tj-card">
        <div class="game-art tj-art" aria-hidden="true"><div class="tj-emblem">TJ<span>♣ ♦ ♠</span></div></div>
        <div class="game-details"><span class="game-number">02 / PERMAINAN KARTU</span><h2>TJ</h2><p>Temukan keseruan berikutnya.</p><span class="game-status">Segera hadir</span></div>
      </article>
    </section>
    <p class="lobby-note">Poker tersedia untuk bermain online. TJ segera hadir.</p>
    <?php require __DIR__ . '/lobby-widgets.php'; ?>
  </main>
  <footer class="lobby-footer"><span>© 2026 Madezur Poker</span><span>PLAY SMART. ENJOY THE GAME. ♠</span></footer>
</body>
</html>
