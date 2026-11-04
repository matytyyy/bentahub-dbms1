// Tiny enhancements: confirm dialogs, live table filter, auto-dismiss notices.
document.addEventListener('submit', function (ev) {
  var msg = ev.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) ev.preventDefault();
});
document.addEventListener('input', function (ev) {
  var sel = ev.target.getAttribute('data-filter');
  if (!sel) return;
  var q = ev.target.value.toLowerCase();
  document.querySelectorAll(sel + ' tbody tr').forEach(function (tr) {
    if (tr.querySelector('td.empty')) return;
    tr.style.display = tr.textContent.toLowerCase().indexOf(q) === -1 ? 'none' : '';
  });
});
window.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.flash-success, .flash-info').forEach(function (el) {
    setTimeout(function () { el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 400); }, 5000);
  });
});
