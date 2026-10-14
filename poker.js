const $ = (id) => document.getElementById(id);
let state = null;
let selected = null;
let busy = false;
let polling = false;
let queuedAction = false;
let lastRendered = '';
const token = document.body.dataset.token;

function cardElement(card, interactive = false, legal = false) {
  const element = document.createElement(interactive ? 'button' : 'div');
  element.className = 'poker-card-face' + (card.red ? ' red-card' : '') + (card.rank === 'JOKER' ? ' joker-card' : '');
  const corner = document.createElement('span');
  corner.className = 'card-corner';
  corner.textContent = card.rank === 'JOKER' ? 'JOKER' : card.rank + card.symbol;
  const symbol = document.createElement('b');
  symbol.textContent = card.symbol;
  const bottom = document.createElement('small');
  bottom.textContent = card.rank === 'JOKER' ? (card.red ? 'MERAH' : 'HITAM') : card.rank + card.symbol;
  element.append(corner, symbol, bottom);
  if (card.deck) {
    const deck = document.createElement('span');
    deck.className = 'card-deck';
    deck.textContent = 'D' + card.deck;
    element.append(deck);
  }
  if (interactive) {
    element.type = 'button';
    element.disabled = !legal || busy;
    element.setAttribute('aria-label', card.rank + ' ' + (card.rank === 'JOKER' ? (card.red ? 'merah' : 'hitam') : ({clubs:'keriting', diamonds:'wajik', hearts:'hati', spades:'sekop'})[card.suit]) + ' deck ' + (card.deck || 1));
    element.setAttribute('aria-pressed', String(selected === card.id));
    if (selected === card.id) element.classList.add('selected');
    element.addEventListener('click', () => { selected = card.id; render(state, true); });
  }
  return element;
}

function render(data, force = false) {
  state = data;
  const key = JSON.stringify(data) + busy;
  if (!force && key === lastRendered) return;
  lastRendered = key;
  $('room-entry').hidden = Boolean(data.room);
  $('room-area').hidden = !data.room;
  if (!data.room) return;
  $('current-code').textContent = data.room;
  $('room-settings').textContent = `${data.activeCount ?? data.members.length}/${data.playerCount} pemain · ${data.deckCount} deck · ${data.deckCount * 54} kartu`;
  const game = data.game;
  if (!game || !game.legal.includes(selected)) selected = null;
  $('start-game').hidden = !data.host || Boolean(game) || data.halted;
  $('start-game').disabled = busy || data.members.length !== data.playerCount;
  $('start-game').textContent = game ? 'Bagikan ulang' : 'Mulai permainan';
  $('leave-room').disabled = busy || Boolean(game && !game.finished);
  $('players').replaceChildren();
  data.members.forEach((member, index) => {
    const box = document.createElement('div');
    box.className = 'player-seat' + (game && game.turn === index && !game.finished ? ' active-seat' : '');
    const name = document.createElement('strong'); name.textContent = member.name + (index === 0 ? ' · Host' : '');
    const count = document.createElement('span'); count.textContent = game ? `${game.players[index].count} kartu${index === game.seat ? ' · Anda' : ''}` : 'Siap bermain';
    const result = game?.results.find(result => result.seat === index);
    if (result) count.textContent = `Juara ${result.rank} · ${result.points} poin${index === game.seat ? ' · Anda' : ''}`;
    if (member.stopped) count.textContent += ' · Berhenti';
    else if (data.decisions?.[member.id] === 'continue') count.textContent += ' · Lanjut';
    box.append(name, count); $('players').append(box);
  });
  $('table-card').replaceChildren();
  $('hand-area').hidden = !game;
  $('history').replaceChildren();
  $('round-results').hidden = !game || !game.finished;
  $('room-history-link').href = 'room-history.php?room=' + encodeURIComponent(data.room);
  $('room-history-link').textContent = 'Meja ' + data.room;
  $('continue-game').disabled = busy || data.halted || data.decision === 'continue';
  $('stop-game').disabled = busy;
  $('continue-status').textContent = data.halted ? 'Permainan dihentikan karena pemain yang tersisa kurang dari 4. Riwayat tetap tersimpan.' : data.decision === 'continue' ? 'Anda memilih Lanjut. Menunggu keputusan pemain lain.' : 'Pilih Lanjut atau Berhenti. Ronde baru otomatis dimulai setelah semua pemain yang tersisa memilih Lanjut.';
  if (!game) {
    $('turn-message').textContent = 'Menunggu pemain bergabung';
    $('table-note').textContent = data.members.length < data.playerCount ? `Menunggu ${data.playerCount - data.members.length} pemain lagi.` : data.host ? 'Semua pemain sudah bergabung. Klik Mulai permainan.' : 'Menunggu pembuat ruang memulai permainan.';
    return;
  }
  const winner = game.finished;
  const ownResult = game.results.find(result => result.seat === game.seat);
  $('turn-message').textContent = winner ? `🏆 Ronde selesai · ${game.players[game.winner].name} juara 1` : game.turn === game.seat ? 'Giliran Anda' : `Giliran ${game.players[game.turn].name}${ownResult ? ' · Anda selesai di juara ' + ownResult.rank : ''}`;
  if (game.table) $('table-card').append(cardElement(game.table));
  else { const empty = document.createElement('div'); empty.className = 'empty-card'; empty.textContent = '♠'; $('table-card').append(empty); }
  $('table-note').textContent = winner ? 'Poin seluruh pemain sudah tersimpan.' : game.opening ? 'Permainan harus dibuka dengan 4♣ dari deck 1.' : game.table ? 'Mainkan kartu lebih tinggi. Nilai sama tidak dapat dimainkan.' : 'Giliran membuka dengan kartu apa saja.';
  $('hand-count').textContent = `(${game.hand.length})`;
  $('hand').replaceChildren(...game.hand.map(card => cardElement(card, true, game.legal.includes(card.id))));
  $('play-card').disabled = busy || !selected || winner;
  $('pass-turn').hidden = !game.canPass;
  $('pass-turn').disabled = busy;
  game.log.forEach(entry => { const item = document.createElement('li'); item.textContent = entry; $('history').append(item); });
}

