import api from '../lib/api'

export interface AppNotification {
  id: string
  type: string
  data: { message?: string; task_id?: number; event_type?: string }
  read_at: string | null
  created_at: string
}

export interface NotificationsResponse {
  data: AppNotification[]
  unread_count: number
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export async function fetchNotifications(page = 1, perPage = 30): Promise<NotificationsResponse> {
  const { data } = await api.get<NotificationsResponse>('/notifications', {
    params: { page, per_page: perPage },
  })
  return data
}

export async function fetchUnreadCount(): Promise<number> {
  const { data } = await api.get<{ count: number }>('/notifications/unread-count')
  return data.count
}

export async function markNotificationRead(id: string): Promise<void> {
  await api.post(`/notifications/${id}/read`)
}

export async function markAllNotificationsRead(): Promise<void> {
  await api.post('/notifications/read-all')
}

export async function deleteNotification(id: string): Promise<void> {
  await api.delete(`/notifications/${id}`)
}

export async function deleteAllNotifications(): Promise<void> {
  await api.delete('/notifications')
}
