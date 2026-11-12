// IJAN - Users & roles (resource) + INNER JOIN report
SCREENS.push({ name: 'Users', render: (el) => crudScreen(el, {
  title: 'Users & Roles', path: '/users',
  fields: [
    { name: 'full_name', label: 'Full name' }, { name: 'email', label: 'Email', type: 'email' },
    { name: 'phone_number', label: 'Phone' }, { name: 'password', label: 'Password (blank = keep)', type: 'password' },
    { name: 'is_verified', label: 'Verified', type: 'select', options: [['1', 'Yes'], ['0', 'No']] },
    { name: 'role_ids', label: 'Role ids (1 Admin, 2 Seller, 3 Buyer)', placeholder: '3' },
  ],
  // role_ids is typed as "2,3": turn it into [2,3]; leave it out when blank so edits keep the old roles
  toBody: (f) => { const b = { ...f }; if (b.role_ids) b.role_ids = b.role_ids.split(',').map(Number); else delete b.role_ids; return b; },
  report: { title: 'Verified sellers', join: 'INNER JOIN', path: '/reports/verified-sellers' },
}) });