async function request(action = null, fields = {}) {
  if (busy || queuedAction) return;
  if (polling && action) {
    queuedAction = true;
    while (polling) await new Promise(resolve => setTimeout(resolve, 50));
    queuedAction = false;
  } else if (polling) return;
  if (action) {
    busy = true;
    if (state) render(state, true);
    $('create-room').disabled = true;
    $('join-room').querySelector('button').disabled = true;
  } else polling = true;
  try {
    const options = {cache:'no-store'};
    if (action) {
      options.method = 'POST';
      options.body = new URLSearchParams({action, token, revision:String(state?.revision ?? ''), ...fields});
    }
    const response = await fetch('poker-api.php', options);
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Permintaan gagal. Silakan coba lagi.');
    $('connection').textContent = 'Terhubung';
    if (action) $('game-message').textContent = data.message || '';
    render(data);
  } catch (error) {
    $('game-message').textContent = error.message || 'Koneksi terputus. Mencoba kembali…';
    $('connection').textContent = 'Mencoba menghubungkan…';
  } finally {
    busy = false;
    polling = false;
    $('create-room').disabled = false;
    $('join-room').querySelector('button').disabled = false;
    if (state) render(state);
  }
}

function updateDealPreview() {
  const players = Number($('player-count').value);
  const total = Number($('deck-count').value) * 54;
  const minimum = Math.floor(total / players);
  const maximum = Math.ceil(total / players);
  $('deal-preview').textContent = `${total} kartu untuk ${players} pemain: ${minimum === maximum ? minimum : minimum + '–' + maximum} kartu per pemain.`;
}
$('player-count').addEventListener('change', updateDealPreview);
$('deck-count').addEventListener('change', updateDealPreview);
updateDealPreview();
const invitedRoom = new URLSearchParams(window.location.search).get('room');
if (invitedRoom && /^[a-f0-9]{8}$/i.test(invitedRoom)) $('room-code').value = invitedRoom.toUpperCase();
$('create-room').addEventListener('click', () => request('create', {playerCount:$('player-count').value, deckCount:$('deck-count').value}));
$('join-room').addEventListener('submit', event => { event.preventDefault(); request('join', {code:$('room-code').value.trim().toUpperCase()}); });
$('start-game').addEventListener('click', () => request('start'));
$('leave-room').addEventListener('click', () => request('leave'));
$('play-card').addEventListener('click', () => { if (selected) request('play', {card:selected}); });
$('pass-turn').addEventListener('click', () => request('pass'));
$('continue-game').addEventListener('click', () => request('continue'));
$('stop-game').addEventListener('click', () => request('stop'));
async function poll() { await request(); setTimeout(poll, 1500); }
poll();
