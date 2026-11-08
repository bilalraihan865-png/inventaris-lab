// Skrip umum aplikasi (JavaScript biasa).
document.addEventListener('click', function (e) {
  // Konfirmasi aksi penting
  var c = e.target.closest('[data-confirm]');
  if (c && !window.confirm(c.getAttribute('data-confirm'))) {
    e.preventDefault();
    return;
  }

  // Tampilkan/sembunyikan password
  var t = e.target.closest('[data-toggle-password]');
  if (t) {
    var input = document.getElementById(t.getAttribute('data-toggle-password'));
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    t.textContent = show ? 'Sembunyikan' : 'Lihat';
  }

  // Sidebar mobile
  if (e.target.closest('[data-sidebar-open]')) document.body.classList.add('sidebar-open');
  if (e.target.closest('[data-sidebar-close]')) document.body.classList.remove('sidebar-open');
});
