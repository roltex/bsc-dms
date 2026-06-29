import api from '../lib/api'

export interface DashboardData {
  pending_count: number
  overdue_count: number
  total_partners: number
  total_archived: number
  total_tasks: number
  today_activity_count: number
  status_breakdown: Record<string, number>
  recent_activities: Array<{
    id: number
    task_id: number
    user_id: number
    action: string
    comment: string | null
    created_at: string
    user: { id: number; name: string } | null
    task: { id: number; status: string } | null
  }>
  pending_tasks: Array<{
    id: number
    status: string
    deadline: string | null
    updated_at: string
    category: { id: number; name: string } | null
    partner: { id: number; name: string } | null
  }>
}

export async function fetchDashboard(): Promise<DashboardData> {
  const { data } = await api.get('/dashboard')
  return data
}
