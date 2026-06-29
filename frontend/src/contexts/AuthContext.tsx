import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { getCsrfCookie } from '../lib/api'
import api from '../lib/api'
import type { LoginCredentials, User } from '../types/auth'

interface AuthState {
  user: User | null
  isLoading: boolean
  isAuthenticated: boolean
}

interface AuthContextValue extends AuthState {
  login: (credentials: LoginCredentials) => Promise<void>
  logout: () => Promise<void>
  refetchUser: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>({
    user: null,
    isLoading: true,
    isAuthenticated: false,
  })

  const refetchUser = useCallback(async () => {
    try {
      const { data } = await api.get<User>('/user')
      setState({
        user: data,
        isLoading: false,
        isAuthenticated: true,
      })
    } catch {
      setState({
        user: null,
        isLoading: false,
        isAuthenticated: false,
      })
    }
  }, [])

  const login = useCallback(
    async (credentials: LoginCredentials) => {
      await getCsrfCookie()
      await api.post('/login', credentials)
      await refetchUser()
    },
    [refetchUser]
  )

  const logout = useCallback(async () => {
    try {
      await api.post('/logout')
    } finally {
      setState({
        user: null,
        isLoading: false,
        isAuthenticated: false,
      })
    }
  }, [])

  useEffect(() => {
    refetchUser()
  }, [refetchUser])

  const value = useMemo<AuthContextValue>(
    () => ({
      ...state,
      login,
      logout,
      refetchUser,
    }),
    [state, login, logout, refetchUser]
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
