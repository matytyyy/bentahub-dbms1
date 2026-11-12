// App shell: login, navigation between the six screens, logout.
const appEl = document.getElementById('app');
const navEl = document.getElementById('nav');
const whoEl = document.getElementById('who');

function showLogin() {
  navEl.innerHTML = ''; whoEl.innerHTML = '';
  appEl.innerHTML = `<form id="login" class="card login"><h2>Log in</h2><div id="msg"></div>
    <label>Email<input name="email" value="matt.ijan@bentahub.ph"></label>
    <label>Password<input name="password" type="password" value="password123"></label>
    <button type="submit">Log in</button></form>`;
  document.getElementById('login').onsubmit = async (ev) => {
    ev.preventDefault();
    const f = Object.fromEntries(new FormData(ev.target).entries());
    document.getElementById('msg').textContent = 'Logging in...';
    try {
      const data = await API.post('/auth/login', f);
      localStorage.setItem('token', data.token);
      localStorage.setItem('user', JSON.stringify(data.user));
      start();
    } catch (e) {
      document.getElementById('msg').innerHTML = `<div class="flash error">Error ${e.status}: ${esc(e.message)}</div>`;
    }
  };
}

function open(i) {
  [...navEl.children].forEach((b, n) => b.classList.toggle('active', n === i));
  appEl.innerHTML = '<p class="muted">Loading...</p>';
  SCREENS[i].render(appEl);
}

function start() {
  const user = JSON.parse(localStorage.getItem('user') || '{}');
  whoEl.innerHTML = `${esc(user.full_name)} (${esc((user.roles || []).join(', '))}) <button id="out">Log out</button>`;
  document.getElementById('out').onclick = () => { localStorage.clear(); location.reload(); };
  navEl.innerHTML = SCREENS.map((s, i) => `<button data-i="${i}">${esc(s.name)}</button>`).join('');
  [...navEl.children].forEach((b) => b.onclick = () => open(Number(b.dataset.i)));
  open(0);
}

if (localStorage.getItem('token')) start(); else showLogin();
