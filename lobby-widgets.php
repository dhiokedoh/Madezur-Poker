<section class="live-section" aria-labelledby="online-title">
  <div class="section-heading"><div><span class="section-kicker">TEMUKAN MEJA ANDA</span><h2 id="online-title">Ruang online</h2></div><div class="widget-actions"><button class="logout-button" type="button" data-points-open data-icon="trophy">Poin</button><button class="logout-button" type="button" data-lobby-refresh data-icon="refresh">Perbarui</button></div></div>
  <p class="widget-note">Ruang dengan pemain aktif dalam 30 detik terakhir. Data diperbarui otomatis.</p>
  <div id="online-rooms" class="online-rooms"><p class="widget-empty">Memuat ruang…</p></div>
  <p id="lobby-data-status" role="status" aria-live="polite" class="widget-note"></p>
</section>
<section class="live-section" aria-labelledby="history-title">
  <div class="section-heading"><div><span class="section-kicker">RIWAYAT PER RUANG</span><h2 id="history-title">Hasil permainan</h2></div></div>
  <p class="widget-note">Setelah ronde selesai, klik nama ruang untuk melihat tabel poin setiap pemain per ronde.</p>
  <div id="room-history-list" class="online-rooms"><p class="widget-empty">Memuat hasil ruang…</p></div>
</section>
<dialog id="points-dialog" class="points-dialog" aria-labelledby="points-title">
  <div class="section-heading"><h2 id="points-title">Poin pemain</h2><button type="button" class="logout-button" data-points-close data-icon="close" aria-label="Tutup poin">Tutup</button></div>
  <p>Poin = jumlah pemain − peringkat. Untuk 4 pemain:</p>
  <div class="point-example"><span>🥇 Juara 1 <strong>3 poin</strong></span><span>🥈 Juara 2 <strong>2 poin</strong></span><span>🥉 Juara 3 <strong>1 poin</strong></span><span>Juara 4 <strong>0 poin</strong></span></div>
  <p class="widget-note">Untuk 20 pemain: 19, 18, …, 0 poin. Total poin tersimpan setelah seluruh peringkat ronde ditentukan. Daftar berikut diurutkan dari poin tertinggi.</p>
  <div class="table-scroll"><table class="stats-table"><thead><tr><th>No.</th><th>Username</th><th>Total poin</th><th>Ronde</th></tr></thead><tbody id="points-leaderboard"></tbody></table></div>
</dialog>
