// Shared UI helpers. Each member's screen file only describes its fields and endpoints; this file
// draws the table + form, calls GET / POST / PUT|PATCH / DELETE, and shows loading and error states.
window.SCREENS = [];

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function tableHtml(rows, actions) {
  if (!rows.length) return '<p class="muted">No rows.</p>';
  const cols = Object.keys(rows[0]);
  const head = cols.map((c) => `<th>${esc(c)}</th>`).join('') + (actions ? '<th></th>' : '');
  const body = rows.map((r, i) =>
    '<tr>' + cols.map((c) => `<td>${esc(r[c])}</td>`).join('') +
    (actions ? `<td><button data-edit="${i}">Edit</button> <button data-del="${i}">Delete</button></td>` : '') + '</tr>').join('');
  return `<table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
}

// cfg: { title, path, fields:[{name,label,type,options}], report:{title,join,path}, updateMethod, toBody, fromRow }
async function crudScreen(el, cfg) {
  let rows = [];
  let editId = null;
  el.innerHTML = `<h2>${esc(cfg.title)}</h2><div id="msg"></div><form id="f" class="card"></form>
    <div class="card" id="list">Loading...</div>
    <h3>${esc(cfg.report.title)} <span class="badge">${esc(cfg.report.join)}</span></h3>
    <div class="card" id="rep">Loading...</div>`;
  const $ = (s) => el.querySelector(s);
  const say = (text, kind = 'error') => { $('#msg').innerHTML = text ? `<div class="flash ${kind}">${esc(text)}</div>` : ''; };
  const fail = (e) => say(`Error ${e.status || ''}: ${e.message}`);

  function drawForm(values = {}) {
    $('#f').innerHTML = cfg.fields.map((f) => {
      const val = values[f.name] ?? '';
      const input = f.type === 'select'
        ? `<select name="${f.name}">${f.options.map(([v, t]) => `<option value="${esc(v)}"${String(v) === String(val) ? ' selected' : ''}>${esc(t)}</option>`).join('')}</select>`
        : `<input name="${f.name}" type="${f.type || 'text'}" value="${f.type === 'password' ? '' : esc(val)}" placeholder="${esc(f.placeholder || '')}">`;
      return `<label>${esc(f.label)}${input}</label>`;
    }).join('') + `<div><button type="submit">${editId ? 'Update' : 'Create'}</button>
      ${editId ? '<button type="button" id="cancel">Cancel</button>' : ''}</div>`;
    const cancel = $('#cancel');
    if (cancel) cancel.onclick = () => { editId = null; drawForm(); say(''); };
  }

  async function load() {
    $('#list').textContent = 'Loading...';
    try {
      rows = await API.get(cfg.path);
      $('#list').innerHTML = tableHtml(rows, true);
      $('#list').querySelectorAll('[data-edit]').forEach((b) => b.onclick = () => {
        const row = rows[b.dataset.edit];
        editId = row.id;
        drawForm(cfg.fromRow ? cfg.fromRow(row) : row);
        scrollTo({ top: 0, behavior: 'smooth' });
      });
      $('#list').querySelectorAll('[data-del]').forEach((b) => b.onclick = async () => {
        const row = rows[b.dataset.del];
        if (!confirm('Delete #' + row.id + '?')) return;
        try { await API.del(cfg.path + '/' + row.id); say('Deleted.', 'success'); await load(); } catch (e) { fail(e); }
      });
    } catch (e) { $('#list').textContent = ''; fail(e); }
    try { $('#rep').innerHTML = tableHtml(await API.get(cfg.report.path), false); }
    catch (e) { $('#rep').textContent = ''; fail(e); }
  }

  $('#f').onsubmit = async (ev) => {
    ev.preventDefault();
    const vals = Object.fromEntries(new FormData($('#f')).entries());
    const body = cfg.toBody ? cfg.toBody(vals) : vals;
    try {
      if (editId) await API[cfg.updateMethod || 'put'](cfg.path + '/' + editId, body);
      else await API.post(cfg.path, body);
      say(editId ? 'Updated.' : 'Created.', 'success');
      editId = null; drawForm(); await load();
    } catch (e) { fail(e); }       // e.g. 400 bad input, 403 not allowed, 409 blocked by a MySQL trigger
  };

  drawForm();
  await load();
}
