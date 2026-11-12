// DINGLASAN - Listings (resource) + RIGHT JOIN report
SCREENS.push({ name: 'Listings', render: (el) => crudScreen(el, {
  title: 'Listings', path: '/listings',
  fields: [
    { name: 'title', label: 'Title' }, { name: 'price', label: 'Price', type: 'number' },
    { name: 'category_id', label: 'Category id', type: 'number' }, { name: 'condition_id', label: 'Condition id', type: 'number' },
    { name: 'description', label: 'Description' },
    { name: 'status', label: 'Status', type: 'select', options: [['active', 'active'], ['reserved', 'reserved'], ['sold', 'sold'], ['archived', 'archived']] },
  ],
  report: { title: 'Categories with items and alerts', join: 'RIGHT JOIN', path: '/reports/category-alerts' },
}) });
