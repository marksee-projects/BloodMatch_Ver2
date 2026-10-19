// Ignore count responses that started before a successful read action.
let revision = 0
export const notificationReadRevision = () => revision
export function publishNotificationRead(unreadCount) {
  revision++
  window.dispatchEvent(new CustomEvent('bloodmatch:notifications-read', { detail: { unreadCount } }))
}
