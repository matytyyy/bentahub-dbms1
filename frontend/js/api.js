// API client. "async" marks a function that returns a Promise; "await" pauses INSIDE it until the
// network call finishes, without freezing the page. This is how the frontend talks to the Node backend.
const API = {
  async request(method, path, body) {
    const headers = { 'Content-Type': 'application/json' };
    const token = localStorage.getItem('token');
    if (token) headers.Authorization = 'Bearer ' + token;

    let res;
    try {
      res = await fetch('/api' + path, { method, headers, body: body ? JSON.stringify(body) : undefined });
    } catch (e) {
      throw Object.assign(new Error('Cannot reach the server.'), { status: 0 });
    }
    let json;
    try { json = await res.json(); } catch (e) { json = { success: false, message: 'Unexpected server response.' }; }

    if (res.status === 401 && path !== '/auth/login') {      // token missing or expired -> back to login
      localStorage.removeItem('token');
      location.reload();
    }
    if (!res.ok || !json.success) throw Object.assign(new Error(json.message || 'Request failed.'), { status: res.status });
    return json.data;
  },
  get:   (p)    => API.request('GET', p),
  post:  (p, b) => API.request('POST', p, b),
  put:   (p, b) => API.request('PUT', p, b),
  patch: (p, b) => API.request('PATCH', p, b),
  del:   (p)    => API.request('DELETE', p),
};
