import api from '../lib/api'

export interface UserListItem {
  id: number
  name: string
  email: string
  role: string
}

export async function fetchUsers(role?: string): Promise<UserListItem[]> {
  const params: Record<string, unknown> = {}
  if (role) params.role = role
  const { data } = await api.get('/users', { params })
  return data
}
