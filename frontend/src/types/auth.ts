export type UserRole = 'initiator' | 'manager' | 'lawyer' | 'administrator'

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  email_verified_at: string | null
  created_at: string
  updated_at: string
}

export interface LoginCredentials {
  email: string
  password: string
  remember?: boolean
}
