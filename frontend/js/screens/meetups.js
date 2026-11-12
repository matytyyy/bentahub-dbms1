// ESPIRITU - Meetups (resource) + INNER JOIN report. Changing status moves the listing (MySQL triggers).
SCREENS.push({ name: 'Meetups', render: (el) => crudScreen(el, {
  title: 'Meetups', path: '/meetups',
  fields: [
    { name: 'thread_id', label: 'Chat thread id', type: 'number' }, { name: 'zone_id', label: 'Safe zone id', type: 'number' },
    { name: 'scheduled_time', label: 'When', placeholder: '2026-11-20 14:00:00' },
    { name: 'status', label: 'Status', type: 'select', options: [['pending', 'pending'], ['confirmed', 'confirmed'], ['completed', 'completed'], ['cancelled', 'cancelled']] },
  ],
  report: { title: 'Chats that led to a meetup', join: 'INNER JOIN', path: '/reports/chats-meetups' },
}) });
