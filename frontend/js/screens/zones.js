// IB-IB - Safe meetup zones (resource) + LEFT JOIN report
SCREENS.push({ name: 'Safe Zones', render: (el) => crudScreen(el, {
  title: 'Safe Meetup Zones', path: '/safe-zones',
  fields: [
    { name: 'zone_name', label: 'Zone name' }, { name: 'address', label: 'Address' }, { name: 'city', label: 'City' },
    { name: 'is_geofenced', label: 'Geofenced', type: 'select', options: [['1', 'Yes'], ['0', 'No']] },
  ],
  report: { title: 'Zones with meetup counts', join: 'LEFT JOIN', path: '/reports/zone-meetups' },
}) });
