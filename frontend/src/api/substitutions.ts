import api from '../lib/api'

export interface Substitution {
  id: number
  user_id: number
  substitute_user_id: number
  from_date: string
  to_date: string
  user: { id: number; name: string; email: string } | null
  created_at: string
  updated_at: string
}

export async function fetchSubstitutions(): Promise<Substitution[]> {
  const { data } = await api.get('/substitutions')
  return data
}
