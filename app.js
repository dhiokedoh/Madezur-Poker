const password = document.querySelector('#password');
const toggle = document.querySelector('.toggle-password');
const status = document.querySelector('#form-status');
let registering = false;
const modeButton = document.querySelector('#switch-mode');
modeButton.addEventListener('click', () => {
  registering = !registering;
  document.querySelector('#account-prompt').textContent = registering ? 'Sudah punya akun?' : 'Belum punya akun?';
  modeButton.textContent = registering ? 'Masuk sekarang' : 'Daftar sekarang';
  document.querySelector('.login-eyebrow').textContent = registering ? 'BERGABUNG DI MADEZUR POKER' : 'SELAMAT DATANG KEMBALI';
  document.querySelector('h2').textContent = registering ? 'Daftar akun' : 'Masuk';
  document.querySelector('.intro').textContent = registering ? 'Isi username dan kata sandi, maksimal 4 karakter.' : 'Masuk dengan akun Anda untuk bermain.';
  document.querySelector('.submit').textContent = registering ? 'Daftar akun' : 'Masuk';
  password.autocomplete = registering ? 'new-password' : 'current-password';
  password.minLength = 1;
  if (registering) password.maxLength = 4;
  else password.removeAttribute('maxlength');
  password.placeholder = registering ? 'Maksimal 4 karakter' : 'Masukkan kata sandi';
  password.value = '';
  status.textContent = '';
  document.title = registering ? 'Madezur Poker — Daftar' : 'Madezur Poker — Masuk';
  document.querySelector('#identity').focus();
});

toggle.addEventListener('click', () => {
  const visible = password.type === 'password';
  password.type = visible ? 'text' : 'password';
  toggle.setAttribute('aria-pressed', String(visible));
  toggle.setAttribute('aria-label', visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
});

document.querySelector('#login-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const identity = document.querySelector('#identity');
  if (!identity.value.trim()) {
    identity.setCustomValidity('Masukkan username Anda.');
    identity.reportValidity();
    return;
  }
  const button = event.currentTarget.querySelector('.submit');
  const formData = new FormData(event.currentTarget);
  button.disabled = true;
  modeButton.disabled = true;
  status.textContent = registering ? 'Membuat akun…' : 'Memeriksa akun…';
  try {
    const response = await fetch(registering ? 'register.php' : 'login.php', { method: 'POST', body: formData });
    const result = await response.json();
    if (response.ok && registering) {
      modeButton.disabled = false;
      modeButton.click();
      status.textContent = result.message;
      password.focus();
      return;
    }
    if (response.ok && result.redirect) {
      window.location.assign(result.redirect);
      return;
    }
    status.textContent = result.message || 'Login gagal. Silakan coba lagi.';
  } catch {
    status.textContent = 'Tidak dapat menghubungi server. Silakan coba lagi.';
  } finally {
    button.disabled = false;
    modeButton.disabled = false;
  }
});

document.querySelector('#identity').addEventListener('input', (event) => {
  event.target.setCustomValidity('');
  status.textContent = '';
});

document.querySelectorAll('[data-info]').forEach((button) => {
  button.addEventListener('click', () => {
    status.textContent = button.dataset.info;
  });
});
