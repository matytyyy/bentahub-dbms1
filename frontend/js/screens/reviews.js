// GEMINA - Reviews (resource) + FULL OUTER JOIN report. The seller's tier updates by trigger.
SCREENS.push({ name: 'Reviews & Trust', render: (el) => crudScreen(el, {
  title: 'Reviews & Trust', path: '/reviews',
  fields: [
    { name: 'meetup_id', label: 'Completed meetup id', type: 'number' },
    { name: 'rating', label: 'Rating (1-5)', type: 'number' }, { name: 'comment', label: 'Comment' },
  ],
  report: { title: 'Trust scores vs audit activity', join: 'FULL OUTER JOIN', path: '/reports/trust-vs-audit' },
}) });
