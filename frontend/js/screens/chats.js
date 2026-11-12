// CABUGNASON - Chat messages (resource, edited with PATCH) + LEFT JOIN report
SCREENS.push({ name: 'Chats', render: (el) => crudScreen(el, {
  title: 'Chat Messages', path: '/messages', updateMethod: 'patch',
  fields: [
    { name: 'thread_id', label: 'Chat thread id', type: 'number' }, { name: 'message_body', label: 'Message' },
  ],
  report: { title: 'Threads with message counts', join: 'LEFT JOIN', path: '/reports/threads-message-counts' },
}) });
