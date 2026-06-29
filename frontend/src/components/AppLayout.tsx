import { useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { useBranding } from '../contexts/BrandingContext'
import { NotificationsBell } from './NotificationsBell'
import AiChatWidget from './AiChatWidget'
import DarkModeToggle from './ui/DarkModeToggle'

const navItems = [
  { to: '/dashboard', label: 'Dashboard' },
  { to: '/partners', label: 'Partners' },
  { to: '/inventory', label: 'Inventory' },
  { to: '/tasks', label: 'Tasks' },
  { to: '/templates', label: 'Templates' },
  { to: '/archive', label: 'Archive' },
  { to: '/finalized-docs', label: 'Finalized Docs' },
  { to: '/settings', label: 'Settings' },
]

export default function AppLayout() {
  const { user, logout } = useAuth()
  const { appName, companyName } = useBranding()
  const navigate = useNavigate()
  const [mobileOpen, setMobileOpen] = useState(false)

  async function handleLogout() {
    await logout()
    navigate('/login', { replace: true })
  }

  const isAdmin = user?.role === 'administrator'

  const navLinkClass = ({ isActive }: { isActive: boolean }) =>
    `px-3 py-2 rounded-md text-sm font-medium transition-colors ${
      isActive
        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
        : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
    }`

  return (
    <div className="min-h-screen flex flex-col bg-slate-50 dark:bg-slate-900">
      <header className="sticky top-0 z-30 border-b border-slate-200 dark:border-slate-700 bg-white/95 dark:bg-slate-800/95 backdrop-blur supports-[backdrop-filter]:bg-white/80 dark:supports-[backdrop-filter]:bg-slate-800/80">
        <div className="flex items-center justify-between h-14 px-4 max-w-7xl mx-auto">
          <div className="flex items-center gap-6">
            <Link to="/dashboard" className="text-lg font-bold text-blue-600 dark:text-blue-400 tracking-tight">
              {appName}
            </Link>

            {/* Desktop nav */}
            <nav className="hidden md:flex gap-1">
              {navItems.map((item) => (
                <NavLink key={item.to} to={item.to} className={navLinkClass}>
                  {item.label}
                </NavLink>
              ))}
              {isAdmin && (
                <a
                  href={(import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000') + '/admin'}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="px-3 py-2 rounded-md text-amber-600 dark:text-amber-400 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm font-medium"
                >
                  Admin
                </a>
              )}
            </nav>
          </div>

          <div className="flex items-center gap-2">
            <DarkModeToggle />
            <NotificationsBell />
            <span className="hidden sm:inline text-sm text-slate-500 dark:text-slate-400 ml-1">
              {user?.name} <span className="text-slate-400 dark:text-slate-500">({user?.role})</span>
            </span>
            <button
              type="button"
              onClick={handleLogout}
              className="text-sm text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white px-2 py-1"
            >
              Log out
            </button>

            {/* Mobile hamburger */}
            <button
              type="button"
              onClick={() => setMobileOpen(!mobileOpen)}
              className="md:hidden rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"
              aria-label="Toggle menu"
            >
              <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {mobileOpen ? (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                ) : (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                )}
              </svg>
            </button>
          </div>
        </div>

        {/* Mobile nav */}
        {mobileOpen && (
          <nav className="md:hidden border-t border-slate-200 dark:border-slate-700 px-4 py-2 space-y-1">
            {navItems.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                onClick={() => setMobileOpen(false)}
                className={({ isActive }) =>
                  `block px-3 py-2 rounded-md text-sm font-medium ${
                    isActive
                      ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                      : 'text-slate-600 dark:text-slate-300'
                  }`
                }
              >
                {item.label}
              </NavLink>
            ))}
            {isAdmin && (
              <a
                href={(import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000') + '/admin'}
                target="_blank"
                rel="noopener noreferrer"
                className="block px-3 py-2 rounded-md text-amber-600 dark:text-amber-400 text-sm font-medium"
              >
                Admin Panel
              </a>
            )}
          </nav>
        )}
      </header>

      <main className="flex-1 p-4 max-w-7xl mx-auto w-full">
        <Outlet />
      </main>

      <footer className="border-t border-slate-200 dark:border-slate-700 py-4 px-4">
        <div className="max-w-7xl mx-auto flex items-center justify-between text-xs text-slate-400 dark:text-slate-500">
          <span>{companyName ? `${companyName} — ` : ''}{appName}</span>
          <Link to="/help" className="hover:text-slate-600 dark:hover:text-slate-300">Help & Manual</Link>
        </div>
      </footer>

      <AiChatWidget />
    </div>
  )
}
