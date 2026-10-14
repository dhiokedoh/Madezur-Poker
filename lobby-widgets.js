(() => {
  const rooms = document.getElementById('online-rooms');
  const histories = document.getElementById('room-history-list');
  const points = document.getElementById('points-leaderboard');
  const dialog = document.getElementById('points-dialog');
  let loading = false;
  function cell(text) { const td = document.createElement('td'); td.textContent = text; return td; }
  function emptyTable(target, columns) { const tr = document.createElement('tr'); const td = cell('Belum ada data pemain.'); td.colSpan = columns; tr.append(td); target.replaceChildren(tr); }
  async function refresh() {
    if (loading) return;
    loading = true;
    document.querySelectorAll('[data-lobby-refresh]').forEach(b => b.disabled = true);
    try {
      const response = await fetch('lobby-api.php',{cache:'no-store'});
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Gagal memuat data.');
      document.getElementById('lobby-data-status').textContent = '';
      rooms.replaceChildren();
      data.rooms.forEach(room => {
        const box = document.createElement('article'); box.className = 'online-room';
        const title = document.createElement('h3'); title.textContent = 'Meja ' + room.code;
        if (room.hasHistory) { const link = document.createElement('a'); link.href = 'room-history.php?room=' + encodeURIComponent(room.code); link.className = 'room-history-name'; link.textContent = title.textContent; title.replaceChildren(link); }
        const host = document.createElement('p'); host.textContent = 'Host: ' + room.host;
        const info = document.createElement('p'); info.className = 'widget-note'; info.textContent = `${room.players}/${room.capacity} pemain · ${room.decks} deck · ${room.online} online`;
        const status = document.createElement('span'); status.className = 'game-status'; status.textContent = room.status;
        box.append(title,host,info,status);
        if (room.joinable || room.member) { const link = document.createElement('a'); link.href = 'poker.php?room=' + encodeURIComponent(room.code); link.className = 'logout-button'; link.dataset.icon = 'login'; link.textContent = room.member ? 'Lanjutkan' : 'Gabung'; box.append(link); }
        rooms.append(box);
      });
      if (!data.rooms.length) { const p = document.createElement('p'); p.className = 'widget-empty'; p.textContent = 'Belum ada ruang online. Buat ruang Poker untuk mulai bermain.'; rooms.append(p); }
      histories.replaceChildren();
      data.histories.forEach(room => {
        const box = document.createElement('article'); box.className = 'online-room';
        const title = document.createElement('h3'); const link = document.createElement('a'); link.href = 'room-history.php?room=' + encodeURIComponent(room.code); link.className = 'room-history-name'; link.textContent = 'Meja ' + room.code; title.append(link);
        const note = document.createElement('p'); note.className = 'widget-note'; note.textContent = `${room.rounds} ronde selesai · Klik nama ruang untuk melihat tabel`;
        box.append(title,note); histories.append(box);
      });
      if (!data.histories.length) { const p = document.createElement('p'); p.className = 'widget-empty'; p.textContent = 'Belum ada ronde selesai. Hasil ruang muncul setelah permainan selesai.'; histories.append(p); }
      points.replaceChildren();
      data.points.forEach((user,index) => { const tr = document.createElement('tr'); tr.append(...[index+1,user.username,user.points,user.games].map(cell)); points.append(tr); });
      if (!data.points.length) emptyTable(points,4);
    } catch(error) { document.getElementById('lobby-data-status').textContent = error.message; }
    finally { loading = false; document.querySelectorAll('[data-lobby-refresh]').forEach(b => b.disabled = false); }
  }
  document.querySelectorAll('[data-points-open]').forEach(b => b.addEventListener('click', () => dialog.showModal()));
  document.querySelector('[data-points-close]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', event => { if (event.target === dialog) { const bounds = dialog.getBoundingClientRect(); if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close(); } });
  document.querySelectorAll('[data-lobby-refresh]').forEach(b => b.addEventListener('click',refresh));
  async function poll() { await refresh(); setTimeout(poll,5000); }
  poll();
})();
