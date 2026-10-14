<?php
require __DIR__ . '/db.php';
startLoginSession();
header('Cache-Control: no-store');
if (empty($_SESSION['user_id'])) { header('Location: index.html'); exit; }
$_SESSION['poker_token'] ??= bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Madezur Poker — Meja Online</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="lobby.css"><link rel="stylesheet" href="poker.css"><link rel="stylesheet" href="widgets.css"><script src="icons.js" defer></script><script src="poker.js" defer></script><script src="lobby-widgets.js" defer></script></head>
<body class="lobby" data-token="<?= htmlspecialchars($_SESSION['poker_token'], ENT_QUOTES, 'UTF-8') ?>">
<header class="lobby-header"><a class="brand" href="dashboard.php"><span class="brand-mark">♠</span> MADEZUR<span class="brand-light">POKER</span></a><div class="widget-actions"><button class="logout-button" type="button" data-points-open data-icon="trophy">Poin</button><a class="logout-button" href="dashboard.php">Kembali ke lobi</a></div></header>
<main class="poker-main">
  <div class="eyebrow">MEJA POKER ONLINE · 4–20 PEMAIN · 1–10 DECK</div>
  <h2>Main bersama teman.</h2>
  <section id="room-entry" class="room-entry" aria-label="Buat atau gabung ruang">
    <div class="room-options">
      <label for="player-count">Jumlah pemain<select id="player-count"><?php for ($i = 4; $i <= 20; $i++): ?><option value="<?= $i ?>"><?= $i ?> pemain</option><?php endfor; ?></select></label>
      <label for="deck-count">Jumlah deck<select id="deck-count"><?php for ($i = 1; $i <= 10; $i++): ?><option value="<?= $i ?>"><?= $i ?> deck · <?= $i * 54 ?> kartu</option><?php endfor; ?></select></label>
    </div>
    <p id="deal-preview">54 kartu untuk 4 pemain: 13–14 kartu per pemain.</p>
    <button id="create-room" class="submit" type="button">Buat ruang baru</button><span>atau</span>
    <form id="join-room"><label for="room-code">Kode ruang</label><input id="room-code" name="code" placeholder="Contoh: A1B2C3D4" maxlength="8" minlength="8" required autocomplete="off"><button class="logout-button" type="submit">Gabung ruang</button></form>
    <p>Bagikan kode ruang kepada teman. Pembuat ruang memulai setelah semua pemain bergabung.</p>
  </section>
  <section id="room-area" hidden>
    <div class="room-toolbar"><div>Kode ruang <strong id="current-code"></strong> <span id="connection">Terhubung</span><p id="room-settings"></p></div><div><button id="start-game" class="logout-button" type="button" hidden>Mulai permainan</button> <button id="leave-room" class="logout-button" type="button">Keluar ruang</button></div></div>
    <div id="players" class="players" aria-label="Pemain"></div>
    <section class="poker-table" aria-label="Kartu di meja"><p id="turn-message" role="status" aria-live="polite"></p><div id="table-card"></div><p id="table-note"></p></section>
    <section id="hand-area" hidden><div class="hand-heading"><h3>Kartu Anda <span id="hand-count"></span></h3><span>Pilih satu kartu yang aktif</span></div><div id="hand" class="hand"></div><div class="play-actions"><button id="play-card" class="submit" type="button" disabled>Mainkan kartu →</button><button id="pass-turn" class="logout-button" type="button" hidden>Pass — lewati giliran</button></div></section>
    <details class="game-history"><summary>Riwayat giliran</summary><ol id="history"></ol></details>
    <section id="round-results" class="round-results" hidden><h3>Ronde selesai</h3><p id="continue-status">Pilih Lanjut untuk ronde berikutnya atau Berhenti.</p><div class="widget-actions"><button id="continue-game" class="logout-button" type="button" data-icon="play">Lanjut</button><button id="stop-game" class="logout-button" type="button" data-icon="exit">Berhenti</button><a id="room-history-link" class="logout-button" href="#" data-icon="trophy"></a></div></section>
  </section>
  <p id="game-message" role="status" aria-live="polite"></p>
  <details class="game-rules"><summary>Aturan permainan</summary><ul><li>Pilih 4–20 pemain dan 1–10 deck. Setiap deck berisi 54 kartu: 52 kartu dengan simbol ♣ ♦ ♥ ♠ dan 2 joker. Semua kartu dibagikan bergiliran; jumlah kartu dapat berbeda satu.</li><li>Pemegang 4♣ dari deck 1 wajib memulai dengan kartu tersebut. Salinan dari deck lain dimainkan mengikuti aturan nilai biasa. Setiap giliran memainkan satu kartu.</li><li>Urutan: 3 &lt; 4 &lt; 5 &lt; 6 &lt; 7 &lt; 8 &lt; 9 &lt; 10 &lt; J &lt; Q &lt; K &lt; A &lt; 2 &lt; Joker. Joker hitam dan merah bernilai sama dan lebih tinggi dari semua kartu biasa. Simbol tidak membedakan nilai kartu.</li><li>Kartu berikutnya harus lebih tinggi. Pass tersedia hanya jika tidak ada kartu yang bisa dimainkan.</li><li>Setelah semua pemain lain pass berturut-turut, pemain terakhir membuka dengan kartu apa saja.</li><li>Pemain pertama yang menghabiskan kartu menjadi juara 1. Permainan berlanjut sampai seluruh peringkat ditentukan, dengan pemain yang sudah selesai dilewati. Poin = jumlah pemain dikurangi peringkat; peringkat terakhir mendapat 0 poin dan dihitung kalah. Jika pemain terakhir yang mengeluarkan kartu sudah selesai dan semua pemain aktif pass, pemain aktif berikutnya bebas membuka. Pemain yang terputus bisa bergabung kembali dengan akun yang sama.</li></ul></details>
  <?php require __DIR__ . '/lobby-widgets.php'; ?>
</main></body></html>
