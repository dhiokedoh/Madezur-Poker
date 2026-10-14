(() => {
  const paths = {
    login:'M10 17l5-5-5-5M3 12h12M14 3h6v18h-6',
    user:'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M4 21v-2a8 8 0 0 1 16 0v2',
    plus:'M12 4v16M4 12h16',
    play:'M8 4l12 8-12 8z',
    exit:'M9 3H3v18h6M14 8l5 4-5 4M8 12h11',
    cards:'M5 3h12v18H5zM9 7h4M9 17h4M18 6l3 1-3 13',
    pass:'M4 12h14M13 7l5 5-5 5M20 5v14',
    trophy:'M8 3h8v7a4 4 0 0 1-8 0zM8 5H4v3a4 4 0 0 0 4 4M16 5h4v3a4 4 0 0 1-4 4M12 14v6M8 21h8',
    refresh:'M20 7v5h-5M4 17v-5h5M6 7a7 7 0 0 1 12-2l2 2M18 17a7 7 0 0 1-12 2l-2-2',
    close:'M6 6l12 12M6 18L18 6',
    back:'M20 12H4M10 6l-6 6 6 6'
  };
  function decorate() {
    document.querySelectorAll('button:not(.poker-card-face),a.logout-button').forEach(button => {
      if (button.querySelector('svg')) return;
      const id = button.id;
      let name = button.dataset.icon;
      if (!name) name = id === 'create-room' ? 'plus' : id === 'start-game' ? 'play' : id === 'leave-room' ? 'exit' : id === 'play-card' ? 'cards' : id === 'pass-turn' ? 'pass' : id === 'switch-mode' ? (button.textContent.includes('Daftar') ? 'user' : 'login') : button.closest('#join-room') ? 'login' : button.classList.contains('submit') ? (button.textContent.includes('Daftar') ? 'user' : 'login') : button.textContent.includes('Keluar') ? 'exit' : button.textContent.includes('Kembali') ? 'back' : 'play';
      const svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
      svg.setAttribute('viewBox','0 0 24 24'); svg.setAttribute('fill','none'); svg.setAttribute('stroke','currentColor'); svg.setAttribute('stroke-width','1.7'); svg.setAttribute('stroke-linecap','round'); svg.setAttribute('stroke-linejoin','round'); svg.setAttribute('aria-hidden','true'); svg.classList.add('button-icon');
      const path = document.createElementNS('http://www.w3.org/2000/svg','path'); path.setAttribute('d',paths[name] || paths.play); svg.append(path); button.prepend(svg);
    });
  }
  new MutationObserver(decorate).observe(document.body,{childList:true,subtree:true});
  decorate();
})();
